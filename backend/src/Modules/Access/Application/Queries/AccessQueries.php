<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Queries;

use Fundly\Modules\Access\Application\ChangeRequests\ChangeRequestService;
use Fundly\Modules\Access\Application\SodChecker;
use Fundly\Modules\Access\Application\Support\RolePresenter;
use Fundly\Modules\Access\Application\Support\UserPresenter;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Access\Infrastructure\Models\ChangeRequest;
use Fundly\Modules\Access\Infrastructure\Models\Delegation;
use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Modules\Access\Infrastructure\Models\RoleAssignment;
use Fundly\Modules\Access\Infrastructure\Models\SodRuleRecord;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Http\CursorPaginator;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\ListScopeFilter;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ScopeColumns;
use Illuminate\Http\Request;

/** Read side of the Access module. Controllers read only through here. */
final class AccessQueries
{
    public function __construct(
        private readonly ListScopeFilter $scope,
        private readonly AuthorizationGate $gate,
        private readonly SodChecker $sod,
    ) {
    }

    /** @return array<string, mixed> */
    public function users(Request $request, Principal $principal): array
    {
        $q = User::query();
        $this->scope->apply($q, $principal, Permission::UserRead->value, new ScopeColumns(legalEntity: 'home_legal_entity_id', orgUnit: 'home_org_unit_id'));
        foreach (['status', 'kind'] as $f) {
            $v = $request->query('filter')[$f] ?? null;
            if (is_string($v)) {
                $q->where($f, $v);
            }
        }

        return CursorPaginator::paginate($q, $request, static fn (User $u): array => UserPresenter::present($u));
    }

    /** @return array{data: array<string, mixed>, etag: string} */
    public function user(string $id, Principal $principal): array
    {
        $user = User::query()->find($id);
        if (! $user instanceof User) {
            throw new NotFound('User not found.');
        }
        $this->gate->authorize($principal, Permission::UserRead->value, self::userAttributes($user));

        return ['data' => UserPresenter::present($user), 'etag' => UserPresenter::etag($user)];
    }

    public static function userAttributes(User $u): ResourceAttributes
    {
        return new ResourceAttributes(legalEntityId: $u->home_legal_entity_id, orgUnitId: $u->home_org_unit_id, entityType: 'user', entityId: $u->id);
    }

    /** @return array<string, mixed> */
    public function roles(Request $request): array
    {
        $q = Role::query();
        $template = $request->query('filter')['is_template'] ?? null;
        if (is_string($template)) {
            $q->where('is_template', filter_var($template, FILTER_VALIDATE_BOOLEAN));
        }

        return CursorPaginator::paginate($q, $request, static fn (Role $r): array => RolePresenter::present($r));
    }

    /** @return array{data: array<string, mixed>, etag: string} */
    public function role(string $id): array
    {
        $role = Role::query()->find($id) ?? throw new NotFound('Role not found.');

        return ['data' => RolePresenter::present($role), 'etag' => RolePresenter::etag($role)];
    }

    /** @return list<array<string, mixed>> */
    public function permissions(): array
    {
        return array_map(static fn (Permission $p): array => [
            'code' => $p->value,
            'resource' => $p->resource(),
            'action' => $p->actionName(),
            'module' => $p->module(),
            'description' => $p->description(),
            'is_sensitive' => $p->isSensitive(),
        ], Permission::cases());
    }

    /** @return array<string, mixed> */
    public function assignments(Request $request, Principal $principal): array
    {
        $q = RoleAssignment::query()->join('users', 'users.id', '=', 'role_assignments.user_id')->select('role_assignments.*');
        $this->scope->apply($q, $principal, Permission::RoleAssignmentRead->value, new ScopeColumns(legalEntity: 'users.home_legal_entity_id', orgUnit: 'users.home_org_unit_id'));
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];
        if (isset($filter['user_id']) && is_string($filter['user_id'])) {
            $q->where('role_assignments.user_id', $filter['user_id']);
        }
        if (($filter['active'] ?? null) === 'true') {
            $q->whereNull('role_assignments.revoked_at');
        }

        return CursorPaginator::paginate($q, $request, static fn (RoleAssignment $a): array => self::presentAssignment($a), 'role_assignments.id');
    }

    /** @return array<string, mixed> */
    public static function presentAssignment(RoleAssignment $a): array
    {
        return [
            'id' => $a->id,
            'user_id' => $a->user_id,
            'role_id' => $a->role_id,
            'scope' => $a->scope,
            'valid_from' => $a->valid_from->toIso8601ZuluString('microsecond'),
            'valid_to' => $a->valid_to?->toIso8601ZuluString('microsecond'),
            'granted_by' => $a->granted_by,
            'change_request_id' => $a->change_request_id,
            'revoked_at' => $a->revoked_at?->toIso8601ZuluString('microsecond'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function sodRules(): array
    {
        return SodRuleRecord::query()->orderBy('id')->get()->map(static fn (SodRuleRecord $r): array => self::presentSodRule($r))->values()->all();
    }

    /** @return array<string, mixed> */
    public static function presentSodRule(SodRuleRecord $r): array
    {
        return ['id' => $r->id, 'kind' => $r->kind, 'left' => $r->left_ref, 'right' => $r->right_ref, 'description' => $r->description, 'enabled' => $r->enabled];
    }

    /** @return list<array<string, mixed>> */
    public function sodConflicts(): array
    {
        return $this->sod->standingConflicts();
    }

    /** @return array<string, mixed> */
    public function delegations(Request $request, Principal $principal): array
    {
        $q = Delegation::query()->where(fn ($w) => $w->where('delegator_id', $principal->id)->orWhere('delegate_id', $principal->id));

        return CursorPaginator::paginate($q, $request, static fn (Delegation $d): array => self::presentDelegation($d));
    }

    /** @return array<string, mixed> */
    public static function presentDelegation(Delegation $d): array
    {
        return [
            'id' => $d->id,
            'delegator_id' => $d->delegator_id,
            'delegate_id' => $d->delegate_id,
            'role_assignment_id' => $d->role_assignment_id,
            'reason' => $d->reason,
            'valid_from' => $d->valid_from->toIso8601ZuluString('microsecond'),
            'valid_to' => $d->valid_to->toIso8601ZuluString('microsecond'),
            'revoked_at' => $d->revoked_at?->toIso8601ZuluString('microsecond'),
        ];
    }

    /** @return array<string, mixed> */
    public function changeRequests(Request $request): array
    {
        $q = ChangeRequest::query();
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];
        foreach (['status', 'action_type', 'maker_id'] as $f) {
            if (isset($filter[$f]) && is_string($filter[$f])) {
                $q->where($f, $filter[$f]);
            }
        }

        return CursorPaginator::paginate($q, $request, static fn (ChangeRequest $c): array => ChangeRequestService::present($c));
    }

    /** @return array<string, mixed> */
    public function changeRequest(string $id): array
    {
        $cr = ChangeRequest::query()->find($id) ?? throw new NotFound('Change request not found.');

        return ChangeRequestService::present($cr);
    }

    /** Permission needed to decide (approve/reject: checker permission; cancel: any). */
    public function changeRequestCheckerPermission(string $id): string
    {
        $cr = ChangeRequest::query()->find($id) ?? throw new NotFound('Change request not found.');

        return $cr->required_checker_permission;
    }

    public function changeRequestMakerPermission(string $id, \Fundly\Modules\Access\Application\ChangeRequests\ChangeActionRegistry $registry): string
    {
        $cr = ChangeRequest::query()->find($id) ?? throw new NotFound('Change request not found.');

        return $registry->get($cr->action_type)->makerPermission();
    }
}
