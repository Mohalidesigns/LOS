<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Domain;

/**
 * The standard role library (BRD §12.3, TRD §1.3, LOS-FR-278): 19 templates
 * shipped to every tenant as clonable, tenant-modifiable templates. Templates
 * are not assignable themselves; a tenant clones one into an assignable role.
 */
final class RoleLibrary
{
    /** @return array<string, array{name: string, description: string, permissions: list<Permission>}> */
    public static function templates(): array
    {
        $read = [
            Permission::LegalEntityRead, Permission::OrgUnitRead, Permission::ConfigRead, Permission::UserRead,
            Permission::RoleRead, Permission::PermissionRead, Permission::RoleAssignmentRead, Permission::SodRuleRead,
            Permission::DelegationRead, Permission::ChangeRequestRead, Permission::AuditRead, Permission::LicenceRead,
            Permission::IntegrationRead, Permission::ApplicationView, Permission::ReportView,
        ];

        return [
            'loan_officer' => ['name' => 'Loan Officer / RM', 'description' => 'Captures and originates applications for own customers.', 'permissions' => [
                Permission::ApplicationView, Permission::ApplicationOriginate, Permission::PartyManage, Permission::DocumentUpload, Permission::ReportView,
                Permission::LegalEntityRead, Permission::OrgUnitRead,
            ]],
            'documentation_officer' => ['name' => 'Documentation Officer', 'description' => 'Collects and verifies application documents.', 'permissions' => [
                Permission::ApplicationView, Permission::DocumentUpload, Permission::DocumentVerify,
            ]],
            'branch_manager' => ['name' => 'Branch Manager', 'description' => 'Oversees branch pipeline, reassigns work.', 'permissions' => [
                Permission::ApplicationView, Permission::ApplicationRecommend, Permission::TaskReassign, Permission::ReportView, Permission::DelegationCreate, Permission::DocumentWaiveApprove,
            ]],
            'credit_analyst' => ['name' => 'Credit Analyst', 'description' => 'Assesses credit risk and prepares the credit memo.', 'permissions' => [
                Permission::ApplicationView, Permission::CreditAnalyse, Permission::BureauPull,
            ]],
            'senior_credit_analyst' => ['name' => 'Senior Credit Analyst', 'description' => 'Reviews analysis, recommends and raises exceptions.', 'permissions' => [
                Permission::ApplicationView, Permission::CreditAnalyse, Permission::BureauPull, Permission::ApplicationRecommend, Permission::ExceptionRaise, Permission::DocumentWaiveApprove,
            ]],
            'credit_approver' => ['name' => 'Credit Approver', 'description' => 'Approves within delegated authority (tiered via scope max amount).', 'permissions' => [
                Permission::ApplicationView, Permission::ApplicationApprove, Permission::DelegationCreate,
            ]],
            'credit_committee_member' => ['name' => 'Credit Committee Member', 'description' => 'Votes on committee-level approvals.', 'permissions' => [
                Permission::ApplicationView, Permission::ApplicationApprove,
            ]],
            'compliance_officer' => ['name' => 'Compliance Officer', 'description' => 'Clears screening alerts and oversees compliance gates.', 'permissions' => [
                Permission::ApplicationView, Permission::ScreeningReview, Permission::ScreeningClear, Permission::PiiUnmask, Permission::PartyViewIdentityNumbers, Permission::AuditRead,
            ]],
            'aml_analyst' => ['name' => 'AML Analyst', 'description' => 'Reviews screening alerts (first line).', 'permissions' => [
                Permission::ApplicationView, Permission::ScreeningReview,
            ]],
            'legal_officer' => ['name' => 'Legal Officer', 'description' => 'Reviews security documents and prepares offers.', 'permissions' => [
                Permission::ApplicationView, Permission::LegalReview, Permission::OfferPrepare,
            ]],
            'collateral_officer' => ['name' => 'Collateral Officer', 'description' => 'Manages collateral, valuations and perfection.', 'permissions' => [
                Permission::ApplicationView, Permission::CollateralManage,
            ]],
            'disbursement_maker' => ['name' => 'Disbursement Maker', 'description' => 'Prepares disbursement instructions.', 'permissions' => [
                Permission::ApplicationView, Permission::DisbursementMake,
            ]],
            'disbursement_checker' => ['name' => 'Disbursement Checker', 'description' => 'Releases disbursements (four-eyes).', 'permissions' => [
                Permission::ApplicationView, Permission::DisbursementCheck,
            ]],
            'product_manager' => ['name' => 'Product Manager', 'description' => 'Designs products and drafts configuration.', 'permissions' => [
                Permission::ProductManage, Permission::ConfigRead, Permission::ConfigAuthor, Permission::ConfigActivateRequest,
            ]],
            'tenant_administrator' => ['name' => 'Tenant Administrator', 'description' => 'Administers organisation, users, roles and configuration. Every change it makes to access or configuration requires a second administrator to approve.', 'permissions' => [
                Permission::LegalEntityRead, Permission::LegalEntityManage, Permission::OrgUnitRead, Permission::OrgUnitManage,
                Permission::ConfigRead, Permission::ConfigAuthor, Permission::ConfigReview, Permission::ConfigActivateRequest, Permission::ConfigActivateApprove,
                Permission::UserRead, Permission::UserManage, Permission::UserManageTokens,
                Permission::RoleRead, Permission::RoleManage, Permission::RoleApprove, Permission::PermissionRead,
                Permission::RoleAssignmentRead, Permission::RoleAssignmentRequest, Permission::RoleAssignmentApprove,
                Permission::SodRuleRead, Permission::SodRuleManage, Permission::DelegationRead, Permission::DelegationCreate,
                Permission::ChangeRequestRead, Permission::AuditRead, Permission::AuditVerify, Permission::LicenceRead, Permission::LicenceImportRequest,
                Permission::LicenceImportApprove, Permission::IntegrationRead,
            ]],
            'auditor' => ['name' => 'Auditor', 'description' => 'Read-only, unrestricted scope, no operational actions.', 'permissions' => array_merge($read, [Permission::AuditVerify])],
            'regulator_examiner' => ['name' => 'Regulator / Examiner', 'description' => 'Read-only access for examinations; assign with a validity end date.', 'permissions' => [
                Permission::ApplicationView, Permission::AuditRead, Permission::ReportView, Permission::LegalEntityRead, Permission::OrgUnitRead, Permission::ConfigRead,
            ]],
            'partner_dsa' => ['name' => 'Partner / DSA', 'description' => 'Submits applications through the partner API; restricted to own submissions.', 'permissions' => [
                Permission::PartnerSubmit, Permission::ApplicationOriginate,
            ]],
            'platform_operator' => ['name' => 'Platform Operator', 'description' => 'Vendor support: licence and integration diagnostics only (D-030). No business data.', 'permissions' => [
                Permission::LicenceRead, Permission::LicenceImportRequest, Permission::IntegrationRead,
            ]],
        ];
    }

    /**
     * Default SoD rules seeded per tenant (TRD §8.1 examples).
     *
     * @return list<array{kind: string, left: string, right: string, description: string}>
     */
    public static function defaultSodRules(): array
    {
        return [
            ['kind' => 'permission_pair', 'left' => Permission::DisbursementMake->value, 'right' => Permission::DisbursementCheck->value, 'description' => 'Disbursement maker and checker must be different people.'],
            ['kind' => 'permission_pair', 'left' => Permission::ApplicationOriginate->value, 'right' => Permission::ScreeningClear->value, 'description' => 'Originators may not clear screening alerts (FR-CMP-014).'],
        ];
    }
}
