<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking;

use Fundly\Integration\Runtime\OperationPolicy;

/**
 * Per-operation contract metadata from integration register §2.1: whether it
 * changes state, default timeout and inline retries. State-changing operations
 * are never retried inline; they go through the outbox with lookup-before-retry.
 */
final class Operations
{
    /** @return array<string, OperationPolicy> */
    public static function all(): array
    {
        $read = static fn (int $timeoutMs, int $retries): OperationPolicy => new OperationPolicy(false, $timeoutMs, $retries);
        $write = static fn (int $timeoutMs): OperationPolicy => new OperationPolicy(true, $timeoutMs, 0);

        return [
            'customer.search' => $read(10_000, 2),
            'customer.get' => $read(10_000, 2),
            'customer.create' => $write(30_000),
            'customer.update' => $write(30_000),
            'customer.relationships' => $read(10_000, 2),
            'customer.findByLosReference' => $read(10_000, 2),
            'accounts.list' => $read(10_000, 2),
            'accounts.balance' => $read(5_000, 1),
            'accounts.status' => $read(5_000, 1),
            'accounts.nameEnquiry' => $read(10_000, 1),
            'exposure.get' => $read(15_000, 2),
            'loanAccount.create' => $write(60_000),
            'loanAccount.get' => $read(10_000, 2),
            'loanAccount.findByLosReference' => $read(10_000, 2),
            'loanAccount.amendTerms' => $write(60_000),
            'loanAccount.close' => $write(60_000),
            'postings.disburse' => $write(60_000),
            'postings.charges' => $write(60_000),
            'postings.reverse' => $write(60_000),
            'postings.getByReference' => $read(10_000, 3),
            'schedule.generate' => $read(15_000, 2),
            'schedule.get' => $read(15_000, 2),
            'collateral.register' => $write(30_000),
            'collateral.update' => $write(30_000),
            'collateral.release' => $write(30_000),
            'mandates.createStandingInstruction' => $write(30_000),
            'mandates.cancelStandingInstruction' => $write(30_000),
            'reference.products' => $read(30_000, 2),
            'reference.branches' => $read(30_000, 2),
            'reference.glCodes' => $read(30_000, 2),
            'reference.currencies' => $read(30_000, 2),
            'reference.rates' => $read(30_000, 2),
            'reconciliation.postings' => $read(120_000, 2),
            'reconciliation.statement' => $read(120_000, 2),
        ];
    }

    public static function policy(string $operation): OperationPolicy
    {
        return self::all()[$operation] ?? throw new \InvalidArgumentException("Unknown core banking operation {$operation}.");
    }
}
