<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification;

use Fundly\Integration\Runtime\Outbox\OutboxHandlerRegistry;
use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Audit\Contracts\EvidenceSources;
use Fundly\Modules\Compliance\Contracts\Events\ApplicationScreened;
use Fundly\Modules\Disbursement\Contracts\Events\DisbursementAwaitingRelease;
use Fundly\Modules\Disbursement\Contracts\Events\FacilityBooked;
use Fundly\Modules\Notification\Application\Commands\NotifyHandler;
use Fundly\Modules\Notification\Application\NotificationQueries;
use Fundly\Modules\Notification\Application\NotificationTriggers;
use Fundly\Modules\Notification\Application\TemplateResolver;
use Fundly\Modules\Notification\Console\CheckDeliveryCommand;
use Fundly\Modules\Notification\Console\SendRemindersCommand;
use Fundly\Modules\Notification\Domain\NotificationTemplate;
use Fundly\Modules\Notification\Infrastructure\DeliverEmailOutboxHandler;
use Fundly\Modules\Notification\Infrastructure\DeliverSmsOutboxHandler;
use Fundly\Modules\Notification\Infrastructure\DeliverWhatsAppOutboxHandler;
use Fundly\Modules\Platform\Contracts\ConfigTypeCatalogue;
use Fundly\Modules\Workflow\Contracts\Events\NoteMentioned;
use Fundly\Modules\Workflow\Contracts\Events\TaskAssigned;
use Fundly\Modules\Workflow\Contracts\Events\TaskSlaChanged;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

/** M16 Notifications: email, SMS and WhatsApp (integration ports) and in-app (webhooks via the integration layer), templates with maker-checker, communication log (FR-NTF-001/002/005/006). */
final class NotificationServiceProvider extends ServiceProvider
{
    public function boot(ConfigTypeCatalogue $types, Dispatcher $events, OutboxHandlerRegistry $outbox, EvidenceSources $evidence): void
    {
        // evidence pack sections (FR-AUD-010)
        $evidence->register('communications', NotificationQueries::class, 'forApplication');
        $types->register(
            TemplateResolver::TYPE,
            'Notification template for one event (key = event code): audience, and per language the email subject/body or in-app title/body with {{ placeholders }} (FR-NTF-002).',
            static function (array $content): void {
                NotificationTemplate::validate($content);
            },
        );
        $outbox->register(NotifyHandler::EMAIL_TOPIC, DeliverEmailOutboxHandler::class);
        $outbox->register(NotifyHandler::SMS_TOPIC, DeliverSmsOutboxHandler::class);
        $outbox->register(NotifyHandler::WHATSAPP_TOPIC, DeliverWhatsAppOutboxHandler::class);
        $events->listen(ApplicationStatusChanged::class, [NotificationTriggers::class, 'onStatusChanged']);
        $events->listen(ApplicationScreened::class, [NotificationTriggers::class, 'onScreened']);
        $events->listen(DisbursementAwaitingRelease::class, [NotificationTriggers::class, 'onAwaitingRelease']);
        $events->listen(FacilityBooked::class, [NotificationTriggers::class, 'onBooked']);
        $events->listen(TaskAssigned::class, [NotificationTriggers::class, 'onTaskAssigned']);
        $events->listen(TaskSlaChanged::class, [NotificationTriggers::class, 'onTaskSla']);
        $events->listen(NoteMentioned::class, [NotificationTriggers::class, 'onNoteMentioned']);
        if ($this->app->runningInConsole()) {
            $this->commands([SendRemindersCommand::class, CheckDeliveryCommand::class]);
        }
    }
}
