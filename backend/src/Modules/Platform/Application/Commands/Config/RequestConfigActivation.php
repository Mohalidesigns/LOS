<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\Config;

use Fundly\Modules\Platform\Application\Config\ActivateConfigVersionAction;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/**
 * Activation (and rollback, which re-activates an earlier version) only
 * happens through maker-checker (FR-TEN-009, TRD §6.4).
 */
#[HandledBy(RequestConfigActivationHandler::class)]
final readonly class RequestConfigActivation implements Command
{
    public function __construct(public string $versionId, public bool $rollback, public ?string $reason)
    {
    }

    public function action(): string
    {
        return $this->rollback ? 'platform.config_version.rollback_requested' : 'platform.config_version.activation_requested';
    }

    public function permission(): string
    {
        return 'config:activate_request';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('config_version', $this->versionId);
    }

    public function actionType(): string
    {
        return ActivateConfigVersionAction::TYPE;
    }
}
