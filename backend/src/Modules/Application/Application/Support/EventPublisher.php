<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application\Support;

use Fundly\Modules\Application\Contracts\Events\ApplicationCreated;
use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Application\Domain\Application;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\CommandContext;

/**
 * Turns appended aggregate events into audit entries (one per state change,
 * DoD §5.6) and public domain events raised after commit.
 */
final class EventPublisher
{
    /** @param list<array{type: string, payload: array<string, mixed>, version: int}> $written */
    public static function publish(CommandContext $context, Application $app, array $written, ?string $stepUpRef = null): void
    {
        $tenant = $context->principal->tenantId;
        foreach ($written as $e) {
            $p = $e['payload'];
            switch ($e['type']) {
                case 'application.created':
                    $context->audit(new AuditEntry(action: 'application.created', entityType: 'application', entityId: $app->id(), after: [
                        'reference' => $app->reference(), 'product_version_id' => $p['product_version_id'] ?? null, 'channel' => $p['channel'] ?? null,
                        'primary_party_id' => $p['primary_party_id'] ?? null, 'requested_amount' => $p['requested_amount'] ?? null, 'currency' => $p['currency'] ?? null,
                        'version' => $e['version'],
                    ]));
                    $context->raise(new ApplicationCreated($tenant, $app->id(), $app->reference(), (string) ($p['product_version_id'] ?? ''), (string) ($p['originator_id'] ?? '')));
                    break;
                case 'application.amended':
                    $before = [];
                    $after = [];
                    foreach ((array) $p['changes'] as $path => $change) {
                        $before[(string) $path] = is_array($change) ? ($change['from'] ?? null) : null;
                        $after[(string) $path] = is_array($change) ? ($change['to'] ?? null) : null;
                    }
                    $context->audit(new AuditEntry(action: 'application.amended', entityType: 'application', entityId: $app->id(), before: $before, after: $after + ['source' => $p['source'] ?? null, 'version' => $e['version']]));
                    break;
                case 'application.applicant_added':
                case 'application.applicant_removed':
                    $context->audit(new AuditEntry(action: $e['type'], entityType: 'application', entityId: $app->id(), after: ['party_id' => $p['party_id'] ?? null, 'role' => $p['role'] ?? null, 'version' => $e['version']]));
                    break;
                case 'application.status_changed':
                    $context->audit(new AuditEntry(
                        action: 'application.status_changed',
                        entityType: 'application',
                        entityId: $app->id(),
                        before: ['status' => $p['from'] ?? null],
                        after: ['status' => $p['to'] ?? null, 'action' => $p['action'] ?? null, 'return_to' => $p['return_to'] ?? null, 'version' => $e['version']],
                        reasonCode: is_string($p['reason_code'] ?? null) ? $p['reason_code'] : null,
                        reasonText: is_string($p['reason_text'] ?? null) ? $p['reason_text'] : null,
                        stepUpRef: $stepUpRef,
                    ));
                    $context->raise(new ApplicationStatusChanged(
                        $tenant, $app->id(), $app->reference(), (string) $p['from'], (string) $p['to'],
                        is_string($p['action'] ?? null) ? $p['action'] : null,
                        is_string($p['reason_code'] ?? null) ? $p['reason_code'] : null,
                        $context->principal->id, $e['version'],
                    ));
                    break;
            }
        }
    }
}
