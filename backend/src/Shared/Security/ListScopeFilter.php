<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Compiles a principal's scopes for a permission into a SQL WHERE, so a list
 * can never include a row the principal could not open (TRD §8.1, FR-RPT-010).
 */
interface ListScopeFilter
{
    /**
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query
     */
    public function apply(EloquentBuilder|QueryBuilder $query, Principal $principal, string $permission, ScopeColumns $columns): void;
}
