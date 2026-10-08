<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\KeyManagement;

final readonly class WrappedKey
{
    public function __construct(public string $kekId, public string $ciphertext)
    {
    }
}
