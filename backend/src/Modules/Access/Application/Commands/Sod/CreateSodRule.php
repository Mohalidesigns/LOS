<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Sod;

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(CreateSodRuleHandler::class)]
final readonly class CreateSodRule implements Command, ValidatesInput
{
    public function __construct(public string $kind, public string $left, public string $right, public string $description) {}

    public function action(): string
    {
        return 'access.sod_rule.created';
    }

    public function permission(): string
    {
        return Permission::SodRuleManage->value;
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }

    public function data(): array
    {
        return ['kind' => $this->kind, 'left' => $this->left, 'right' => $this->right, 'description' => $this->description];
    }

    public function rules(): array
    {
        $ref = $this->kind === 'permission_pair' ? ['required', 'string', 'in:'.implode(',', Permission::codes())] : ['required', 'uuid'];

        return [
            'kind' => ['required', 'in:permission_pair,role_pair'],
            'left' => $ref,
            'right' => array_merge($ref, ['different:left']),
            'description' => ['required', 'string', 'max:500'],
        ];
    }
}
