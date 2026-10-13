<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Offer\Application\OfferBuilder;
use Fundly\Modules\Offer\Application\OfferFile;
use Fundly\Modules\Offer\Application\OfferQueries;
use Fundly\Modules\Offer\Infrastructure\Models\OfferSignatory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;

final class ReissueOfferHandler implements CommandHandler
{
    public function __construct(private readonly OfferFile $file, private readonly OfferBuilder $builder, private readonly OfferQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof ReissueOffer);
        $old = $this->file->offer($command->offerId);
        $app = $this->file->application($old->application_id);
        if ($old->status !== 'issued' || $app->status !== CanonicalStatus::OfferIssued) {
            throw new DomainRuleViolation("Only a live issued offer can be re-issued; this one is {$old->status}.");
        }
        $max = $this->file->templateOf($old)->maxReissues();
        if ($old->reissue_count >= $max) {
            throw new DomainRuleViolation("This offer has been re-issued {$old->reissue_count} times, the template's limit; route it back to credit instead.");
        }
        if (OfferSignatory::query()->where('offer_id', $old->id)->whereNotNull('signed_at')->exists()) {
            throw new DomainRuleViolation('Signatures have been recorded on this offer; a re-issue would void them. Decline it and route back to credit instead.');
        }
        $old->forceFill(['status' => 'superseded', 'decided_by' => $context->principal->id, 'decided_at' => $this->file->now(), 'decision_note' => $command->reason])->save();
        $new = $this->builder->build($app, $context->principal->id, $old);
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'offer', entityId: $new->id, before: ['offer_id' => $old->id, 'sequence' => $old->sequence], after: [
            'sequence' => $new->sequence, 'reissue_count' => $new->reissue_count, 'terms' => $new->terms, 'document_sha256' => $new->document_sha256, 'valid_until' => $new->valid_until->toIso8601String(),
        ], reasonText: $command->reason));

        return ['data' => $this->queries->present($new)];
    }
}
