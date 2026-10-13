<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Offer\Application\OfferFile;
use Fundly\Modules\Offer\Application\OfferQueries;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;

final class DeclineOfferHandler implements CommandHandler
{
    public function __construct(private readonly OfferFile $file, private readonly ApplicationLifecycle $lifecycle, private readonly OfferQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof DeclineOffer);
        $offer = $this->file->offer($command->offerId);
        $app = $this->file->application($offer->application_id);
        if ($offer->status !== 'issued' || $app->status !== CanonicalStatus::OfferIssued) {
            throw new DomainRuleViolation("Only a live issued offer can be declined; this one is {$offer->status}.");
        }
        $offer->forceFill([
            'status' => $command->outcome === 'negotiate' ? 'negotiating' : 'rejected',
            'decision_reason_code' => $command->reasonCode, 'decision_note' => $command->note,
            'decided_by' => $context->principal->id, 'decided_at' => $this->file->now(),
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'offer', entityId: $offer->id, after: ['outcome' => $command->outcome, 'status' => $offer->status], reasonCode: $command->reasonCode, reasonText: $command->note));
        // Both routes go back to credit (G-14d): revised terms need a fresh decision, recommendation and approval.
        $this->lifecycle->advance($app->id, CanonicalStatus::Assessment, $command->outcome === 'negotiate' ? 'OFFER_NEGOTIATION' : 'OFFER_REJECTED', "Offer #{$offer->sequence}: {$command->reasonCode}. {$command->note}", $context->principal);

        return ['data' => $this->queries->present($offer)];
    }
}
