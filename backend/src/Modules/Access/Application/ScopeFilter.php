<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application;

use Fundly\Modules\Access\Domain\Grant;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Security\ListScopeFilter;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ScopeColumns;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * OR over the principal's active grants that hold the permission; AND over the
 * scope dimensions of each grant that the queried resource carries. This is
 * the same rule as AccessPolicy::inScope, expressed in SQL.
 */
final class ScopeFilter implements ListScopeFilter
{
    public function __construct(private readonly GrantRepository $grants, private readonly Clock $clock) {}

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query
     */
    public function apply(EloquentBuilder|QueryBuilder $query, Principal $principal, string $permission, ScopeColumns $columns): void
    {
        if ($principal->isSystem()) {
            return;
        }
        if (! $principal->tokenAllows($permission)) {
            $query->whereRaw('false');

            return;
        }

        $now = $this->clock->now();
        $grants = array_values(array_filter(
            $this->grants->forUser($principal->id),
            static fn (Grant $g): bool => $g->isActiveAt($now) && $g->grants($permission),
        ));
        if ($grants === []) {
            $query->whereRaw('false');

            return;
        }
        foreach ($grants as $grant) {
            if (! self::constrains($grant, $columns)) {
                return; // at least one grant covers everything this resource type carries
            }
        }

        $query->where(function ($outer) use ($grants, $columns): void {
            foreach ($grants as $grant) {
                $outer->orWhere(function ($q) use ($grant, $columns): void {
                    $s = $grant->scope;
                    if ($columns->legalEntity !== null && $s->legalEntityIds !== []) {
                        $q->whereIn($columns->legalEntity, $s->legalEntityIds);
                    }
                    if ($columns->orgUnit !== null && $s->orgUnitId !== null) {
                        $q->whereIn($columns->orgUnit, $grant->orgSubtree ?? [$s->orgUnitId]);
                    }
                    if ($columns->product !== null && $s->productIds !== []) {
                        $q->whereIn($columns->product, $s->productIds);
                    }
                    if ($columns->currency !== null && $s->currencies !== []) {
                        $q->whereIn($columns->currency, $s->currencies);
                    }
                    if ($columns->amount !== null && $s->maxAmount !== null) {
                        $q->where($columns->amount, '<=', $s->maxAmount->toStorage());
                        if ($columns->currency !== null) {
                            $q->where($columns->currency, $s->maxAmount->currency->code);
                        }
                    }
                    if ($columns->segment !== null && $s->segments !== []) {
                        $q->whereIn($columns->segment, $s->segments);
                    }
                });
            }
        });
    }

    private static function constrains(Grant $grant, ScopeColumns $c): bool
    {
        $s = $grant->scope;

        return ($c->legalEntity !== null && $s->legalEntityIds !== [])
            || ($c->orgUnit !== null && $s->orgUnitId !== null)
            || ($c->product !== null && $s->productIds !== [])
            || ($c->currency !== null && $s->currencies !== [])
            || ($c->amount !== null && $s->maxAmount !== null)
            || ($c->segment !== null && $s->segments !== []);
    }
}
