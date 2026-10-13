<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use Fundly\Modules\Disbursement\Domain\DisbursementStatus;
use Fundly\Modules\Disbursement\Domain\Display;
use Fundly\Modules\Disbursement\Domain\FeeSplit;
use Fundly\Modules\Disbursement\Domain\ScheduleVariance;

function inst(int $n, string $p, string $i): array
{
    return ['number' => $n, 'principal' => $p, 'interest' => $i, 'total' => (string) (BigDecimal::of($p)->plus($i))];
}

it('accepts a CBA schedule within tolerance and reports the largest difference', function () {
    $offered = [inst(1, '240000.00', '61250.00'), inst(2, '244900.00', '56350.00')];
    $booked = [inst(1, '240000.40', '61249.60'), inst(2, '244900.00', '56350.00')];
    $v = ScheduleVariance::compare($offered, $booked, '1.00');
    expect($v)->toMatchArray(['within_tolerance' => true, 'max_difference' => '0.40', 'offered_count' => 2, 'booked_count' => 2, 'mismatches' => []]);
})->group('LOS-FR-313');

it('flags amount differences beyond tolerance, missing instalments and a different count', function () {
    $offered = [inst(1, '100.00', '10.00'), inst(2, '100.00', '9.00'), inst(3, '100.00', '8.00')];
    $booked = [inst(1, '100.00', '12.50'), inst(2, '100.00', '9.00')];
    $v = ScheduleVariance::compare($offered, $booked, '1.00');
    expect($v['within_tolerance'])->toBeFalse()
        ->and(array_map(fn ($m) => [$m['number'], $m['field']], $v['mismatches']))->toBe([[1, 'interest'], [1, 'total'], [3, 'instalment'], [0, 'instalment_count']]);
})->group('LOS-FR-313');

it('deducts only costed upfront fees from the disbursement', function () {
    $split = FeeSplit::split('3000000.0000', [
        ['code' => 'MGMT', 'name' => 'Management', 'type' => 'upfront', 'amount' => '30000.00', 'costed' => true],
        ['code' => 'LEGAL', 'name' => 'Legal', 'type' => 'amortised', 'amount' => '5000.00', 'costed' => true],
        ['code' => 'PEN', 'name' => 'Penalty', 'type' => 'contingent', 'amount' => '0.00', 'costed' => false],
    ]);
    expect($split)->toBe(['deducted' => [['code' => 'MGMT', 'name' => 'Management', 'amount' => '30000.00']], 'total' => '30000.00', 'net' => '2970000.0000']);
})->group('FR-DSB-004');

it('knows which statuses are live, exceptions and retryable', function () {
    expect(DisbursementStatus::Compensated->isLive())->toBeFalse()
        ->and(DisbursementStatus::FailedIntervention->isLive())->toBeTrue()
        ->and(DisbursementStatus::Compensated->isException())->toBeTrue()
        ->and(DisbursementStatus::Compensated->canRetry())->toBeFalse()
        ->and(DisbursementStatus::FailedIntervention->canRetry())->toBeTrue()
        ->and(Display::money('2955000', 'NGN'))->toBe('NGN 2,955,000.00');
});
