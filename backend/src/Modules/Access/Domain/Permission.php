<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Domain;

/**
 * The code-defined permission catalogue (TRD §8.1). Tenants bundle these into
 * roles; they cannot invent new ones. Permissions for P1+ modules are
 * declared now so the standard role library has its intended shape; they
 * guard nothing until those modules exist.
 */
enum Permission: string
{
    // Platform (M01)
    case LegalEntityRead = 'legal_entity:read';
    case LegalEntityManage = 'legal_entity:manage';
    case OrgUnitRead = 'org_unit:read';
    case OrgUnitManage = 'org_unit:manage';
    case ConfigRead = 'config:read';
    case ConfigAuthor = 'config:author';
    case ConfigReview = 'config:review';
    case ConfigActivateRequest = 'config:activate_request';
    case ConfigActivateApprove = 'config:activate_approve';

    // Identity & access (M02)
    case UserRead = 'user:read';
    case UserManage = 'user:manage';
    case UserManageTokens = 'user:manage_tokens';
    case RoleRead = 'role:read';
    case RoleManage = 'role:manage';
    case RoleApprove = 'role:approve';
    case PermissionRead = 'permission:read';
    case RoleAssignmentRead = 'role_assignment:read';
    case RoleAssignmentRequest = 'role_assignment:request';
    case RoleAssignmentApprove = 'role_assignment:approve';
    case SodRuleRead = 'sod_rule:read';
    case SodRuleManage = 'sod_rule:manage';
    case DelegationRead = 'delegation:read';
    case DelegationCreate = 'delegation:create';
    case ChangeRequestRead = 'change_request:read';

    // Audit (M19)
    case AuditRead = 'audit:read';
    case AuditVerify = 'audit:verify';

    // Licensing (M21)
    case LicenceRead = 'licence:read';
    case LicenceImportRequest = 'licence:import_request';
    case LicenceImportApprove = 'licence:import_approve';

    // Integration runtime (M17)
    case IntegrationRead = 'integration:read';

    // ---- P1+ business permissions (catalogue only in P0) ----
    case ApplicationView = 'application:view';
    case ApplicationOriginate = 'application:originate';
    case ApplicationRecommend = 'application:recommend';
    case ApplicationApprove = 'application:approve';
    case PartyManage = 'party:manage';
    case PartyViewIdentityNumbers = 'party:view_identity_numbers';
    case PiiUnmask = 'pii:unmask';
    case DocumentUpload = 'document:upload';
    case DocumentVerify = 'document:verify';
    case CreditAnalyse = 'credit:analyse';
    case BureauPull = 'bureau:pull';
    case ExceptionRaise = 'exception:raise';
    case ScreeningReview = 'screening:review';
    case ScreeningClear = 'screening:clear';
    case CollateralManage = 'collateral:manage';
    case LegalReview = 'legal:review';
    case OfferPrepare = 'offer:prepare';
    case DisbursementMake = 'disbursement:make';
    case DisbursementCheck = 'disbursement:check';
    case ProductManage = 'product:manage';
    case ProductActivate = 'product:activate';
    case TaskReassign = 'task:reassign';
    case ReportView = 'report:view';
    case PartnerSubmit = 'partner:submit';

    public function resource(): string
    {
        return explode(':', $this->value, 2)[0];
    }

    public function actionName(): string
    {
        return explode(':', $this->value, 2)[1];
    }

    public function module(): string
    {
        return match ($this->resource()) {
            'legal_entity', 'org_unit', 'config' => 'platform',
            'user', 'role', 'permission', 'role_assignment', 'sod_rule', 'delegation', 'change_request' => 'access',
            'audit' => 'audit',
            'licence' => 'licensing',
            'integration' => 'integration',
            default => 'origination',
        };
    }

    /** Sensitive permissions are highlighted in effective-access and assignment reviews. */
    public function isSensitive(): bool
    {
        return in_array($this, [
            self::RoleAssignmentApprove, self::RoleApprove, self::ConfigActivateApprove, self::LicenceImportApprove,
            self::UserManageTokens, self::PiiUnmask, self::PartyViewIdentityNumbers, self::DisbursementCheck,
            self::ApplicationApprove, self::ScreeningClear, self::SodRuleManage,
        ], true);
    }

    public function description(): string
    {
        return ucfirst(str_replace('_', ' ', $this->actionName())).' '.str_replace('_', ' ', $this->resource());
    }

    /** Read-only permissions (used for the auditor fail-safe and read-only templates). */
    public function isReadOnly(): bool
    {
        return in_array($this->actionName(), ['read', 'view'], true);
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }
}
