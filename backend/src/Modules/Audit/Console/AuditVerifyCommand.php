<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Console;

use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Audit\AuditVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** php artisan audit:verify [--tenant=] [--from=] [--to=]  (FR-AUD-004, TRD §5.4). Exit 1 on any break. */
final class AuditVerifyCommand extends Command
{
    protected $signature = 'audit:verify {--tenant= : Verify one tenant only} {--from= : First seq} {--to= : Last seq} {--json : Machine-readable output}';

    protected $description = 'Recompute the audit hash chain and compare it with stored hashes and checkpoints.';

    public function handle(AuditVerifier $verifier, TenantDirectory $tenants): int
    {
        $tenantOpt = $this->option('tenant');
        $ids = is_string($tenantOpt) && $tenantOpt !== '' ? [$tenantOpt] : $tenants->activeTenantIds();
        $from = is_numeric($this->option('from')) ? (int) $this->option('from') : null;
        $to = is_numeric($this->option('to')) ? (int) $this->option('to') : null;

        $failed = false;
        $results = [];
        foreach ($ids as $id) {
            $r = $verifier->verify($id, $from, $to);
            $results[] = $r->toArray();
            if (! $r->ok()) {
                $failed = true;
                // P1 alert hook (TRD §12.2): an audit chain break is a critical incident.
                Log::critical('audit.chain.broken', ['tenant_id' => $id, 'breaks' => $r->breaks()]);
            }
            if (! $this->option('json')) {
                $this->line(sprintf('%s tenant %s: %d events, %d checkpoints, head seq %d', $r->ok() ? 'OK  ' : 'FAIL', $id, $r->eventsChecked, $r->checkpointsChecked, $r->headSeq));
                foreach ($r->breaks() as $b) {
                    $this->error(sprintf('  seq %d [%s] %s', $b['seq'], $b['kind'], $b['detail']));
                }
            }
        }
        if ($this->option('json')) {
            $this->line((string) json_encode($results, JSON_PRETTY_PRINT));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
