<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Domain;

final class ChecklistSummary
{
    /**
     * @param  list<array{name: string, mandatory: bool, status: string}>  $items
     * @return array{mandatory_total: int, mandatory_satisfied: int, outstanding: list<string>, complete: bool}
     */
    public static function of(array $items): array
    {
        $total = 0;
        $done = 0;
        $outstanding = [];
        foreach ($items as $i) {
            if (! $i['mandatory']) {
                continue;
            }
            $total++;
            if (ChecklistStatus::from($i['status'])->satisfies()) {
                $done++;
            } else {
                $outstanding[] = $i['name'];
            }
        }

        return ['mandatory_total' => $total, 'mandatory_satisfied' => $done, 'outstanding' => $outstanding, 'complete' => $total === $done];
    }
}
