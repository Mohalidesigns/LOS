<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Application;

use Fundly\Integration\Ports\Licensing\LicensingPort;
use Fundly\Shared\Clock\Clock;
use Illuminate\Database\ConnectionInterface;

final class LicenceStatusQuery
{
    public function __construct(
        private readonly LicensingPort $port,
        private readonly LicenceGuard $guard,
        private readonly Clock $clock,
        private readonly ConnectionInterface $db,
    ) {
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $licence = $this->port->currentLicence();
        $now = $this->clock->now();

        return [
            'state' => $this->guard->state()->value,
            'installation_fingerprint' => $this->port->installationFingerprint(),
            'licence' => $licence?->toArray(),
            'days_until_expiry' => $licence?->daysUntilExpiry($now),
            'named_users' => [
                'active' => $this->db->table('users')->where('kind', 'human')->where('status', 'active')->count(),
                'max' => $licence?->maxNamedUsers,
            ],
        ];
    }

    /** @return array<string, string> */
    public function activationRequest(): array
    {
        return $this->port->activationRequest();
    }
}
