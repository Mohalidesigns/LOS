<?php

declare(strict_types=1);

use Fundly\Integration\Adapters\LocalKeyfile\LocalKeyfileKms;
use Fundly\Integration\Ports\KeyManagement\KeyManagementFailure;
use Fundly\Integration\Ports\KeyManagement\KeyManagementPort;
use Fundly\Shared\Crypto\FieldEncryptor;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->a = $this->provisionTenant('enc-a');
    $this->b = $this->provisionTenant('enc-b');
});

it('encrypts fields under per-tenant data keys that are stored only wrapped by the KEK', function () {
    $this->useTenant($this->a);
    $enc = app(FieldEncryptor::class);
    $ct = $enc->encrypt('22345678991', 'party.bvn');
    expect($ct)->toStartWith('fe1.1.')->not->toContain('22345678991')
        ->and($enc->decrypt($ct, 'party.bvn'))->toBe('22345678991')
        ->and($enc->encrypt('22345678991', 'party.bvn'))->not->toBe($ct); // random nonce

    $key = DB::table('tenant_keys')->where('purpose', 'pii_dek')->first();
    expect($key->kek_id)->toBe(config('fundly.crypto.kek_id'))->and(strlen(base64_decode($key->wrapped_key)))->toBeGreaterThan(32);

    // tenant B has a different key: A's ciphertext does not open under B's context
    $this->useTenant($this->b);
    expect(fn () => app(FieldEncryptor::class)->decrypt($ct, 'party.bvn'))->toThrow(RuntimeException::class);
})->group('FR-SEC-016', 'FR-SEC-017');

it('binds ciphertext to its field (no swapping values between columns)', function () {
    $this->useTenant($this->a);
    $enc = app(FieldEncryptor::class);
    $ct = $enc->encrypt('0123456789', 'party.account_no');
    expect(fn () => $enc->decrypt($ct, 'party.bvn'))->toThrow(RuntimeException::class);
})->group('FR-SEC-017');

it('provides a deterministic per-tenant blind index for exact-match search', function () {
    $this->useTenant($this->a);
    $a1 = app(FieldEncryptor::class)->blindIndex('2234 5678 991', 'party.bvn');
    $a2 = app(FieldEncryptor::class)->blindIndex('22345678991', 'party.bvn');
    $this->useTenant($this->b);
    $b1 = app(FieldEncryptor::class)->blindIndex('22345678991', 'party.bvn');
    expect($a1)->toBe($a2)->not->toBe($b1)->toMatch('/^[0-9a-f]{64}$/');
})->group('FR-SEC-017');

it('rotates data keys while old ciphertext stays readable, and refuses tampered wrapped keys', function () {
    $this->useTenant($this->a);
    $old = app(FieldEncryptor::class)->encrypt('secret-1', 'f');
    expect(app(Fundly\Shared\Crypto\TenantKeyRing::class)->rotate('pii_dek'))->toBe(2);
    $new = app(FieldEncryptor::class)->encrypt('secret-2', 'f');
    expect($new)->toStartWith('fe1.2.')->and(app(FieldEncryptor::class)->decrypt($old, 'f'))->toBe('secret-1');

    $kms = app(KeyManagementPort::class);
    $wrapped = $kms->wrap(random_bytes(32), 'ctx-a');
    expect(fn () => $kms->unwrap($wrapped, 'ctx-b'))->toThrow(KeyManagementFailure::class);
    $other = new LocalKeyfileKms('k2', ['k2' => random_bytes(32)]);
    expect(fn () => $other->unwrap($wrapped, 'ctx-a'))->toThrow(KeyManagementFailure::class);
})->group('FR-SEC-016');
