<?php

declare(strict_types=1);

use Fundly\Shared\Id\UuidV7;

it('generates RFC 9562 version 7 ids that sort by time', function () {
    $a = UuidV7::generate(new DateTimeImmutable('2026-10-08T09:00:00.001Z'));
    $b = UuidV7::generate(new DateTimeImmutable('2026-10-08T09:00:00.002Z'));
    expect($a)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and(strcmp($a, $b))->toBeLessThan(0)
        ->and(UuidV7::timestampMs($a))->toBe((int) (new DateTimeImmutable('2026-10-08T09:00:00.001Z'))->format('Uv'));
});
