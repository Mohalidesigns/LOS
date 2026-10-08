<?php

declare(strict_types=1);

namespace Fundly\Shared\Database;

use stdClass;

/**
 * Query-builder rows are stdClass at runtime but typed `object` by the
 * framework. These helpers narrow with a real runtime check so column access
 * is type-safe.
 */
final class Row
{
    public static function one(mixed $row): ?stdClass
    {
        return $row instanceof stdClass ? $row : null;
    }

    /**
     * @param  iterable<mixed>  $rows
     * @return list<stdClass>
     */
    public static function all(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if ($row instanceof stdClass) {
                $out[] = $row;
            }
        }

        return $out;
    }
}
