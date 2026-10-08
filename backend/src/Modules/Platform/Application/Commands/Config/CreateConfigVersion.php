<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\Config;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(CreateConfigVersionHandler::class)]
final readonly class CreateConfigVersion implements Command, ValidatesInput
{
    /** @param array<string, mixed> $content */
    public function __construct(public string $artifactId, public array $content, public ?string $notes) {}

    public function action(): string
    {
        return 'platform.config_version.drafted';
    }

    public function permission(): string
    {
        return 'config:author';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('config_artifact', $this->artifactId);
    }

    public function data(): array
    {
        return ['content' => $this->content, 'notes' => $this->notes];
    }

    public function rules(): array
    {
        return ['content' => ['present', 'array'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
