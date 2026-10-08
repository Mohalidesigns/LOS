<?php

declare(strict_types=1);

namespace Fundly\Shared\Bus;

use Fundly\Shared\Security\ResourceRef;

/**
 * A state-changing intent. Commands are explicit DTOs (no mass assignment,
 * TRD §8.4) and run only through the CommandBus.
 */
interface Command
{
    /** Audit action name, e.g. "access.role.create". */
    public function action(): string;

    /** Permission required; null only for commands that are exclusively system-issued. */
    public function permission(): ?string;

    /** Entity the command acts on, for scope checks and audit; null for creations. */
    public function resource(): ?ResourceRef;
}
