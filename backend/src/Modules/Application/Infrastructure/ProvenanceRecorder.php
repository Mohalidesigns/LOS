<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Infrastructure;

use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/** Per-field data provenance (LOS-FR-301, TRD §5.2): who or what supplied each value, at which version. */
final class ProvenanceRecorder
{
    public const SOURCES = ['cba', 'extraction', 'self_service', 'staff', 'partner'];

    public function __construct(private readonly Clock $clock, private readonly TenantContext $tenant) {}

    /** @param list<string> $paths */
    public function record(string $applicationId, array $paths, int $version, string $source, ?string $sourceRef, string $recordedBy): void
    {
        $now = $this->clock->now();
        $rows = [];
        foreach (array_unique($paths) as $path) {
            $rows[] = [
                'id' => UuidV7::generate(),
                'tenant_id' => $this->tenant->requireId(),
                'application_id' => $applicationId,
                'field_path' => $path,
                'version' => $version,
                'source' => $source,
                'source_ref' => $sourceRef,
                'recorded_by' => $recordedBy,
                'recorded_at' => $now->format('Y-m-d H:i:s.uP'),
            ];
        }
        if ($rows !== []) {
            DB::table('field_provenance')->insert($rows);
        }
    }
}
