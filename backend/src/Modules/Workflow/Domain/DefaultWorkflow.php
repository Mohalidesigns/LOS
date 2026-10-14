<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Domain;

/**
 * The built-in workflow used when a product's bound workflow has no active
 * tenant version. SLAs are indicative business hours [PO to confirm]; the
 * escalation goes to holders of task:reassign (branch manager template).
 */
final class DefaultWorkflow
{
    /** @return array<string, mixed> */
    public static function standard(): array
    {
        $stage = static fn (string $code, string $name, string $status, ?string $permission = null, string $strategy = 'queue', ?int $warn = null, ?int $breach = null): array => array_filter([
            'code' => $code, 'name' => $name, 'status' => $status,
            'assignment' => $permission === null ? null : ['permission' => $permission, 'strategy' => $strategy],
            'sla' => $warn === null ? null : ['warn_hours' => $warn, 'breach_hours' => (int) $breach, 'escalate_to' => 'task:reassign'],
        ], static fn (mixed $v): bool => $v !== null);

        return [
            'name' => 'Standard lending workflow (built-in)',
            'business_hours' => ['timezone' => 'Africa/Lagos', 'days' => [1, 2, 3, 4, 5], 'start' => '08:00', 'end' => '17:00'],
            'stages' => [
                ['code' => 'intake', 'name' => 'Intake', 'status' => 'submitted', 'automation' => 'advance'],
                ['code' => 'prequalification', 'name' => 'Pre-qualification', 'status' => 'pre_qualified', 'automation' => 'advance'],
                $stage('kyc', 'KYC and screening', 'kyc_screening', 'application:originate', 'originator', 12, 16),
                $stage('documentation', 'Documentation', 'documentation', 'document:verify', 'queue', 18, 24),
                $stage('assessment', 'Credit assessment', 'assessment', 'credit:analyse', 'least_loaded', 12, 16),
                $stage('recommended', 'Routing to approval', 'recommended'),
                $stage('approval', 'Approval', 'approval', 'application:approve', 'queue', 12, 16),
                $stage('offer_preparation', 'Offer preparation', 'approved', 'offer:prepare', 'queue', 6, 8),
                $stage('counter_offer', 'Counter-offer', 'counter_offered', 'offer:prepare', 'queue', 6, 8),
                $stage('offer_follow_up', 'Offer with customer', 'offer_issued', 'application:originate', 'originator', 30, 40),
                $stage('acceptance', 'Accepted', 'accepted', 'condition:manage', 'queue', 6, 8),
                $stage('conditions', 'Conditions precedent', 'conditions_precedent', 'condition:manage', 'queue', 30, 40),
                $stage('disbursement', 'Ready for disbursement', 'ready_for_disbursement', 'disbursement:make', 'queue', 6, 8),
                $stage('release', 'Disbursement release', 'disbursing', 'disbursement:check', 'queue', 3, 4),
                $stage('rework', 'Returned for rework', 'returned_for_rework', 'application:originate', 'originator', 12, 16),
            ],
        ];
    }
}
