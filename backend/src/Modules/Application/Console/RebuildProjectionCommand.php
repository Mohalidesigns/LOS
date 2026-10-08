<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Console;

use Brick\Math\BigDecimal;
use Fundly\Modules\Application\Domain\Application;
use Fundly\Modules\Application\Infrastructure\ApplicationStore;
use Fundly\Modules\Application\Infrastructure\Models\ApplicationRecord;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Verifies the `applications` projection against the event store (TRD §5.2:
 * the projection is rebuildable). Reports drift; --fix rewrites the derived
 * columns from a replay.
 */
final class RebuildProjectionCommand extends Command
{
    protected $signature = 'applications:verify-projection {--fix : Rewrite drifted rows from the event stream}';

    protected $description = 'Replay application events and compare with (or rebuild) the applications projection.';

    public function handle(TenantDirectory $tenants, TenantContext $tenant, ApplicationStore $store): int
    {
        $drift = 0;
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $tenant->run($tenantId, function () use ($store, &$drift): void {
                foreach (ApplicationRecord::query()->cursor() as $record) {
                    $state = Application::replay($record->id, $store->events($record->id))->snapshot();
                    if ($state['status'] !== $record->canonical_status || $state['version'] !== $record->version || ! self::sameAmount($state['requested_amount'], $record->requested_amount)) {
                        $drift++;
                        $this->warn("Drift on {$record->reference}: projection {$record->canonical_status} v{$record->version}, events {$state['status']} v{$state['version']}");
                        if ($this->option('fix')) {
                            $record->forceFill(['canonical_status' => $state['status'], 'version' => $state['version'], 'requested_amount' => $state['requested_amount'], 'tenor_months' => $state['tenor_months'], 'purpose' => $state['purpose']])->save();
                        }
                    }
                }
            });
        }
        $this->info($drift === 0 ? 'Projection matches the event store.' : "{$drift} drifted row(s).");

        return $drift === 0 || $this->option('fix') ? self::SUCCESS : self::FAILURE;
    }

    private static function sameAmount(mixed $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return is_string($a) && BigDecimal::of($a)->isEqualTo(BigDecimal::of($b));
    }
}
