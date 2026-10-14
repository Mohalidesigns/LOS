<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Record a valuation: valuer, date, method, amount and expiry (FR-COL-003). It becomes the basis for cover. */
#[HandledBy(RecordValuationHandler::class)]
final readonly class RecordValuation implements Command, ValidatesInput
{
    public function __construct(public string $collateralId, public ?string $valuer, public ?string $valuationDate, public ?string $method, public ?string $marketValue, public ?string $forcedSaleValue = null, public ?string $validUntil = null, public ?string $note = null, public ?string $tempPath = null, public ?string $originalName = null) {}

    public function action(): string
    {
        return 'collateral.valued';
    }

    public function permission(): string
    {
        return 'collateral:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('collateral', $this->collateralId);
    }

    public function data(): array
    {
        return ['valuer' => $this->valuer, 'valuation_date' => $this->valuationDate, 'method' => $this->method, 'market_value' => $this->marketValue, 'forced_sale_value' => $this->forcedSaleValue, 'valid_until' => $this->validUntil, 'note' => $this->note];
    }

    public function rules(): array
    {
        return ['valuer' => ['required', 'string', 'min:2', 'max:200'], 'valuation_date' => ['required', 'date_format:Y-m-d'], 'method' => ['required', 'in:market_comparison,income,cost,residual,desktop,mark_to_market'], 'market_value' => ['required', 'string', 'regex:/^\\d{1,16}(\\.\\d{1,4})?$/'], 'forced_sale_value' => ['nullable', 'string', 'regex:/^\\d{1,16}(\\.\\d{1,4})?$/'], 'valid_until' => ['nullable', 'date_format:Y-m-d', 'after:valuation_date'], 'note' => ['nullable', 'string', 'max:2000']];
    }
}
