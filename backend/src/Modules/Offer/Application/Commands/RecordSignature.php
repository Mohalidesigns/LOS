<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Record a wet signature for the next signatory in order (FR-OFR-005/009). */
#[HandledBy(RecordSignatureHandler::class)]
final readonly class RecordSignature implements Command, ValidatesInput
{
    public function __construct(public string $offerId, public string $signatoryId, public ?string $signedAt, public ?string $witnessName, public ?string $place) {}

    public function action(): string
    {
        return 'offer.signature_recorded';
    }

    public function permission(): string
    {
        return 'offer:prepare';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('offer', $this->offerId);
    }

    public function data(): array
    {
        return ['signed_at' => $this->signedAt, 'witness_name' => $this->witnessName, 'place' => $this->place];
    }

    public function rules(): array
    {
        return ['signed_at' => ['required', 'date'], 'witness_name' => ['nullable', 'string', 'max:200'], 'place' => ['nullable', 'string', 'max:200']];
    }
}
