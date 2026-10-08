<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Domain;

use DateTimeImmutable;
use Fundly\Modules\Application\Contracts\CanonicalStatus as S;

/**
 * Event-sourced application aggregate (TRD §5.2, FR-APP-001/005/006,
 * LOS-FR-282/283). State is derived only by applying events; every change is
 * a new event with the next version, so `(application_id, version)` gives
 * optimistic concurrency. Pure PHP: no framework, no I/O.
 */
final class Application
{
    public const AMENDABLE = ['requested_amount', 'tenor_months', 'purpose', 'repayment_frequency'];

    public const APPLICANT_ROLES = ['primary', 'joint', 'guarantor', 'co_signer'];

    private string $id = '';

    private string $reference = '';

    private S $status = S::Draft;

    private ?S $resumeTo = null;

    private ?S $returnTo = null;

    private string $currency = '';

    private ?string $requestedAmount = null;

    private ?int $tenorMonths = null;

    private ?string $purpose = null;

    private ?string $repaymentFrequency = null;

    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, array{role: string, party_type: string}> party id → role */
    private array $applicants = [];

    private string $originatorId = '';

    private int $version = 0;

    /** @var list<PendingEvent> */
    private array $pending = [];

    private function __construct() {}

    /**
     * @param  array{reference: string, legal_entity_id: string, org_unit_id: string, product_id: string, product_key: string, product_version_id: string, product_name: string, segment: string, currency: string, channel: string, originator_id: string, primary_party_id: string, primary_party_type: string, primary_party_name: string, requested_amount: ?string, tenor_months: ?int, purpose: ?string, repayment_frequency: ?string, data: array<string, mixed>, expires_at: string}  $attributes
     */
    public static function create(string $id, array $attributes): self
    {
        $app = new self;
        $app->id = $id;
        $app->record('application.created', $attributes);

        return $app;
    }

    /**
     * Rebuild from stored events, optionally only those up to an instant (as-at, FR-AUD-010).
     *
     * @param  iterable<array{type: string, payload: array<string, mixed>, version: int, occurred_at: DateTimeImmutable}>  $events
     */
    public static function replay(string $id, iterable $events, ?DateTimeImmutable $asAt = null): self
    {
        $app = new self;
        $app->id = $id;
        foreach ($events as $e) {
            if ($asAt !== null && $e['occurred_at'] > $asAt) {
                break;
            }
            $app->apply($e['type'], $e['payload']);
            $app->version = $e['version'];
        }

        return $app;
    }

    // ---- behaviour -------------------------------------------------------------------------

    /**
     * Amend captured data before approval (FR-APP-005). Returns the changed field paths.
     *
     * @param  array<string, mixed>  $changes  top-level amendable fields and/or `data` (merged)
     * @return list<string>
     */
    public function amend(array $changes, string $source, ?string $sourceRef): array
    {
        $this->assertOpen();
        if (! $this->status->isPreApproval()) {
            throw new ApplicationRuleViolation("An application in {$this->status->value} can no longer be amended; use the post-approval amendment process.");
        }
        $diff = [];
        foreach (self::AMENDABLE as $field) {
            if (array_key_exists($field, $changes) && $changes[$field] !== $this->field($field)) {
                $diff[$field] = ['from' => $this->field($field), 'to' => $changes[$field]];
            }
        }
        if (isset($changes['data']) && is_array($changes['data'])) {
            foreach ($changes['data'] as $key => $value) {
                $path = 'data.'.$key;
                if (($this->data[$key] ?? null) !== $value) {
                    $diff[$path] = ['from' => $this->data[$key] ?? null, 'to' => $value];
                }
            }
        }
        if ($diff === []) {
            return [];
        }
        $this->record('application.amended', ['changes' => $diff, 'source' => $source, 'source_ref' => $sourceRef]);

        return array_keys($diff);
    }

    public function addApplicant(string $partyId, string $role, string $partyType, string $displayName): void
    {
        $this->assertOpen();
        if (! $this->status->isPreApproval()) {
            throw new ApplicationRuleViolation('Applicants can only be changed before approval.');
        }
        if (! in_array($role, self::APPLICANT_ROLES, true) || $role === 'primary') {
            throw new ApplicationRuleViolation('Role must be joint, guarantor or co_signer; the primary applicant is set at creation.');
        }
        if (isset($this->applicants[$partyId])) {
            throw new ApplicationRuleViolation('This party is already on the application.');
        }
        $this->record('application.applicant_added', ['party_id' => $partyId, 'role' => $role, 'party_type' => $partyType, 'display_name' => $displayName]);
    }

