<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application\Commands;

use Fundly\Modules\Credit\Application\CreditFileLoader;
use Fundly\Modules\Credit\Application\CreditPresenter;
use Fundly\Modules\Credit\Application\MemoComposer;
use Fundly\Modules\Credit\Infrastructure\Models\CreditMemo;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Illuminate\Support\Facades\DB;

final class SaveCreditMemoHandler implements CommandHandler
{
    public function __construct(private readonly CreditFileLoader $file, private readonly MemoComposer $composer) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof SaveCreditMemo);
        $app = $this->file->application($command->applicationId);
        $decision = $this->file->latestDecision($app->id) ?? throw new DomainRuleViolation('Run the credit decision before writing the memo.');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['memo:'.$app->id]);
        $memo = new CreditMemo;
        $memo->forceFill([
            'application_id' => $app->id,
            'version_no' => (int) CreditMemo::query()->where('application_id', $app->id)->max('version_no') + 1,
            'decision_id' => $decision->id,
            'sections' => $this->composer->sections($app, $decision),
            'narrative' => (string) $command->narrative,
            'recommendation' => $command->recommendation,
            'recommended_amount' => $command->recommendedAmount,
            'recommended_tenor_months' => $command->recommendedTenorMonths,
            'conditions' => array_values($command->conditions),
            'authored_by' => $context->principal->id,
            'authored_at' => $this->file->now(),
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $app->id, after: [
            'memo_id' => $memo->id, 'version_no' => $memo->version_no, 'decision_id' => $decision->id, 'recommendation' => $memo->recommendation,
        ]));

        return ['data' => CreditPresenter::memo($memo->refresh(), $app->requestedAmount->currency->code ?? 'NGN')];
    }
}
