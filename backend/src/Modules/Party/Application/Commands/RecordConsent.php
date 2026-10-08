<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Grant or withdraw consent for one purpose (FR-CUS-008, FR-CMP-010/021/032). */
#[HandledBy(RecordConsentHandler::class)]
final readonly class RecordConsent implements Command, ValidatesInput
{
    public function __construct(
        public string $partyId,
        public string $purpose,
        public string $consentAction,
        public string $channel,
        public string $termsVersion,
        public ?string $evidenceRef,
        public ?string $applicationId,
    ) {}

    public function action(): string
    {
        return 'party.consent.'.($this->consentAction === 'withdraw' ? 'withdrawn' : 'granted');
    }

    public function permission(): string
    {
        return 'party:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('party', $this->partyId);
    }

    public function data(): array
    {
        return ['purpose' => $this->purpose, 'action' => $this->consentAction, 'channel' => $this->channel, 'terms_version' => $this->termsVersion, 'evidence_ref' => $this->evidenceRef, 'application_id' => $this->applicationId];
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', 'in:data_processing,credit_bureau,credit_reporting,marketing,third_party_sharing'],
            'action' => ['required', 'in:grant,withdraw'],
            'channel' => ['required', 'in:branch,digital,call_centre,agent,api'],
            'terms_version' => ['required', 'string', 'max:32'],
            'evidence_ref' => ['nullable', 'string', 'max:200'],
            'application_id' => ['nullable', 'uuid'],
        ];
    }
}
