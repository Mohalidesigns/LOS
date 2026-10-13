<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/**
 * A vote at the request's current authority level (FR-APV-008/009): decision,
 * mandatory rationale, reason codes for anything but a plain approval,
 * conditions for a conditional approval, terms for a counter-offer.
 */
#[HandledBy(CastApprovalVoteHandler::class)]
final readonly class CastApprovalVote implements Command, ValidatesInput
{
    public const DECISIONS = ['approve', 'approve_with_conditions', 'decline', 'counter_offer'];

    /**
     * @param  list<mixed>  $reasonCodes
     * @param  list<mixed>  $conditions
     * @param  array<string, mixed>|null  $counterTerms
     */
    public function __construct(
        public string $requestId,
        public string $decision,
        public ?string $rationale,
        public array $reasonCodes,
        public array $conditions,
        public ?array $counterTerms,
    ) {}

    public function action(): string
    {
        return 'approval.vote.cast';
    }

    public function permission(): string
    {
        return 'application:approve';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('approval_request', $this->requestId);
    }

    public function data(): array
    {
        return ['decision' => $this->decision, 'rationale' => $this->rationale, 'reason_codes' => $this->reasonCodes, 'conditions' => $this->conditions, 'counter_terms' => $this->counterTerms];
    }

    public function rules(): array
    {
        $needsCodes = 'required_if:decision,approve_with_conditions,decline,counter_offer';

        return [
            'decision' => ['required', 'in:'.implode(',', self::DECISIONS)],
            'rationale' => ['required', 'string', 'min:10', 'max:4000'],
            'reason_codes' => ['array', 'max:20', $needsCodes],
            'reason_codes.*' => ['string', 'distinct', 'regex:/^[A-Z][A-Z0-9_]{1,47}$/'],
            'conditions' => ['array', 'max:30', 'required_if:decision,approve_with_conditions', 'prohibited_unless:decision,approve_with_conditions'],
            'conditions.*.kind' => ['required', 'in:precedent,subsequent'],
            'conditions.*.description' => ['required', 'string', 'min:5', 'max:1000'],
            'counter_terms' => ['nullable', 'array', 'required_if:decision,counter_offer', 'prohibited_unless:decision,counter_offer'],
            'counter_terms.amount' => ['required_with:counter_terms', 'string', 'regex:/^\d{1,16}(\.\d{1,4})?$/'],
            'counter_terms.tenor_months' => ['required_with:counter_terms', 'integer', 'min:1', 'max:600'],
        ];
    }
}
