<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Licensing;

use Fundly\Shared\Exceptions\ProblemException;

final class LicenceInvalid extends ProblemException
{
    public function __construct(string $detail, private readonly string $reason = 'licence-invalid')
    {
        parent::__construct($detail);
    }

    public function status(): int
    {
        return 422;
    }

    public function type(): string
    {
        return $this->reason;
    }

    public function title(): string
    {
        return 'Licence rejected';
    }
}
