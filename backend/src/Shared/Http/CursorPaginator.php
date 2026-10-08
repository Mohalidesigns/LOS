<?php

declare(strict_types=1);

namespace Fundly\Shared\Http;

use Fundly\Shared\Exceptions\ValidationFailed;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * Keyset pagination over time-ordered UUIDv7 ids (TRD §10.1):
 * `page[size]` (≤ 100) and `page[after]` (opaque cursor).
 */
final class CursorPaginator
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query
     * @param  callable(mixed): array<string, mixed>  $transform
     * @param  'asc'|'desc'  $direction
     * @return array{data: list<array<string, mixed>>, meta: array{page: array{size: int, next_cursor: ?string, has_more: bool}}}
     */
    public static function paginate(EloquentBuilder|QueryBuilder $query, Request $request, callable $transform, string $column = 'id', string $direction = 'desc'): array
    {
        $page = $request->query('page');
        $page = is_array($page) ? $page : [];
        $max = (int) config('fundly.api.page_size_max', 100);
        $size = isset($page['size']) && is_numeric($page['size']) ? (int) $page['size'] : (int) config('fundly.api.page_size_default', 25);
        if ($size < 1 || $size > $max) {
            throw ValidationFailed::with(['page[size]' => "page[size] must be between 1 and {$max}."]);
        }

        if (isset($page['after']) && is_string($page['after']) && $page['after'] !== '') {
            $after = self::decode($page['after']);
            $query->where($column, $direction === 'desc' ? '<' : '>', $after);
        }

        $rows = $query->orderBy($column, $direction)->limit($size + 1)->get()->all();
        $hasMore = count($rows) > $size;
        $rows = array_slice($rows, 0, $size);
        $last = end($rows);
        $next = null;
        if ($hasMore && $last !== false) {
            $value = is_object($last) ? data_get($last, $column) : null;
            $next = is_scalar($value) ? self::encode((string) $value) : null;
        }

        return [
            'data' => array_values(array_map($transform, $rows)),
            'meta' => ['page' => ['size' => $size, 'next_cursor' => $next, 'has_more' => $hasMore]],
        ];
    }

    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode(json_encode(['k' => $value], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    public static function decode(string $cursor): string
    {
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($data) || ! isset($data['k']) || ! is_string($data['k'])) {
            throw ValidationFailed::with(['page[after]' => 'Invalid cursor.']);
        }

        return $data['k'];
    }
}
