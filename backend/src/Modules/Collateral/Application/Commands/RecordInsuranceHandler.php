<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Modules\Collateral\Application\CollateralFile;
use Fundly\Modules\Collateral\Application\CollateralQueries;
use Fundly\Modules\Collateral\Infrastructure\Models\InsurancePolicy;
use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Money\Money;

final class RecordInsuranceHandler implements CommandHandler
{
    public function __construct(private readonly CollateralFile $file, private readonly DocumentArchive $archive, private readonly CollateralQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RecordInsurance);
        $c = $this->file->collateral($command->collateralId);
        $app = $this->file->application($c->origin_application_id);
        $this->file->assertOpen($app);
        $sum = Money::of((string) $command->sumInsured, $c->currency);
        $doc = $command->tempPath === null ? null
            : $this->archive->storeUploaded($app->id, 'INSURANCE_POLICY', 'Insurance: '.mb_substr($c->description, 0, 120), $command->tempPath, (string) $command->originalName, $context->principal->id);
        $p = new InsurancePolicy;
        $p->forceFill([
            'collateral_id' => $c->id, 'policy_no' => trim((string) $command->policyNo), 'insurer' => trim((string) $command->insurer),
            'sum_insured' => $sum->toStorage(), 'currency' => $c->currency, 'start_date' => $command->startDate, 'expiry_date' => $command->expiryDate,
            'bank_interest_noted' => $command->bankInterestNoted, 'document_id' => $doc?->documentId, 'document_version_id' => $doc?->versionId,
            'recorded_by' => $context->principal->id, 'recorded_at' => $this->file->now(),
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'collateral', entityId: $c->id, after: [
            'policy_id' => $p->id, 'policy_no' => $p->policy_no, 'insurer' => $p->insurer, 'sum_insured' => $sum->toArray(),
            'start_date' => $command->startDate, 'expiry_date' => $command->expiryDate, 'bank_interest_noted' => $p->bank_interest_noted, 'document_sha256' => $doc?->sha256,
        ]));

        return ['data' => $this->queries->item($c)];
    }
}
