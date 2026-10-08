<?php

declare(strict_types=1);

namespace Fundly\Integration\Simulators\Identity;

use Fundly\Integration\Ports\Identity\Dto\IdentityCheckRequest;
use Fundly\Integration\Ports\Identity\Dto\IdentityCheckResult;
use Fundly\Integration\Ports\Identity\IdentityVerificationPort;
use Fundly\Integration\Runtime\CapabilityManifest;
use Fundly\Integration\Runtime\Errors\RetryableError;

/**
 * Deterministic identity simulator for UAT and tests (synthetic data only,
 * D-039). Numbers ending in 00 are not found, 99 return a name mismatch, 98
 * time out; everything else verifies with a 100% match. Binding config
 * `records` may pin a number to specific attributes.
 */
final class IdentitySimulator implements IdentityVerificationPort
{
    public const KEY = 'identity-simulator';

    public const VERSION = '1.0.0';

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = []) {}

    public static function capabilities(): CapabilityManifest
    {
        return new CapabilityManifest(self::KEY, self::VERSION, '1.0', 'on_prem:simulator', [
            self::OP_VERIFY => ['support' => 'native', 'idempotency' => 'natural'],
        ]);
    }

    public function verify(IdentityCheckRequest $request): IdentityCheckResult
    {
        $ref = 'SIM-ID-'.strtoupper(substr(hash('sha256', $request->idType.$request->idNumber), 0, 12));
        $suffix = substr($request->idNumber, -2);
        if ($suffix === '98') {
            throw new RetryableError('IDENTITY.TRANSPORT.TIMEOUT', 'Simulated provider timeout.');
        }
        if ($suffix === '00') {
            return new IdentityCheckResult(IdentityCheckResult::NOT_FOUND, '0.00', $ref, [], []);
        }
        $records = is_array($this->config['records'] ?? null) ? $this->config['records'] : [];
        $record = is_array($records[$request->idNumber] ?? null) ? $records[$request->idNumber] : null;
        if ($suffix === '99' || $record !== null) {
            $matched = [];
            $mismatched = [];
            foreach (['first_name' => $request->firstName, 'last_name' => $request->lastName, 'date_of_birth' => $request->dateOfBirth] as $field => $claimed) {
                $expected = $record[$field] ?? ($suffix === '99' && $field === 'last_name' ? 'DIFFERENT' : $claimed);
                (is_string($expected) && $claimed !== null && strcasecmp($expected, $claimed) === 0) ? $matched[] = $field : $mismatched[] = $field;
            }
            $score = (string) intdiv(count($matched) * 100, 3).'.00';

            return new IdentityCheckResult($mismatched === [] ? IdentityCheckResult::VERIFIED : IdentityCheckResult::MISMATCH, $score, $ref, $matched, $mismatched);
        }

        return new IdentityCheckResult(IdentityCheckResult::VERIFIED, '100.00', $ref, ['first_name', 'last_name', 'date_of_birth'], []);
    }
}
