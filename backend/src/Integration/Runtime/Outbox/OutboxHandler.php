<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Outbox;

interface OutboxHandler
{
    public function topic(): string;

    /** @return array<string, mixed> result recorded on the message */
    public function handle(OutboxMessage $message): array;
}
