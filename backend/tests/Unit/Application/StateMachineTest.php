<?php

declare(strict_types=1);

use Fundly\Modules\Application\Contracts\CanonicalStatus as S;
use Fundly\Modules\Application\Domain\Application;
use Fundly\Modules\Application\Domain\ApplicationAction as A;
use Fundly\Modules\Application\Domain\ApplicationRuleViolation;
use Fundly\Modules\Application\Domain\StateMachine;

function draftApp(array $overrides = []): Application
{
    return Application::create('0190a8f0-0000-7000-8000-000000000001', array_merge([
        'reference' => 'DEMO-2026-000001', 'legal_entity_id' => 'le', 'org_unit_id' => 'ou', 'product_id' => 'p', 'product_key' => 'sme',
        'product_version_id' => 'pv', 'product_name' => 'SME', 'segment' => 'sme', 'currency' => 'NGN', 'channel' => 'staff', 'originator_id' => 'rm',
        'primary_party_id' => 'party-1', 'primary_party_type' => 'limited_company', 'primary_party_name' => 'Adebayo Foods',
        'requested_amount' => '5000000.00', 'tenor_months' => 12, 'purpose' => 'Working capital', 'repayment_frequency' => 'monthly', 'data' => [], 'expires_at' => '2026-11-07T00:00:00+00:00',
    ], $overrides));
}

it('encodes the canonical graph of TRD §6.2 including the G-14 amendments', function () {
    expect(StateMachine::allows(S::Draft, S::Submitted))->toBeTrue()
        ->and(StateMachine::allows(S::Submitted, S::Declined))->toBeTrue()
        ->and(StateMachine::allows(S::Approval, S::Approval))->toBeTrue()
        ->and(StateMachine::allows(S::OfferIssued, S::Assessment))->toBeTrue()
        ->and(StateMachine::allows(S::Declined, S::Assessment))->toBeTrue()
        ->and(StateMachine::allows(S::Disbursing, S::Booked))->toBeTrue()
        ->and(StateMachine::allows(S::Draft, S::Approved))->toBeFalse()
        ->and(StateMachine::allows(S::Booked, S::Disbursing))->toBeFalse()
        ->and(StateMachine::allows(S::ReadyForDisbursement, S::Booked))->toBeFalse();
    foreach ([S::Booked, S::Declined, S::Expired, S::Withdrawn, S::Cancelled] as $terminal) {
        expect($terminal->isTerminal())->toBeTrue();
    }
})->group('LOS-FR-282');

it('offers hold, rework, withdraw and cancel from every stage up to ReadyForDisbursement but not from Disbursing', function () {
    expect(StateMachine::crossCuttingAllowedFrom(S::Draft))->toBeTrue()
        ->and(StateMachine::crossCuttingAllowedFrom(S::ReadyForDisbursement))->toBeTrue()
        ->and(StateMachine::crossCuttingAllowedFrom(S::Disbursing))->toBeFalse()
        ->and(StateMachine::crossCuttingAllowedFrom(S::Booked))->toBeFalse()
        ->and(StateMachine::canClose(S::OnHold))->toBeTrue()
        ->and(StateMachine::canReturnTo(S::Assessment, S::Documentation))->toBeTrue()
        ->and(StateMachine::canReturnTo(S::Documentation, S::Assessment))->toBeFalse();
})->group('LOS-FR-283');

it('derives state only from events and versions every change', function () {
    $app = draftApp();
    $app->amend(['requested_amount' => '7500000.00', 'data' => ['sector' => 'agro']], 'staff', null);
    $app->act(A::Submit, null, null);
    $events = $app->releaseEvents();
    expect(array_map(fn ($e) => [$e->type, $e->version], $events))->toBe([
        ['application.created', 1], ['application.amended', 2], ['application.status_changed', 3],
    ]);
    $stored = array_map(fn ($e) => ['type' => $e->type, 'payload' => $e->payload, 'version' => $e->version, 'occurred_at' => new DateTimeImmutable('2026-10-08T10:0'.$e->version.':00Z')], $events);
    $replayed = Application::replay($app->id(), $stored);
    expect($replayed->snapshot())->toBe($app->snapshot());
    // as-at: only the first two events
    expect(Application::replay($app->id(), $stored, new DateTimeImmutable('2026-10-08T10:02:30Z'))->status())->toBe(S::Draft)
        ->and(Application::replay($app->id(), $stored, new DateTimeImmutable('2026-10-08T10:02:30Z'))->requestedAmount())->toBe('7500000.00');
})->group('FR-APP-005', 'FR-AUD-010');

