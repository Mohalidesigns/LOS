<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Offer\Application\OfferFile;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;

final class ExpireOfferHandler implements CommandHandler
{
    public function __construct(private readonly OfferFile $file, private readonly ApplicationLifecycle $lifecycle) {}

    public function handle(Command $command, CommandContext $context): null
    {
        assert($command instanceof ExpireOffer);
        $offer = $this->file->offer($command->offerId);
        if ($offer->status !== 'issued' || $offer->valid_until->greaterThan($this->file->now())) {
            throw new DomainRuleViolation('Only an issued offer past its validity can expire.');
        }
        $app = $this->file->application($offer->application_id);
        $offer->forceFill(['status' => 'expired', 'decided_at' => $this->file->now()])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'offer', entityId: $offer->id, before: ['status' => 'issued'], after: ['status' => 'expired', 'valid_until' => $offer->valid_until->toIso8601String()]));
        if ($app->status === CanonicalStatus::OfferIssued) {
            $this->lifecycle->advance($app->id, CanonicalStatus::Expired, 'OFFER_VALIDITY_LAPSED', "Offer #{$offer->sequence} was not accepted by {$offer->valid_until->toDateString()}.", $context->principal);
        }

        return null;
    }
}
