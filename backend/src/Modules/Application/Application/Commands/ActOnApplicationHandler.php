<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application\Commands;

use Fundly\Modules\Application\Application\ApplicationQueries;
use Fundly\Modules\Application\Application\Support\EventPublisher;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Application\Contracts\TransitionGuard;
use Fundly\Modules\Application\Domain\Application;
use Fundly\Modules\Application\Domain\ApplicationAction;
use Fundly\Modules\Application\Infrastructure\ApplicationStore;
use Fundly\Modules\Application\Infrastructure\Models\ApplicationRecord;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Http\ETag;
use Fundly\Shared\Money\Money;
use Illuminate\Contracts\Container\Container;

final class ActOnApplicationHandler implements CommandHandler
{
    public function __construct(
        private readonly ApplicationStore $store,
        private readonly ProductCatalogue $products,
        private readonly ApplicationQueries $queries,
        private readonly ApplicationReader $reader,
        private readonly Container $container,
    ) {}

    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof ActOnApplication);
        $app = $this->store->load($command->applicationId);
        ETag::assertHeader($command->ifMatch, ApplicationQueries::etag($app->id(), $app->version()));
        $returnTo = null;
        if ($command->returnTo !== null) {
            $returnTo = CanonicalStatus::tryFrom($command->returnTo) ?? throw ValidationFailed::with(['return_to' => 'Unknown status.']);
        }
        $blockers = $command->verb === ApplicationAction::Submit ? $this->productBlockers($app) : [];
        $blockers = array_merge($blockers, $this->guardBlockers($app->id(), $command->verb->value));
        $app->act($command->verb, $command->reasonCode, $command->reasonText, $returnTo, $blockers);
        EventPublisher::publish($context, $app, $this->store->save($app, $context->principal));

        return $this->queries->viewForCommand($app->id());
    }

    /**
     * The pinned product version's limits (FR-PRD-003/006): checked at submit,
     * not at draft, so a draft can be saved incomplete (FR-CHN-003).
     *
     * @return list<string>
     */
    private function productBlockers(Application $app): array
    {
        $record = ApplicationRecord::query()->findOrFail($app->id());
        $product = $this->products->version($record->product_version_id);
        if ($product === null) {
            return ['The pinned product version is no longer readable.'];
        }
        $blockers = [];
        if ($app->requestedAmount() !== null && ! $product->allowsAmount(Money::of($app->requestedAmount(), $app->currency()))) {
            $blockers[] = sprintf('requested_amount must be between %s and %s %s.', $product->amountMin->amount, $product->amountMax->amount, $product->currency);
        }
        if ($app->tenorMonths() !== null && ! $product->allowsTenor($app->tenorMonths())) {
            $blockers[] = sprintf('tenor_months must be between %d and %d.', $product->tenorMinMonths, $product->tenorMaxMonths);
        }
        foreach ($app->applicants() as $partyId => $a) {
            if (in_array($a['role'], ['primary', 'joint'], true) && ! in_array($a['party_type'], $product->applicantTypes, true)) {
                $blockers[] = "Applicant {$partyId} is a {$a['party_type']}, which this product does not serve.";
            }
        }

        return $blockers;
    }

    /** @return list<string> preconditions contributed by other modules (TransitionGuard) */
    private function guardBlockers(string $applicationId, string $action): array
    {
        $summary = $this->reader->find($applicationId);
        if ($summary === null) {
            return [];
        }
        $out = [];
        foreach ($this->container->tagged(TransitionGuard::TAG) as $guard) {
            if ($guard instanceof TransitionGuard) {
                $out = array_merge($out, $guard->blockers($summary, $action));
            }
        }

        return $out;
    }
}