it('blocks submit until required data is present', function () {
    $app = draftApp(['purpose' => null, 'tenor_months' => null]);
    try {
        $app->act(A::Submit, null, null);
        $this->fail('expected a violation');
    } catch (ApplicationRuleViolation $e) {
        expect($e->extensions()['blockers'])->toBe(['tenor_months is required.', 'purpose is required.']);
    }
    expect($app->status())->toBe(S::Draft);
})->group('FR-APP-008');

it('holds and resumes to the stage it left, and returns for rework to an earlier stage only', function () {
    $app = draftApp();
    $app->act(A::Submit, null, null);
    $app->advance(S::PreQualified, 'ELIGIBILITY_PASS', null);
    $app->advance(S::KycScreening, 'AUTO', null);
    $app->act(A::Hold, 'AWAITING_CUSTOMER', 'Customer travelling');
    expect($app->status())->toBe(S::OnHold);
    $app->act(A::Resume, null, null);
    expect($app->status())->toBe(S::KycScreening);
    expect(fn () => $app->act(A::Return, 'REWORK', null, S::Assessment))->toThrow(ApplicationRuleViolation::class);
    $app->act(A::Return, 'MISSING_DOCS', null, S::Draft);
    expect($app->status())->toBe(S::ReturnedForRework);
    $app->act(A::Resubmit, null, null);
    expect($app->status())->toBe(S::Draft);
})->group('LOS-FR-283', 'FR-WFL-007', 'FR-WFL-008');

it('requires reason codes for terminal and pausing actions and freezes terminal applications', function () {
    $app = draftApp();
    expect(fn () => $app->act(A::Withdraw, null, null))->toThrow(ApplicationRuleViolation::class, 'A reason code is required');
    $app->act(A::Withdraw, 'CUSTOMER_REQUEST', null);
    expect($app->status())->toBe(S::Withdrawn);
    expect(fn () => $app->amend(['purpose' => 'x'], 'staff', null))->toThrow(ApplicationRuleViolation::class);
    expect(fn () => $app->act(A::Cancel, 'X', null))->toThrow(ApplicationRuleViolation::class);
    expect(fn () => $app->advance(S::Submitted, 'X', null))->toThrow(ApplicationRuleViolation::class);
})->group('FR-APP-006');

it('manages joint applicants and guarantors; the primary stays', function () {
    $app = draftApp();
    $app->addApplicant('party-2', 'guarantor', 'individual', 'Ngozi Okafor');
    expect(fn () => $app->addApplicant('party-2', 'joint', 'individual', 'Ngozi Okafor'))->toThrow(ApplicationRuleViolation::class);
    expect(fn () => $app->addApplicant('party-3', 'primary', 'individual', 'X'))->toThrow(ApplicationRuleViolation::class);
    expect(fn () => $app->removeApplicant('party-1'))->toThrow(ApplicationRuleViolation::class);
    expect($app->applicants())->toHaveKeys(['party-1', 'party-2'])->and($app->applicantTypes())->toBe(['limited_company', 'individual']);
    $app->removeApplicant('party-2');
    expect($app->applicants())->toHaveKeys(['party-1'])->toHaveCount(1);
})->group('FR-APP-004');

it('allows amendment only before approval', function () {
    $app = draftApp();
    $app->act(A::Submit, null, null);
    foreach ([S::PreQualified, S::KycScreening, S::Documentation, S::Assessment] as $s) {
        $app->advance($s, 'AUTO', null);
    }
    expect($app->amend(['tenor_months' => 18], 'staff', null))->toBe(['tenor_months']);
    $app->act(A::Recommend, null, null);
    $app->advance(S::Approval, 'AUTO', null);
    expect(fn () => $app->amend(['tenor_months' => 24], 'staff', null))->toThrow(ApplicationRuleViolation::class);
})->group('FR-APP-005');
