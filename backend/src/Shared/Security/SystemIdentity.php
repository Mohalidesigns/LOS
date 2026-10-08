<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

/**
 * Named identities for automated actions (FR-AUD-005). Automated work is
 * always attributed to one of these, never to a generic actor.
 */
enum SystemIdentity: string
{
    case Installer = 'system:installer';
    case Outbox = 'system:outbox';
    case Scheduler = 'system:scheduler';
    case AuditCheckpoint = 'system:audit-checkpoint';
    case AuditVerifier = 'system:audit-verifier';
    case Licensing = 'system:licensing';
    case IntegrationRuntime = 'system:integration-runtime';
    case RulesEngine = 'system:rules-engine';
    case Workflow = 'system:workflow';
}