    public function removeApplicant(string $partyId): void
    {
        $this->assertOpen();
        if (! isset($this->applicants[$partyId])) {
            throw new ApplicationRuleViolation('This party is not on the application.');
        }
        if ($this->applicants[$partyId]['role'] === 'primary') {
            throw new ApplicationRuleViolation('The primary applicant cannot be removed.');
        }
        if (! $this->status->isPreApproval()) {
            throw new ApplicationRuleViolation('Applicants can only be changed before approval.');
        }
        $this->record('application.applicant_removed', ['party_id' => $partyId]);
    }

    /**
     * Staff action. `$blockers` are reasons computed outside the aggregate
     * (e.g. product limits) that stop a submit.
     *
     * @param  list<string>  $blockers
     */
    public function act(ApplicationAction $action, ?string $reasonCode, ?string $reasonText, ?S $returnTo = null, array $blockers = []): void
    {
        if ($action->requiresReason() && ($reasonCode === null || $reasonCode === '')) {
            throw new ApplicationRuleViolation("A reason code is required to {$action->value} an application.");
        }
        switch ($action) {
            case ApplicationAction::Submit:
                $this->expect(S::Draft, $action);
                $missing = array_merge($this->submissionGaps(), $blockers);
                if ($missing !== []) {
                    throw new ApplicationRuleViolation('The application is not ready to submit.', ['blockers' => $missing]);
                }
                $this->move(S::Submitted, $action, $reasonCode, $reasonText);
                break;
            case ApplicationAction::Recommend:
                $this->expect(S::Assessment, $action);
                $this->move(S::Recommended, $action, $reasonCode, $reasonText);
                break;
            case ApplicationAction::Hold:
                if (! StateMachine::crossCuttingAllowedFrom($this->status)) {
                    throw $this->illegal($action);
                }
                $this->move(S::OnHold, $action, $reasonCode, $reasonText, ['resume_to' => $this->status->value]);
                break;
            case ApplicationAction::Resume:
                $this->expect(S::OnHold, $action);
                $this->move($this->resumeTo ?? S::Draft, $action, $reasonCode, $reasonText);
                break;
            case ApplicationAction::Return:
                if ($returnTo === null || ! StateMachine::canReturnTo($this->status, $returnTo)) {
                    throw new ApplicationRuleViolation('Rework must return to an earlier stage the application has passed.', ['current' => $this->status->value]);
                }
                $this->move(S::ReturnedForRework, $action, $reasonCode, $reasonText, ['return_to' => $returnTo->value]);
                break;
            case ApplicationAction::Resubmit:
                $this->expect(S::ReturnedForRework, $action);
                $this->move($this->returnTo ?? S::Draft, $action, $reasonCode, $reasonText);
                break;
            case ApplicationAction::Withdraw:
            case ApplicationAction::Cancel:
                if (! StateMachine::canClose($this->status)) {
                    throw $this->illegal($action);
                }
                $this->move($action === ApplicationAction::Withdraw ? S::Withdrawn : S::Cancelled, $action, $reasonCode, $reasonText);
                break;
        }
    }

    /** A move driven by another module (screening cleared, decision made, booked …) along the canonical graph. */
    public function advance(S $to, string $reasonCode, ?string $note): void
    {
        if ($this->status->isTerminal() || ! StateMachine::allows($this->status, $to)) {
            throw new ApplicationRuleViolation(sprintf('Transition %s → %s is not allowed.', $this->status->value, $to->value));
        }
        $this->move($to, null, $reasonCode, $note);
    }

    /**
     * Fields still missing before submit (completeness, FR-APP-008).
     *
     * @return list<string>
     */
    public function submissionGaps(): array
    {
        $gaps = [];
        if ($this->requestedAmount === null) {
            $gaps[] = 'requested_amount is required.';
        }
        if ($this->tenorMonths === null) {
            $gaps[] = 'tenor_months is required.';
        }
        if ($this->purpose === null || trim($this->purpose) === '') {
            $gaps[] = 'purpose is required.';
        }

        return $gaps;
    }

    // ---- state --------------------------------------------------------------------------------

