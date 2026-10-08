<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application;

use Fundly\Modules\Access\Domain\Grant;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Shared\Audit\Actor;
use Fundly\Shared\Audit\ActorProvider;
use Fundly\Shared\Audit\ActorType;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Security\CurrentPrincipal;
use Fundly\Shared\Security\PrincipalKind;

/**
 * Snapshot of the acting principal for the audit trail: role codes and a hash
 * of the effective permission set at the time (FR-AUD-002).
 */
final class PrincipalActorProvider implements ActorProvider
{
    public function __construct(
        private readonly CurrentPrincipal $principal,
        private readonly GrantRepository $grants,
        private readonly Clock $clock,
    ) {}

    public function current(): Actor
    {
        $p = $this->principal->get();
        if ($p === null) {
            return Actor::anonymous();
        }
        if ($p->kind === PrincipalKind::System) {
            return new Actor(ActorType::System, $p->id, [$p->id]);
        }

        $now = $this->clock->now();
        $roles = [];
        $perms = [];
        $onBehalf = null;
        foreach ($this->grants->forUser($p->id) as $g) {
            if (! $g->isActiveAt($now)) {
                continue;
            }
            $roles[$g->roleCode] = true;
            foreach ($g->permissions as $perm) {
                if ($p->tokenAllows($perm)) {
                    $perms[$perm] = true;
                }
            }
        }
        ksort($perms);
        ksort($roles);

        return new Actor(
            $p->kind === PrincipalKind::Service ? ActorType::ServiceAccount : ActorType::User,
            $p->id,
            array_keys($roles),
            hash('sha256', implode(',', array_keys($perms))),
            $onBehalf,
        );
    }

    /** @param list<Grant> $grants */
    public static function permissionsHash(array $grants): string
    {
        $perms = [];
        foreach ($grants as $g) {
            foreach ($g->permissions as $perm) {
                $perms[$perm] = true;
            }
        }
        ksort($perms);

        return hash('sha256', implode(',', array_keys($perms)));
    }
}
