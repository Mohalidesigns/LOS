<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application;

use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Compliance\Contracts\Events\ApplicationScreened;
use Fundly\Modules\Disbursement\Contracts\DisbursementRegister;
use Fundly\Modules\Disbursement\Contracts\Events\DisbursementAwaitingRelease;
use Fundly\Modules\Disbursement\Contracts\Events\FacilityBooked;
use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Modules\Notification\Application\Commands\Notify;
use Fundly\Modules\Notification\Domain\Format;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Turns other modules' events into notifications. A notification failure
 * never affects the business change that caused it: it is logged.
 */
final class NotificationTriggers
{
    private const FAILURE_REASONS = ['DISBURSEMENT_FAILED_RETRYABLE', 'DISBURSEMENT_NEEDS_INTERVENTION', 'DISBURSEMENT_COMPENSATED'];

    public function __construct(
        private readonly CommandBus $bus,
        private readonly TenantContext $tenant,
        private readonly DisbursementRegister $disbursements,
        private readonly DocumentArchive $documents,
        private readonly LoggerInterface $log,
    ) {}

    public function onStatusChanged(ApplicationStatusChanged $e): void
    {
        $event = match (true) {
            $e->to === CanonicalStatus::Submitted->value => 'application.received',
            $e->to === CanonicalStatus::Approval->value => 'approval.awaiting',
            $e->to === CanonicalStatus::OfferIssued->value => 'offer.issued',
            $e->to === CanonicalStatus::ReadyForDisbursement->value => 'disbursement.ready',
            $e->to === CanonicalStatus::Disbursing->value && in_array($e->reasonCode, self::FAILURE_REASONS, true) => 'disbursement.failed',
            default => null,
        };
        if ($event !== null) {
            $this->notify($e->tenantId, $event, $e->applicationId, "v{$e->version}", ['error_code' => (string) $e->reasonCode]);
        }
    }

    public function onScreened(ApplicationScreened $e): void
    {
        if ($e->hits > 0) {
            $this->notify($e->tenantId, 'screening.alert', $e->applicationId, 'hits-'.$e->hits.'-'.substr(sha1((string) microtime()), 0, 8), []);
        }
    }

    public function onAwaitingRelease(DisbursementAwaitingRelease $e): void
    {
        $this->notify($e->tenantId, 'disbursement.awaiting_release', $e->applicationId, $e->disbursementId, ['exclude_user_ids' => [$e->makerId]]);
    }

    public function onBooked(FacilityBooked $e): void
    {
        $this->tenant->run($e->tenantId, function () use ($e): void {
            $facility = $this->disbursements->booked($e->applicationId);
            $attachments = [];
            if ($facility?->adviceDocumentId !== null) {
                foreach ($this->documents->references($e->applicationId) as $ref) {
                    if ($ref->documentId === $facility->adviceDocumentId) {
                        $attachments[] = ['document_id' => $ref->documentId, 'version_id' => $ref->versionId, 'filename' => $ref->filename];
                    }
                }
            }
            $vars = $facility === null ? [] : [
                'loan_account_no' => $facility->loanAccountNo,
                'net_amount' => Format::money((string) $facility->net->amount, $facility->net->currency->code),
                'value_date' => $facility->valueDate,
            ];
            // FR-DSB-011: the advice and final schedule go to the customer
            $this->dispatch($e->tenantId, 'facility.booked', $e->applicationId, $e->disbursementId, $vars + ['attachments' => $attachments]);
            $this->dispatch($e->tenantId, 'facility.booked.staff', $e->applicationId, $e->disbursementId, $vars);
        });
    }

    /** @param array<string, mixed> $context */
    public function notify(string $tenantId, string $event, string $applicationId, string $discriminator, array $context): void
    {
        $this->tenant->run($tenantId, fn () => $this->dispatch($tenantId, $event, $applicationId, $discriminator, $context));
    }

    /** @param array<string, mixed> $context */
    private function dispatch(string $tenantId, string $event, string $applicationId, string $discriminator, array $context): void
    {
        try {
            $this->bus->dispatch(new Notify($event, $applicationId, $discriminator, $context), Principal::system($tenantId, SystemIdentity::Workflow));
        } catch (Throwable $ex) {
            $this->log->error('Notification failed', ['event' => $event, 'application_id' => $applicationId, 'error' => $ex->getMessage()]);
        }
    }
}
