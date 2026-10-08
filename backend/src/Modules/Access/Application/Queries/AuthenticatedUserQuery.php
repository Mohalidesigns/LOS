<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Queries;

use Fundly\Modules\Access\Application\Support\UserPresenter;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\Principal;

final class AuthenticatedUserQuery
{
    /** @return array<string, mixed> */
    public function profile(string $userId): array
    {
        $user = User::query()->find($userId) ?? throw new NotFound('User not found.');
        $roles = $user->assignments()->join('roles', 'roles.id', '=', 'role_assignments.role_id')
            ->whereNull('role_assignments.revoked_at')->orderBy('roles.code')->pluck('roles.code')->unique()->values()->all();

        return UserPresenter::present($user) + ['tenant_id' => $user->tenant_id, 'roles' => $roles];
    }

    /** The staff user behind a session principal (step-up/logout are session operations). */
    public function sessionUser(Principal $principal): User
    {
        if ($principal->viaToken()) {
            throw new DomainRuleViolation('This operation is only available to interactive staff sessions.');
        }

        return User::query()->find($principal->id) ?? throw new NotFound('User not found.');
    }
}
