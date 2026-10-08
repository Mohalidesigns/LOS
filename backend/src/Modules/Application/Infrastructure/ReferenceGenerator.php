<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Infrastructure;

use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Human-readable application references (FR-APP-001), default format
 * `{LE}-{YYYY}-{SEQ:6}`. The counter row is incremented inside the caller's
 * transaction, so a rolled-back creation releases its number: gap-free.
 */
final class ReferenceGenerator
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function next(string $legalEntityCode, int $year, string $format = '{LE}-{YYYY}-{SEQ:6}'): string
    {
        $scope = $legalEntityCode.':'.$year;
        $row = DB::selectOne(
            'insert into application_sequences (tenant_id, scope_key, last_value) values (?, ?, 1)
             on conflict (tenant_id, scope_key) do update set last_value = application_sequences.last_value + 1
             returning last_value',
            [$this->tenant->requireId(), $scope],
        );
        $seq = (int) ($row->last_value ?? 0);

        return (string) preg_replace_callback('/\{(LE|YYYY|SEQ:(\d+))\}/', static fn (array $m): string => match (true) {
            $m[1] === 'LE' => $legalEntityCode,
            $m[1] === 'YYYY' => (string) $year,
            default => str_pad((string) $seq, (int) ($m[2] ?? 6), '0', STR_PAD_LEFT),
        }, $format);
    }
}
