<?php

declare(strict_types=1);

use Fundly\Modules\Conditions\Domain\CheckItem;
use Fundly\Modules\Conditions\Domain\ConditionStatus;
use Fundly\Modules\Conditions\Domain\NameMatch;
use Fundly\Shared\Exceptions\DomainRuleViolation;

it('matches account names regardless of order, case, punctuation and company suffixes', function () {
    expect(NameMatch::score('Amaka Nwosu', 'NWOSU AMAKA'))->toBe(100)
        ->and(NameMatch::score('Adebayo Foods Limited', 'ADEBAYO FOODS LTD.'))->toBe(100)
        ->and(NameMatch::score('Mrs Funke Adebayo', 'ADEBAYO, FUNKE'))->toBe(100)
        ->and(NameMatch::score('Amaka Nwosu', 'AMAKA OKORO'))->toBe(50)
        ->and(NameMatch::score('Amaka Nwosu', 'JOHN DOE TRADING'))->toBe(0)
        ->and(NameMatch::score('', 'ANYONE'))->toBe(0);
})->group('FR-CPR-005');

it('treats open and waiver-pending items as outstanding and refuses double sign-off', function () {
    expect(ConditionStatus::Open->isOutstanding())->toBeTrue()
        ->and(ConditionStatus::WaiverPending->isOutstanding())->toBeTrue()
        ->and(ConditionStatus::Waived->isOutstanding())->toBeFalse();
    ConditionStatus::WaiverPending->assertCanWaive();
    expect(fn () => ConditionStatus::Satisfied->assertCanSatisfy())->toThrow(DomainRuleViolation::class)
        ->and(fn () => ConditionStatus::Waived->assertCanWaive())->toThrow(DomainRuleViolation::class);
})->group('FR-CPR-002');

it('passes a check only when nothing fails; not-applicable items do not block', function () {
    expect(CheckItem::passed([CheckItem::pass('kyc_current', 'ok'), CheckItem::notApplicable('insurance_in_force', 'n/a')]))->toBeTrue()
        ->and(CheckItem::passed([CheckItem::pass('kyc_current', 'ok'), CheckItem::fail('account_verified', 'no')]))->toBeFalse()
        ->and(CheckItem::fail('account_verified', 'x')->toArray()['label'])->toBe('Destination account verified');
})->group('FR-CPR-003');
