<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Console;

use Fundly\Modules\Audit\Application\AuditCheckpointer;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Illuminate\Console\Command;

/** php artisan audit:checkpoint — scheduled every 5 minutes (TRD §5.4). */
final class AuditCheckpointCommand extends Command
{
    protected $signature = 'audit:checkpoint';

    protected $description = 'Anchor the head hash of every tenant audit chain.';

    public function handle(AuditCheckpointer $checkpointer, TenantDirectory $tenants): int
    {
        foreach ($tenants->activeTenantIds() as $id) {
            $cp = $checkpointer->checkpoint($id);
            $this->line($cp === null ? "tenant {$id}: unchanged" : "tenant {$id}: anchored seq {$cp['seq']} {$cp['hash']}");
        }

        return self::SUCCESS;
    }
}
