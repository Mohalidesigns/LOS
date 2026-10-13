<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Offer\Application\OfferBuilder;
use Fundly\Modules\Offer\Application\OfferFile;
use Fundly\Modules\Offer\Application\OfferQueries;
use Fundly\Modules\Offer\Infrastructure\Models\Offer;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;

final class IssueOfferHandler implements CommandHandler
{
    public function __construct(private readonly OfferFile $file, private readonly OfferBuilder $builder, private readonly ApplicationLifecycle $lifecycle, private readonly OfferQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof IssueOffer);
        $app = $this->file->application($command->applicationId);
        if (! in_array($app->status, [CanonicalStatus::Approved, CanonicalStatus::CounterOffered], true)) {
            throw new DomainRuleViolation("An offer is issued on an approved or counter-offered application; this one is {$app->status->value}.");
        }
        if (Offer::query()->where('application_id', $app->id)->where('status', 'issued')->exists()) {
            throw new DomainRuleViolation('This application already has an issued offer; re-issue it instead.');
        }
        $offer = $this->builder->build($app, $context->principal->id);
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'offer', entityId: $offer->id, after: [
            'application_id' => $app->id, 'sequence' => $offer->sequence, 'template' => ['key' => $offer->template_key, 'version_no' => $offer->template_version_no],
            'terms' => $offer->terms, 'document_sha256' => $offer->document_sha256, 'valid_until' => $offer->valid_until->toIso8601String(),
        ]));
        $this->lifecycle->advance($app->id, CanonicalStatus::OfferIssued, 'OFFER_ISSUED', "Offer #{$offer->sequence} valid until {$offer->valid_until->toDateString()}", $context->principal);

        return ['data' => $this->queries->present($offer)];
    }
}
