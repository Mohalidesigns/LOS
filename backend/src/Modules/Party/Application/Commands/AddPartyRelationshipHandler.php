<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Application\Commands;

use Brick\Math\BigDecimal;
use Fundly\Modules\Party\Application\PartyQueries;
use Fundly\Modules\Party\Domain\PartyType;
use Fundly\Modules\Party\Domain\RelationshipRole;
use Fundly\Modules\Party\Infrastructure\Models\Party;
use Fundly\Modules\Party\Infrastructure\Models\PartyRelationship;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\CodedConflict;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Exceptions\ValidationFailed;

final class AddPartyRelationshipHandler implements CommandHandler
{
    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof AddPartyRelationship);
        $company = Party::query()->lockForUpdate()->find($command->partyId) ?? throw new NotFound('Party not found.');
        $related = Party::query()->find($command->relatedPartyId) ?? throw ValidationFailed::with(['related_party_id' => 'Unknown party.']);
        if ($company->type !== PartyType::LimitedCompany->value) {
            throw new DomainRuleViolation('Relationships of this kind can only be recorded on a limited company.');
        }
        if ($related->id === $company->id) {
            throw ValidationFailed::with(['related_party_id' => 'A party cannot be related to itself.']);
        }
        $role = RelationshipRole::from($command->role);
        if ($role->carriesOwnership() && $command->ownershipPercent === null) {
            throw ValidationFailed::with(['ownership_percent' => 'Ownership percentage is required for shareholders and beneficial owners.']);
        }
        if (! $role->carriesOwnership() && $command->ownershipPercent !== null) {
            throw ValidationFailed::with(['ownership_percent' => 'Only shareholders and beneficial owners carry an ownership percentage.']);
        }
        if (in_array($role, [RelationshipRole::Director, RelationshipRole::BeneficialOwner, RelationshipRole::CompanySecretary], true) && $related->type !== PartyType::Individual->value) {
            throw new DomainRuleViolation("A {$role->value} must be an individual; record corporate holders as shareholders.");
        }
        if (PartyRelationship::query()->where('party_id', $company->id)->where('related_party_id', $related->id)->where('role', $role->value)->exists()) {
            throw new CodedConflict('party-relationship-exists', 'This relationship is already recorded.');
        }
        if ($command->ownershipPercent !== null) {
            $pct = BigDecimal::of($command->ownershipPercent);
            if ($pct->isNegativeOrZero() || $pct->isGreaterThan(100)) {
                throw ValidationFailed::with(['ownership_percent' => 'Ownership must be greater than 0 and at most 100.']);
            }
            // Direct shareholdings may not exceed 100%; beneficial-owner declarations are look-through and are not summed.
            if ($role === RelationshipRole::Shareholder) {
                $existing = BigDecimal::zero();
                foreach (PartyRelationship::query()->where('party_id', $company->id)->where('role', 'shareholder')->pluck('ownership_percent') as $v) {
                    $existing = $existing->plus(BigDecimal::of((string) $v));
                }
                if ($existing->plus($pct)->isGreaterThan(100)) {
                    throw new DomainRuleViolation('Direct shareholdings would exceed 100%.', ['allocated_percent' => (string) $existing]);
                }
            }
        }

        $rel = new PartyRelationship;
        $rel->forceFill([
            'party_id' => $company->id,
            'related_party_id' => $related->id,
            'role' => $role->value,
            'ownership_percent' => $command->ownershipPercent,
            'notes' => $command->notes,
            'created_by' => $context->principal->id,
        ])->save();
        $data = PartyQueries::relationship($rel->refresh(), $related);
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'party', entityId: $company->id, after: $data));

        return ['data' => $data];
    }
}
