<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Contracts;

use Fundly\Shared\Exceptions\ProblemException;

/** The change would give someone mutually exclusive access (FR-SEC-006). */
final class SodConflict extends ProblemException
{
    /** @param list<array{rule_id: string, kind: string, left: string, right: string, description: string, user_id?: string}> $conflicts */
    public function __construct(array $conflicts, string $detail = 'This change would create a segregation-of-duties conflict.')
    {
        parent::__construct($detail, ['conflicts' => $conflicts]);
    }

    public function status(): int
    {
        return 409;
    }

    public function type(): string
    {
        return 'sod-conflict';
    }

    public function title(): string
    {
        return 'Segregation of duties conflict';
    }
}
