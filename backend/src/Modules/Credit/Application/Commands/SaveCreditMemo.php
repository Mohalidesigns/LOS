<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Save a new credit memo version: frozen auto sections + analyst narrative and recommendation (FR-CRD-013). */
#[HandledBy(SaveCreditMemoHandler::class)]
final readonly class SaveCreditMemo implements Command, ValidatesInput
{
    /** @param list<string> $conditions */
    public function __construct(
        public string $applicationId,
        public ?string $narrative,
        public string $recommendation,
        public ?string $recommendedAmount,
        public ?int $recommendedTenorMonths,
        public array $conditions,
    ) {}

    public function action(): string
    {
        return 'credit.memo.saved';
    }

    public function permission(): string
    {
        return 'credit:analyse';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }

    public function data(): array
    {
        return ['narrative' => $this->narrative, 'recommendation' => $this->recommendation, 'recommended_amount' => $this->recommendedAmount, 'recommended_tenor_months' => $this->recommendedTenorMonths, 'conditions' => $this->conditions];
    }

    public function rules(): array
    {
        return [
            'narrative' => ['required', 'string', 'min:30', 'max:20000'],
            'recommendation' => ['required', 'in:approve,decline,counter_offer'],
            'recommended_amount' => ['nullable', 'regex:/^\d{1,16}(\.\d{1,4})?$/', 'required_if:recommendation,counter_offer'],
            'recommended_tenor_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'conditions' => ['array', 'max:30'],
            'conditions.*' => ['string', 'min:3', 'max:500'],
        ];
    }
}
