<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Brick\Math\BigDecimal;
use Fundly\Modules\Collateral\Application\CollateralFile;
use Fundly\Modules\Collateral\Application\CollateralQueries;
use Fundly\Modules\Collateral\Domain\CollateralType;
use Fundly\Modules\Collateral\Domain\Cover;
use Fundly\Modules\Collateral\Infrastructure\Models\Collateral;
use Fundly\Modules\Collateral\Infrastructure\Models\CollateralValuation;
use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Money\Money;
use Illuminate\Support\Carbon;

final class RecordValuationHandler implements CommandHandler
{
    public function __construct(private readonly CollateralFile $file, private readonly DocumentArchive $archive, private readonly CollateralQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RecordValuation);
        $c = Collateral::query()->lockForUpdate()->findOrFail($this->file->collateral($command->collateralId)->id);
        $app = $this->file->application($c->origin_application_id);
        $this->file->assertOpen($app);
        $date = Carbon::parse((string) $command->valuationDate)->startOfDay();
        if ($date->greaterThan($this->file->today())) {
            throw ValidationFailed::with(['valuation_date' => 'A valuation cannot be dated in the future.']);
        }
        $market = Money::of((string) $command->marketValue, $c->currency);
        $fsv = $command->forcedSaleValue === null ? Cover::forcedSaleValue($market, BigDecimal::of($c->haircut_percent)) : Money::of($command->forcedSaleValue, $c->currency);
        if ($fsv->isGreaterThan($market)) {
            throw ValidationFailed::with(['forced_sale_value' => 'The forced-sale value cannot exceed the market value.']);
        }
        [$type] = $this->typeOf($c);
        $months = $type?->valuationValidityMonths();
        $validUntil = $command->validUntil ?? ($months === null ? null : $date->copy()->addMonthsNoOverflow($months)->toDateString());
        $doc = $command->tempPath === null ? null
            : $this->archive->storeUploaded($app->id, 'VALUATION_REPORT', 'Valuation: '.mb_substr($c->description, 0, 120), $command->tempPath, (string) $command->originalName, $context->principal->id);
        $v = new CollateralValuation;
        $v->forceFill([
            'collateral_id' => $c->id, 'sequence' => (int) CollateralValuation::query()->where('collateral_id', $c->id)->max('sequence') + 1,
            'valuer' => trim((string) $command->valuer), 'valuation_date' => $date->toDateString(), 'method' => $command->method,
            'market_value' => $market->toStorage(), 'forced_sale_value' => $fsv->toStorage(), 'currency' => $c->currency, 'valid_until' => $validUntil,
            'report_document_id' => $doc?->documentId, 'report_version_id' => $doc?->versionId, 'note' => $command->note,
            'recorded_by' => $context->principal->id, 'recorded_at' => $this->file->now(),
        ])->save();
        $before = ['market_value' => $c->market()->toArray(), 'forced_sale_value' => $c->forcedSale()->toArray(), 'value_basis' => $c->value_basis];
        $c->forceFill(['market_value' => $market->toStorage(), 'forced_sale_value' => $fsv->toStorage(), 'value_basis' => 'valuation', 'valuation_valid_until' => $validUntil])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'collateral', entityId: $c->id, before: $before, after: [
            'valuation_id' => $v->id, 'sequence' => $v->sequence, 'valuer' => $v->valuer, 'method' => $v->method, 'valuation_date' => $date->toDateString(),
            'market_value' => $market->toArray(), 'forced_sale_value' => $fsv->toArray(), 'valid_until' => $validUntil, 'report_sha256' => $doc?->sha256,
        ]));

        return ['data' => $this->queries->item($c)];
    }

    /** @return array{0: ?CollateralType} */
    private function typeOf(Collateral $c): array
    {
        try {
            return [$this->file->type($c->type_code)[0]];
        } catch (ValidationFailed) {
            return [null];
        }
    }
}
