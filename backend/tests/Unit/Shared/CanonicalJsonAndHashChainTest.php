<?php

declare(strict_types=1);

use Fundly\Shared\Audit\HashChain;
use Fundly\Shared\Json\CanonicalJson;

it('encodes equal data identically regardless of key order', function () {
    $a = ['b' => 1, 'a' => ['y' => true, 'x' => [3, 2, 1]], 'c' => 'ŋ/ü'];
    $b = ['c' => 'ŋ/ü', 'a' => ['x' => [3, 2, 1], 'y' => true], 'b' => 1];
    expect(CanonicalJson::encode($a))->toBe(CanonicalJson::encode($b))
        ->and(CanonicalJson::encode($a))->toBe('{"a":{"x":[3,2,1],"y":true},"b":1,"c":"ŋ/ü"}');
})->group('FR-AUD-004');

it('chains hashes over the previous hash and the canonical row', function () {
    $row = ['id' => 'e1', 'tenant_id' => 't', 'seq' => 1, 'action' => 'x', 'prev_hash' => HashChain::GENESIS];
    $h1 = HashChain::compute($row);
    expect($h1)->toMatch('/^[0-9a-f]{64}$/')
        ->and(HashChain::compute($row))->toBe($h1)
        ->and(HashChain::compute(['action' => 'y'] + $row))->not->toBe($h1)
        ->and(HashChain::compute(['prev_hash' => str_repeat('1', 64)] + $row))->not->toBe($h1);
})->group('FR-AUD-004');
