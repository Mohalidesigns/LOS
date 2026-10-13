<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Infrastructure;

use Fundly\Modules\Conditions\Application\ConditionFile;
use Fundly\Modules\Conditions\Contracts\ConditionsGate;
use Fundly\Modules\Conditions\Contracts\VerifiedDestination;
use Fundly\Shared\Crypto\FieldEncryptor;

final class DatabaseConditionsGate implements ConditionsGate
{
    public function __construct(private readonly ConditionFile $file, private readonly FieldEncryptor $crypto) {}

    public function releaseBlockers(string $applicationId): array
    {
        $out = array_map(static fn ($c): string => "Condition precedent outstanding: {$c->description}", $this->file->outstanding($applicationId));
        $check = $this->file->latestCheck($applicationId);
        if ($check === null) {
            $out[] = 'Run the pre-disbursement check.';
        } elseif (! $check->passed) {
            $out[] = "The latest pre-disbursement check (#{$check->sequence}) failed; resolve the failures and run it again.";
        } elseif ($check->valid_until->lessThan($this->file->now())) {
            $out[] = "The latest pre-disbursement check (#{$check->sequence}) is out of date; run it again.";
        }

        return array_values($out);
    }

    public function verifiedDestination(string $applicationId): ?VerifiedDestination
    {
        $check = $this->file->latestCheck($applicationId);
        if ($check === null || ! $check->passed || $check->valid_until->lessThan($this->file->now())) {
            return null;
        }

        return new VerifiedDestination(
            $check->id,
            $check->bank_code,
            $this->crypto->decrypt($check->account_no_enc, 'conditions.account_no'),
            $check->account_name,
            $check->checked_at->toDateTimeImmutable(),
            $check->valid_until->toDateTimeImmutable(),
        );
    }
}
