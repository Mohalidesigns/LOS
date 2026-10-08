<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Users;

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;
use Illuminate\Validation\Rules\Password;

/** Users start with no roles (deny by default); access is granted by maker-checker assignment. */
#[HandledBy(CreateUserHandler::class)]
final readonly class CreateUser implements Command, ValidatesInput
{
    public function __construct(
        public string $kind,
        public string $email,
        public string $name,
        public ?string $password,
        public ?string $homeLegalEntityId,
        public ?string $homeOrgUnitId,
    ) {}

    public function action(): string
    {
        return 'access.user.created';
    }

    public function permission(): string
    {
        return Permission::UserManage->value;
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }

    public function data(): array
    {
        return [
            'kind' => $this->kind, 'email' => $this->email, 'name' => $this->name, 'password' => $this->password,
            'home_legal_entity_id' => $this->homeLegalEntityId, 'home_org_unit_id' => $this->homeOrgUnitId,
        ];
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:human,service'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'name' => ['required', 'string', 'max:200'],
            'password' => $this->kind === 'human'
                ? ['required', 'string', Password::min((int) config('fundly.auth.password.min_length', 12))->mixedCase()->numbers()->symbols()]
                : ['prohibited'],
            'home_legal_entity_id' => ['nullable', 'uuid'],
            'home_org_unit_id' => ['nullable', 'uuid'],
        ];
    }
}
