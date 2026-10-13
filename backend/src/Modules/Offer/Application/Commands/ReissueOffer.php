<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Replace an issued offer with a fresh one (corrected terms or validity), within the template's re-issue limit (FR-OFR-007). */
#[HandledBy(ReissueOfferHandler::class)]
final readonly class ReissueOffer implements Command, ValidatesInput
{
    public function __construct(public string $offerId, public ?string $reason) {}

    public function action(): string
    {
        return 'offer.reissued';
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
        return ['reason' => $this->reason];
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:2000']];
    }
}
