<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\Config;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(CreateConfigArtifactHandler::class)]
final readonly class CreateConfigArtifact implements Command, ValidatesInput
{
    public function __construct(public string $type, public string $key, public string $name, public ?string $description)
    {
    }

    public function action(): string
    {
        return 'platform.config_artifact.created';
    }

    public function permission(): string
    {
        return 'config:author';
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }

    public function data(): array
    {
        return ['key' => $this->key, 'name' => $this->name, 'description' => $this->description];
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9_.-]{0,95}$/'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
