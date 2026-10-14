<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application;

use Fundly\Modules\Notification\Domain\DefaultTemplates;
use Fundly\Modules\Notification\Domain\NotificationTemplate;
use Fundly\Modules\Platform\Contracts\ActiveConfiguration;

/** The tenant's active template for an event (maker-checker activated), else the built-in default. */
final class TemplateResolver
{
    public const TYPE = 'notification.template';

    public function __construct(private readonly ActiveConfiguration $config) {}

    /** @return array{0: NotificationTemplate, 1: 'tenant'|'default', 2: ?string} template, source, version id */
    public function resolve(string $event): array
    {
        $version = $this->config->activeVersion(self::TYPE, $event);
        if ($version !== null && ($version->content['event'] ?? null) === $event) {
            return [NotificationTemplate::of($version->content), 'tenant', $version->id];
        }

        return [NotificationTemplate::of(DefaultTemplates::all()[$event]), 'default', null];
    }
}
