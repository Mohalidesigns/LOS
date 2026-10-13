<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Approval\Application\ApprovalFile;
use Fundly\Modules\Approval\Application\ApprovalProgression;
use Fundly\Modules\Approval\Console\ExpireApprovalsCommand;
use Fundly\Modules\Approval\Contracts\ApprovalRegister;
use Fundly\Modules\Approval\Domain\AuthorityLimits;
use Fundly\Modules\Approval\Domain\MatrixDefinition;
use Fundly\Modules\Approval\Infrastructure\DatabaseApprovalRegister;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalRequest;
use Fundly\Modules\Platform\Contracts\ConfigTypeCatalogue;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

/** M11 Approvals: matrix routing, authority limits, approver SoD, votes with conditions, validity (FR-APV-001..012). */
final class ApprovalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ApprovalFile::class);
        $this->app->scoped(ApprovalRegister::class, DatabaseApprovalRegister::class);
    }

    public function boot(ConfigTypeCatalogue $types, ResourceResolver $resources, Dispatcher $events): void
    {
        $types->register(
            ApprovalFile::MATRIX_TYPE,
            'Approval matrix: authority levels and a routing decision table over amount, grade, segment and exceptions, with escalation and step-up rules (FR-APV-001/003/007).',
            static function (array $content): void {
                MatrixDefinition::validate($content);
            },
        );
        $types->register(
            ApprovalFile::LIMITS_TYPE,
            'Approval authority limits per role, org unit and user; the effective limit is the most restrictive that applies (FR-APV-002).',
            static function (array $content): void {
                AuthorityLimits::validate($content);
            },
        );
        $app = $this->app;
        // amount is deliberately left out: approval authority is decided by effective limits, not by scope matching
        $resources->register('approval_request', static function (string $id) use ($app): ?ResourceAttributes {
            $r = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? ApprovalRequest::query()->find($id) : null;
            $a = $r === null ? null : $app->make(ApplicationReader::class)->find($r->application_id);

            return $r === null || $a === null ? null : new ResourceAttributes(legalEntityId: $a->legalEntityId, orgUnitId: $a->orgUnitId, productId: $a->productId, segment: $a->segment, entityType: 'approval_request', entityId: $r->id);
        });
        $events->listen(ApplicationStatusChanged::class, [ApprovalProgression::class, 'onStatusChanged']);
        if ($this->app->runningInConsole()) {
            $this->commands([ExpireApprovalsCommand::class]);
        }
    }
}
