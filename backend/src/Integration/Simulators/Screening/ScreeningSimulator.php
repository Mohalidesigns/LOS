<?php

declare(strict_types=1);

namespace Fundly\Integration\Simulators\Screening;

use Fundly\Integration\Ports\Screening\Dto\ScreeningHit;
use Fundly\Integration\Ports\Screening\Dto\ScreeningResult;
use Fundly\Integration\Ports\Screening\Dto\ScreeningSubject;
use Fundly\Integration\Ports\Screening\ScreeningPort;
use Fundly\Integration\Runtime\CapabilityManifest;

/**
 * Deterministic screening simulator (synthetic list, D-039). A subject hits
 * when every token of a list entry's name appears in the subject's name.
 * The binding config `entries` replaces the default synthetic list.
 */
final class ScreeningSimulator implements ScreeningPort
{
    public const KEY = 'screening-simulator';

    public const VERSION = '1.0.0';

    public const LIST_VERSION = 'SIM-2026.10';

    /** @var list<array{id: string, name: string, category: string, list: string}> */
    public const DEFAULT_ENTRIES = [
        ['id' => 'PEP-0001', 'name' => 'Emeka Obi', 'category' => 'pep', 'list' => 'Synthetic PEP register'],
        ['id' => 'PEP-0002', 'name' => 'Halima Danjuma', 'category' => 'pep', 'list' => 'Synthetic PEP register'],
        ['id' => 'SAN-0001', 'name' => 'Blackwater Commodities', 'category' => 'sanction', 'list' => 'Synthetic consolidated sanctions'],
        ['id' => 'ADV-0001', 'name' => 'Tunde Bakare Ventures', 'category' => 'adverse_media', 'list' => 'Synthetic adverse media'],
    ];

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = []) {}

    public static function capabilities(): CapabilityManifest
    {
        return new CapabilityManifest(self::KEY, self::VERSION, '1.0', 'on_prem:simulator', [
            self::OP_SCREEN => ['support' => 'native', 'idempotency' => 'natural'],
        ]);
    }

    public function screen(ScreeningSubject $subject): ScreeningResult
    {
        $entries = is_array($this->config['entries'] ?? null) ? $this->config['entries'] : self::DEFAULT_ENTRIES;
        $tokens = self::tokens($subject->name);
        $hits = [];
        foreach ($entries as $e) {
            if (! is_array($e) || ! is_string($e['name'] ?? null)) {
                continue;
            }
            $entryTokens = self::tokens($e['name']);
            if ($entryTokens !== [] && array_diff($entryTokens, $tokens) === []) {
                $score = (string) intdiv(count($entryTokens) * 100, max(count($tokens), 1));
                $hits[] = new ScreeningHit((string) ($e['list'] ?? 'Synthetic list'), (string) ($e['category'] ?? 'watchlist'), (string) ($e['id'] ?? ''), $e['name'], min(100, (int) $score).'.00');
            }
        }

        return new ScreeningResult('SIM-SCR-'.strtoupper(substr(hash('sha256', $subject->reference.$subject->name), 0, 12)), self::LIST_VERSION, $hits);
    }

    /** @return list<string> */
    private static function tokens(string $name): array
    {
        $clean = strtolower(preg_replace('/[^a-z0-9 ]+/i', ' ', $name) ?? $name);

        return array_values(array_unique(array_filter(explode(' ', $clean), static fn (string $t): bool => $t !== '' && ! in_array($t, ['ltd', 'limited', 'plc', 'chief', 'mr', 'mrs', 'dr'], true))));
    }
}
