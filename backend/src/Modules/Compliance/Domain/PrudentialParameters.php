<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Domain;

use DateTimeImmutable;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Money\Money;

/**
 * Effective-dated prudential parameters of one legal entity (LOS-FR-307,
 * G-35): the limit base the regulatory limits apply to. A
 * `prudential.parameters` configuration keyed by the legal entity code,
 * maintained under maker-checker.
 *
 *   { entries: [ { effective_from, shareholders_funds_unimpaired: {amount, currency}, source } ] }
 */
final class PrudentialParameters
{
    /** @param list<array<string, mixed>> $entries */
    private function __construct(private readonly array $entries) {}

    /** @param array<string, mixed> $content */
    public static function of(array $content): self
    {
        $entries = array_values(array_filter((array) ($content['entries'] ?? []), 'is_array'));
        usort($entries, static fn (array $a, array $b): int => strcmp((string) $b['effective_from'], (string) $a['effective_from']));

        return new self($entries);
    }

    /** @param array<string, mixed> $content */
    public static function validate(array $content): void
    {
        $e = [];
        $entries = $content['entries'] ?? null;
        if (! is_array($entries) || ! array_is_list($entries) || $entries === []) {
            throw ValidationFailed::with(['content.entries' => 'entries must list the effective-dated parameters.']);
        }
        $seen = [];
        foreach ($entries as $i => $x) {
            $p = "content.entries.{$i}";
            $from = is_array($x) ? ($x['effective_from'] ?? null) : null;
            if (! is_string($from) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) {
                $e["{$p}.effective_from"][] = 'effective_from is a date (YYYY-MM-DD).';
            } elseif (isset($seen[$from])) {
                $e["{$p}.effective_from"][] = 'Two entries cannot start on the same date.';
            } else {
                $seen[$from] = true;
            }
            $base = is_array($x) ? ($x['shareholders_funds_unimpaired'] ?? null) : null;
            if (! is_array($base) || ! is_string($base['amount'] ?? null) || preg_match('/^\d{1,18}(\.\d{1,4})?$/', $base['amount']) !== 1 || ! is_string($base['currency'] ?? null) || preg_match('/^[A-Z]{3}$/', $base['currency']) !== 1) {
                $e["{$p}.shareholders_funds_unimpaired"][] = 'Give {amount, currency}, e.g. {"amount": "250000000000.00", "currency": "NGN"}.';
            }
            if (! is_array($x) || ! is_string($x['source'] ?? null) || trim($x['source']) === '') {
                $e["{$p}.source"][] = 'Record the source (e.g. audited accounts or regulatory return and its date).';
            }
        }
        if ($e !== []) {
            throw ValidationFailed::with($e);
        }
    }

    /** @return array{base: Money, effective_from: string, source: string}|null the entry in force on the date */
    public function at(DateTimeImmutable $date): ?array
    {
        $day = $date->format('Y-m-d');
        foreach ($this->entries as $x) {
            if ((string) $x['effective_from'] <= $day) {
                $b = (array) $x['shareholders_funds_unimpaired'];

                return ['base' => Money::of((string) $b['amount'], (string) $b['currency']), 'effective_from' => (string) $x['effective_from'], 'source' => (string) $x['source']];
            }
        }

        return null;
    }
}
