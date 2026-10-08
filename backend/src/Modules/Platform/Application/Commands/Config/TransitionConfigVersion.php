<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\Config;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Lifecycle transitions that do not activate: submit (author), approve / reject (reviewer). */
#[HandledBy(TransitionConfigVersionHandler::class)]
final readonly class TransitionConfigVersion implements Command, ValidatesInput
{
    public const SUBMIT = 'submit';

    public const APPROVE = 'approve';

    public const REJECT = 'reject';

    public function __construct(public string $versionId, public string $transition, public ?string $reason)
    {
    }

    public function action(): string
    {
        return 'platform.config_version.'.match ($this->transition) {
            self::SUBMIT => 'submitted',
            self::APPROVE => 'approved',
            default => 'rejected',
        };
    }

    public function permission(): string
    {
        return $this->transition === self::SUBMIT ? 'config:author' : 'config:review';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('config_version', $this->versionId);
    }

    public function data(): array
    {
        return ['transition' => $this->transition, 'reason' => $this->reason];
    }

    public function rules(): array
    {
        return [
            'transition' => ['required', 'in:submit,approve,reject'],
            'reason' => [$this->transition === self::REJECT ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }
}
