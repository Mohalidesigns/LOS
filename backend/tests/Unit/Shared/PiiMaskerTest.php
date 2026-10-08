<?php

declare(strict_types=1);

use Fundly\Shared\Pii\PiiMasker;

it('masks PII and redacts secrets by default, recursively', function () {
    $m = new PiiMasker(['bvn', 'email', 'account_no', 'phone'], ['password', 'mfa_secret', 'token']);
    $out = $m->mask([
        'BVN' => '22345678991',
        'email' => 'ada.obi@bank.ng',
        'nested' => ['account_no' => '0123456789', 'phone' => '+2348031234567', 'name' => 'Ada'],
        'password' => 'hunter2',
        'mfa_secret' => 'ABCDEF',
        'token' => null,
        'amount' => '1000.0000',
    ]);
    expect($out['BVN'])->toBe('2234*****91')
        ->and($out['email'])->toBe('a******@bank.ng')
        ->and($out['nested']['account_no'])->toBe('0123****89')
        ->and($out['nested']['phone'])->toBe('+234********67')
        ->and($out['nested']['name'])->toBe('Ada')
        ->and($out['password'])->toBe(PiiMasker::REDACTED)
        ->and($out['mfa_secret'])->toBe(PiiMasker::REDACTED)
        ->and($out['token'])->toBeNull()
        ->and($out['amount'])->toBe('1000.0000');
})->group('FR-CMP-036', 'FR-AUD-002', 'FR-CBA-016');
