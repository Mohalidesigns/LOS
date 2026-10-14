<?php

declare(strict_types=1);

use Fundly\Modules\Workflow\Domain\BusinessCalendar;
use Fundly\Modules\Workflow\Domain\DefaultWorkflow;
use Fundly\Modules\Workflow\Domain\WorkflowDefinition;

$lagos = static fn (string $t): DateTimeImmutable => new DateTimeImmutable($t, new DateTimeZone('Africa/Lagos'));

it('adds business minutes across evenings and weekends', function () use ($lagos) {
    $cal = BusinessCalendar::lagos();
    $fmt = static fn (DateTimeImmutable $d): string => $d->setTimezone(new DateTimeZone('Africa/Lagos'))->format('D H:i');
    expect($fmt($cal->addBusinessMinutes($lagos('2026-10-12 09:00'), 6 * 60)))->toBe('Mon 15:00')
        ->and($fmt($cal->addBusinessMinutes($lagos('2026-10-16 15:00'), 4 * 60)))->toBe('Mon 10:00')
        ->and($fmt($cal->addBusinessMinutes($lagos('2026-10-17 11:00'), 30)))->toBe('Mon 08:30')
        ->and($fmt($cal->addBusinessMinutes($lagos('2026-10-12 06:00'), 0)))->toBe('Mon 08:00');
});

it('counts business minutes between two instants', function () use ($lagos) {
    $cal = BusinessCalendar::lagos();
    expect($cal->businessMinutesBetween($lagos('2026-10-12 15:05'), $lagos('2026-10-13 12:00')))->toBe(355)
        ->and($cal->businessMinutesBetween($lagos('2026-10-16 16:00'), $lagos('2026-10-19 09:00')))->toBe(120)
        ->and($cal->businessMinutesBetween($lagos('2026-10-13 12:00'), $lagos('2026-10-12 12:00')))->toBe(0);
});

it('ships a valid default workflow and resolves stages by status', function () {
    WorkflowDefinition::validate(DefaultWorkflow::standard());
    $w = WorkflowDefinition::of(DefaultWorkflow::standard());
    expect($w->stageFor('assessment'))->toMatchArray(['code' => 'assessment', 'assignment' => ['permission' => 'credit:analyse', 'strategy' => 'least_loaded']])
        ->and($w->stageFor('submitted')['automation'])->toBe('advance')
        ->and($w->stageFor('booked'))->toBeNull();
});