    public function id(): string
    {
        return $this->id;
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function status(): S
    {
        return $this->status;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function requestedAmount(): ?string
    {
        return $this->requestedAmount;
    }

    public function tenorMonths(): ?int
    {
        return $this->tenorMonths;
    }

    public function originatorId(): string
    {
        return $this->originatorId;
    }

    /** @return array<string, array{role: string, party_type: string}> */
    public function applicants(): array
    {
        return $this->applicants;
    }

    /** @return list<string> */
    public function applicantTypes(): array
    {
        return array_values(array_unique(array_column($this->applicants, 'party_type')));
    }

    /** @return list<PendingEvent> */
    public function releaseEvents(): array
    {
        $events = $this->pending;
        $this->pending = [];

        return $events;
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'resume_to' => $this->resumeTo?->value,
            'return_to' => $this->returnTo?->value,
            'currency' => $this->currency,
            'requested_amount' => $this->requestedAmount,
            'tenor_months' => $this->tenorMonths,
            'purpose' => $this->purpose,
            'repayment_frequency' => $this->repaymentFrequency,
            'data' => $this->data,
            'applicants' => $this->applicants,
            'version' => $this->version,
        ];
    }

    // ---- internals ----------------------------------------------------------------------------

    /** @param array<string, mixed> $extra */
    private function move(S $to, ?ApplicationAction $action, ?string $reasonCode, ?string $reasonText, array $extra = []): void
    {
        $this->record('application.status_changed', [
            'from' => $this->status->value,
            'to' => $to->value,
            'action' => $action?->value,
            'reason_code' => $reasonCode,
            'reason_text' => $reasonText,
        ] + $extra);
    }

    private function expect(S $required, ApplicationAction $action): void
    {
        if ($this->status !== $required) {
            throw $this->illegal($action);
        }
    }

    private function illegal(ApplicationAction $action): ApplicationRuleViolation
    {
        return new ApplicationRuleViolation("Cannot {$action->value} an application that is {$this->status->value}.", ['current' => $this->status->value]);
    }

    private function assertOpen(): void
    {
        if ($this->status->isTerminal()) {
            throw new ApplicationRuleViolation("The application is {$this->status->value}.");
        }
    }

    private function field(string $name): mixed
    {
        return match ($name) {
            'requested_amount' => $this->requestedAmount,
            'tenor_months' => $this->tenorMonths,
            'purpose' => $this->purpose,
            'repayment_frequency' => $this->repaymentFrequency,
            default => null,
        };
    }

    /** @param array<string, mixed> $payload */
    private function record(string $type, array $payload): void
    {
        $this->apply($type, $payload);
        $this->version++;
        $this->pending[] = new PendingEvent($type, $payload, $this->version);
    }

    /** @param array<string, mixed> $p */
    private function apply(string $type, array $p): void
    {
        switch ($type) {
            case 'application.created':
                $this->reference = (string) $p['reference'];
                $this->currency = (string) $p['currency'];
                $this->requestedAmount = isset($p['requested_amount']) ? (string) $p['requested_amount'] : null;
                $this->tenorMonths = isset($p['tenor_months']) ? (int) $p['tenor_months'] : null;
                $this->purpose = isset($p['purpose']) ? (string) $p['purpose'] : null;
                $this->repaymentFrequency = isset($p['repayment_frequency']) ? (string) $p['repayment_frequency'] : null;
                $this->data = is_array($p['data'] ?? null) ? $p['data'] : [];
                $this->originatorId = (string) $p['originator_id'];
                $this->applicants[(string) $p['primary_party_id']] = ['role' => 'primary', 'party_type' => (string) $p['primary_party_type']];
                $this->status = S::Draft;
                break;
            case 'application.amended':
                foreach ((array) $p['changes'] as $path => $change) {
                    $to = is_array($change) ? ($change['to'] ?? null) : null;
                    $path = (string) $path;
                    if (str_starts_with($path, 'data.')) {
                        $this->data[substr($path, 5)] = $to;

                        continue;
                    }
                    match ($path) {
                        'requested_amount' => $this->requestedAmount = $to === null ? null : (string) $to,
                        'tenor_months' => $this->tenorMonths = $to === null ? null : (int) $to,
                        'purpose' => $this->purpose = $to === null ? null : (string) $to,
                        'repayment_frequency' => $this->repaymentFrequency = $to === null ? null : (string) $to,
                        default => null,
                    };
                }
                break;
            case 'application.applicant_added':
                $this->applicants[(string) $p['party_id']] = ['role' => (string) $p['role'], 'party_type' => (string) $p['party_type']];
                break;
            case 'application.applicant_removed':
                unset($this->applicants[(string) $p['party_id']]);
                break;
            case 'application.status_changed':
                $to = S::from((string) $p['to']);
                if ($to === S::OnHold) {
                    $this->resumeTo = S::from((string) $p['resume_to']);
                } elseif ($to === S::ReturnedForRework) {
                    $this->returnTo = S::from((string) $p['return_to']);
                }
                if ($this->status === S::OnHold) {
                    $this->resumeTo = null;
                }
                if ($this->status === S::ReturnedForRework) {
                    $this->returnTo = null;
                }
                $this->status = $to;
                break;
        }
    }
}
