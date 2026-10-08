<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\ChangeRequests;

use Fundly\Shared\Bus\Command;

/** A command whose effect is to raise a maker-checker change request. */
interface SubmitsChangeRequest extends Command
{
    public function changeActionType(): string;

    /** @return array<string, mixed> */
    public function changePayload(): array;

    public function changeReason(): ?string;
}
