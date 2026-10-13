<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Application\Commands;

use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Approval\Application\ApprovalFile;
use Fundly\Modules\Approval\Contracts\Events\ApprovalDecided;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;

final class ExpireApprovalHandler implements CommandHandler
{
    public function __construct(private readonly ApprovalFile $file, private readonly ApplicationLifecycle $lifecycle) {}

    public function handle(Command $command, CommandContext $context): null
    {
        assert($command instanceof ExpireApproval);
        $r = $this->file->request($command->requestId);
        $until = $r->valid_until;
        if ($r->status !== 'approved' || $until === null || $until->greaterThan($this->file->now())) {
            throw new DomainRuleViolation('Only an approval past its validity can expire.');
        }
        $app = $this->file->application($r->application_id);
        $r->forceFill(['status' => 'expired'])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'approval_request', entityId: $r->id, before: ['status' => 'approved'], after: ['status' => 'expired', 'valid_until' => $until->toIso8601String()]));
        // still Approved means no offer went out on it; later states carry their own validity (offer expiry)
        if ($app->status === CanonicalStatus::Approved) {
            $this->lifecycle->advance($app->id, CanonicalStatus::Expired, 'APPROVAL_VALIDITY_LAPSED', 'Approval validity ended '.$until->toDateString().'; re-approval is required.', $context->principal);
        }
        $context->raise(new ApprovalDecided($context->principal->tenantId, $app->id, $r->id, 'expired', null));

        return null;
    }
}
