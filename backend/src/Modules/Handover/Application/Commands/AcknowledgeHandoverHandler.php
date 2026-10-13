<?php

declare(strict_types=1);

namespace Fundly\Modules\Handover\Application\Commands;

use Fundly\Modules\Handover\Application\HandoverQueries;
use Fundly\Modules\Handover\Infrastructure\Models\HandoverAcknowledgement;
use Fundly\Modules\Handover\Infrastructure\Models\HandoverPackage;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;

final class AcknowledgeHandoverHandler implements CommandHandler
{
    public function __construct(private readonly Clock $clock, private readonly HandoverQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof AcknowledgeHandover);
        $p = HandoverPackage::query()->find($command->packageId) ?? throw new NotFound('Handover package not found.');
        if (HandoverAcknowledgement::query()->where('package_id', $p->id)->exists()) {
            throw new DomainRuleViolation('This handover has already been acknowledged.');
        }
        (new HandoverAcknowledgement)->forceFill(['package_id' => $p->id, 'acknowledged_by' => $context->principal->id, 'note' => (string) $command->note, 'acknowledged_at' => $this->clock->now()])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'handover_package', entityId: $p->id, after: ['sha256' => $p->sha256], reasonText: $command->note));

        return ['data' => $this->queries->package($p)];
    }
}
