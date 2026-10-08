<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Totp;

// RFC 6238 Appendix B (SHA1 seed "12345678901234567890"), 8 digits there; we check the 6-digit truncation
// of the same HOTP values, and RFC 4226 Appendix D HOTP vectors directly.
it('matches the RFC 4226 HOTP test vectors', function (int $counter, string $expected) {
    expect(Totp::hotp('12345678901234567890', $counter))->toBe($expected);
})->with([[0, '755224'], [1, '287082'], [2, '359152'], [3, '969429'], [4, '338314'], [5, '254676'], [9, '520489']])->group('FR-SEC-013', 'LOS-FR-302');

it('matches the RFC 6238 TOTP test vectors (8 digits, SHA1)', function (int $time, string $expected) {
    expect(Totp::hotp('12345678901234567890', Totp::timeStep($time), 8))->toBe($expected);
})->with([[59, '94287082'], [1111111109, '07081804'], [1111111111, '14050471'], [1234567890, '89005924'], [2000000000, '69279037'], [20000000000, '65353130']])->group('FR-SEC-013');

it('round-trips RFC 4648 base32', function () {
    expect(Totp::base32Encode('foobar'))->toBe('MZXW6YTBOI======')
        ->and(Totp::base32Decode('MZXW6YTBOI======'))->toBe('foobar')
        ->and(Totp::base32Decode(Totp::base32Encode("\x00\xff\x10")))->toBe("\x00\xff\x10");
});

it('accepts ±1 step, rejects other steps and replays of a used step', function () {
    $secret = Totp::generateSecret();
    $t = 1_760_000_000;
    $step = Totp::timeStep($t);
    expect(Totp::verify($secret, Totp::codeAt($secret, $t), $t))->toBe($step)
        ->and(Totp::verify($secret, Totp::codeAt($secret, $t - 30), $t))->toBe($step - 1)
        ->and(Totp::verify($secret, Totp::codeAt($secret, $t + 90), $t))->toBeNull()
        ->and(Totp::verify($secret, Totp::codeAt($secret, $t), $t, lastUsedStep: $step))->toBeNull()
        ->and(Totp::verify($secret, 'abcdef', $t))->toBeNull();
})->group('FR-SEC-013', 'LOS-FR-302');

it('builds an otpauth provisioning URI', function () {
    expect(Totp::provisioningUri('JBSWY3DPEHPK3PXP', 'ada@bank.ng', 'Fundly LOS'))
        ->toBe('otpauth://totp/Fundly%20LOS:ada%40bank.ng?secret=JBSWY3DPEHPK3PXP&issuer=Fundly%20LOS&algorithm=SHA1&digits=6&period=30');
});
