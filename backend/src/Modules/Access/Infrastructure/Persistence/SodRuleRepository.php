<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Infrastructure\Persistence;

use Fundly\Modules\Access\Domain\SodRule;
use Illuminate\Database\ConnectionInterface;

final class SodRuleRepository
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    /** @return list<SodRule> */
    public function enabled(): array
    {
        $out = [];
        foreach ($this->db->table('sod_rules')->where('enabled', true)->orderBy('id')->get() as $r) {
            $out[] = new SodRule((string) $r->id, (string) $r->kind, (string) $r->left_ref, (string) $r->right_ref, (string) $r->description);
        }

        return $out;
    }
}
