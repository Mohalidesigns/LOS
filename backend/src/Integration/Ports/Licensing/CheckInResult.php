<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Licensing;

final readonly class CheckInResult
{
    public function __construct(public bool $supported, public bool $ok, public string $message)
    {
    }
}
