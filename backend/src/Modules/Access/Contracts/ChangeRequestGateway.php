<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Contracts;

use Fundly\Shared\Bus\CommandContext;

/**
 * Public entry point other modules use to raise maker-checker requests.
 */
interface ChangeRequestGateway
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> the created change request (API representation)
     */
    public function submit(string $actionType, array $payload, ?string $reason, CommandContext $context): array;
}
