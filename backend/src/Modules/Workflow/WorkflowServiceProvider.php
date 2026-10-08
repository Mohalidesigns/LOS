<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow;

use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Workflow\Application\IntakeAutomation;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

/** M10 Workflow (MVP slice): automated intake stages. Engine, queues and SLAs follow in P1-WFL-01..03. */
final class WorkflowServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $events): void
    {
        $events->listen(ApplicationStatusChanged::class, [IntakeAutomation::class, 'onStatusChanged']);
    }
}
