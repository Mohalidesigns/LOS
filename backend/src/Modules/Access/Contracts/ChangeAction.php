<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Contracts;

use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceRef;

/**
 * An action type that runs only through maker-checker (TRD §6.4, FR-SEC-007).
 * Modules implement this and register it with the ChangeActionRegistry.
 */
interface ChangeAction
{
    public function type(): string;

    /** Permission the maker needs to raise the request. */
    public function makerPermission(): string;

    /** Permission the checker needs, evaluated in scope of entity(). */
    public function checkerPermission(): string;

    /** @param array<string, mixed> $payload */
    public function entity(array $payload): ?ResourceRef;

    /**
     * Validate against *current* state. Called at submission and again at
     * execution, so a request that has become invalid is never applied.
     *
     * @param  array<string, mixed>  $payload
     */
    public function validate(array $payload, Principal $maker): void;

    /**
     * Fingerprint of the state the request depends on. If it differs at
     * execution from submission, the request fails stale.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fingerprint(array $payload): string;

    /**
     * Apply the change. Runs inside the checker's command transaction; audit
     * entries go on the context.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function execute(array $payload, string $changeRequestId, CommandContext $context): array;

    /**
     * Users who may not act as checker beyond the maker (e.g. the user whose
     * access is being changed).
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public function excludedCheckers(array $payload): array;
}
