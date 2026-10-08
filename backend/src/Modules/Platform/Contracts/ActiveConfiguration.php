<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Contracts;

/** Read configuration for the current tenant: the active version, or a pinned version by id. */
interface ActiveConfiguration
{
    /** @return array<string, mixed>|null content of the active version */
    public function content(string $type, string $key): ?array;

    public function activeVersion(string $type, string $key): ?ConfigVersionView;

    /** @return list<ConfigVersionView> active versions of every artefact of a type, ordered by key */
    public function activeVersions(string $type): array;

    /** A specific version, whatever its status (pinned versions stay readable after supersession, FR-PRD-006). */
    public function version(string $versionId): ?ConfigVersionView;
}
