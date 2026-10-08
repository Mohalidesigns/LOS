<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Licensing;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The signed licence document (TRD §2.6, D-034). Immutable value; validity is
 * evaluated against a clock, never cached as a flag.
 */
final readonly class Licence
{
    /**
     * @param  list<string>  $modules
     * @param  list<string>  $adapterEntitlements
     */
    public function __construct(
        public string $licenceId,
        public string $client,
        public string $installationFingerprint,
        public string $edition,
        public array $modules,
        public ?int $maxNamedUsers,
        public ?int $maxLegalEntities,
        public array $adapterEntitlements,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validTo,
        public int $graceDays,
        public string $supportTier,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        foreach (['licence_id', 'client', 'installation_fingerprint', 'edition', 'modules', 'valid_from', 'valid_to', 'grace_days', 'support_tier'] as $required) {
            if (! array_key_exists($required, $d)) {
                throw new InvalidArgumentException("Licence field {$required} is missing.");
            }
        }
        $list = static fn (mixed $v): array => is_array($v) ? array_values(array_map('strval', array_filter($v, 'is_scalar'))) : [];

        return new self(
            licenceId: (string) $d['licence_id'],
            client: (string) $d['client'],
            installationFingerprint: (string) $d['installation_fingerprint'],
            edition: (string) $d['edition'],
            modules: $list($d['modules']),
            maxNamedUsers: isset($d['max_named_users']) && is_int($d['max_named_users']) ? $d['max_named_users'] : null,
            maxLegalEntities: isset($d['max_legal_entities']) && is_int($d['max_legal_entities']) ? $d['max_legal_entities'] : null,
            adapterEntitlements: $list($d['adapter_entitlements'] ?? []),
            validFrom: new DateTimeImmutable((string) $d['valid_from']),
            validTo: new DateTimeImmutable((string) $d['valid_to']),
            graceDays: (int) $d['grace_days'],
            supportTier: (string) $d['support_tier'],
        );
    }

    public function state(DateTimeImmutable $now): LicenceState
    {
        if ($now < $this->validFrom) {
            return LicenceState::NotYetValid;
        }
        if ($now <= $this->validTo) {
            return LicenceState::Valid;
        }
        $graceEnd = $this->validTo->modify('+'.$this->graceDays.' days');

        return $now <= $graceEnd ? LicenceState::Grace : LicenceState::Expired;
    }

    public function daysUntilExpiry(DateTimeImmutable $now): int
    {
        return intdiv($this->validTo->getTimestamp() - $now->getTimestamp(), 86400);
    }

    public function entitles(string $module): bool
    {
        return in_array($module, $this->modules, true) || in_array('*', $this->modules, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'licence_id' => $this->licenceId,
            'client' => $this->client,
            'installation_fingerprint' => $this->installationFingerprint,
            'edition' => $this->edition,
            'modules' => $this->modules,
            'max_named_users' => $this->maxNamedUsers,
            'max_legal_entities' => $this->maxLegalEntities,
            'adapter_entitlements' => $this->adapterEntitlements,
            'valid_from' => $this->validFrom->format(DATE_ATOM),
            'valid_to' => $this->validTo->format(DATE_ATOM),
            'grace_days' => $this->graceDays,
            'support_tier' => $this->supportTier,
        ];
    }
}
