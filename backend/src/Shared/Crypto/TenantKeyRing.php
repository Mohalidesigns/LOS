<?php

declare(strict_types=1);

namespace Fundly\Shared\Crypto;

use Fundly\Integration\Ports\KeyManagement\KeyManagementPort;
use Fundly\Integration\Ports\KeyManagement\WrappedKey;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Per-tenant data keys (tenant-level key separation, FR-SEC-016). Keys are
 * created lazily, stored wrapped by the KEK, and unwrapped into memory only.
 */
final class TenantKeyRing
{
    /** @var array<string, string> cache key "tenant|purpose|version" => raw key */
    private array $cache = [];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
        private readonly KeyManagementPort $kms,
        private readonly Clock $clock,
    ) {
    }

    /** @return array{version: int, key: string} */
    public function active(string $purpose): array
    {
        $tenantId = $this->tenant->requireId();
        $row = $this->db->table('tenant_keys')
            ->where(['tenant_id' => $tenantId, 'purpose' => $purpose, 'status' => 'active'])
            ->orderByDesc('version')->first();

        if ($row === null) {
            return $this->create($tenantId, $purpose, 1);
        }

        return ['version' => (int) $row->version, 'key' => $this->unwrapRow($tenantId, $purpose, $row)];
    }

    public function version(string $purpose, int $version): string
    {
        $tenantId = $this->tenant->requireId();
        $cacheKey = "{$tenantId}|{$purpose}|{$version}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        $row = $this->db->table('tenant_keys')
            ->where(['tenant_id' => $tenantId, 'purpose' => $purpose, 'version' => $version])->first();
        if ($row === null) {
            throw new RuntimeException("Key {$purpose} v{$version} does not exist for this tenant.");
        }

        return $this->unwrapRow($tenantId, $purpose, $row);
    }

    /** Creates a new active version; older versions stay available for decryption. */
    public function rotate(string $purpose): int
    {
        $tenantId = $this->tenant->requireId();
        $current = (int) $this->db->table('tenant_keys')->where(['tenant_id' => $tenantId, 'purpose' => $purpose])->max('version');
        $this->db->table('tenant_keys')->where(['tenant_id' => $tenantId, 'purpose' => $purpose])->update(['status' => 'retired']);

        return $this->create($tenantId, $purpose, $current + 1)['version'];
    }

    /** @return array{version: int, key: string} */
    private function create(string $tenantId, string $purpose, int $version): array
    {
        $key = random_bytes(32);
        $wrapped = $this->kms->wrap($key, $this->context($tenantId, $purpose, $version));
        $this->db->table('tenant_keys')->insert([
            'id' => UuidV7::generate(),
            'tenant_id' => $tenantId,
            'purpose' => $purpose,
            'version' => $version,
            'wrapped_key' => $wrapped->ciphertext,
            'kek_id' => $wrapped->kekId,
            'status' => 'active',
            'created_at' => $this->clock->now(),
        ]);
        $this->cache["{$tenantId}|{$purpose}|{$version}"] = $key;

        return ['version' => $version, 'key' => $key];
    }

    private function unwrapRow(string $tenantId, string $purpose, object $row): string
    {
        $version = (int) $row->version;
        $cacheKey = "{$tenantId}|{$purpose}|{$version}";

        return $this->cache[$cacheKey] ??= $this->kms->unwrap(
            new WrappedKey((string) $row->kek_id, (string) $row->wrapped_key),
            $this->context($tenantId, $purpose, $version),
        );
    }

    private function context(string $tenantId, string $purpose, int $version): string
    {
        return "tenant:{$tenantId}|purpose:{$purpose}|v{$version}";
    }
}
