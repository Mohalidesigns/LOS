<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Compiles a principal's scopes for a permission into a SQL WHERE, so a list
 * can never include a row the principal could not open (TRD §8.1, FR-RPT-010).
 */
interface ListScopeFilter
{
    /** @param EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder $query */
    public function apply(EloquentBuilder|QueryBuilder $query, Principal $principal, string $permission, ScopeColumns $columns): void;
}
