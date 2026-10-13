<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** The customer rejects the offer or wants to negotiate: back to credit for revised terms (FR-OFR-008, G-14d). */
#[HandledBy(DeclineOfferHandler::class)]
final readonly class DeclineOffer implements Command, ValidatesInput
{
    public function __construct(public string $offerId, public string $outcome, public string $reasonCode, public ?string $note) {}

    public function action(): string
    {
        return 'offer.declined';
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
        return ['outcome' => $this->outcome, 'reason_code' => $this->reasonCode, 'note' => $this->note];
    }

    public function rules(): array
    {
        return ['outcome' => ['required', 'in:reject,negotiate'], 'reason_code' => ['required', 'regex:/^[A-Z][A-Z0-9_]{1,47}$/'], 'note' => ['required', 'string', 'min:10', 'max:2000']];
    }
}
