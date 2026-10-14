<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Domain\Packs;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Fundly\Shared\Exceptions\ValidationFailed;

/**
 * A jurisdiction pack (FR-CMP-001/003, TRD §9.1): the regulatory rule
 * register for one jurisdiction × licence category, as a versioned
 * `jurisdiction.pack` configuration (key `ng.commercial_bank`), activated
 * through maker-checker and only when every rule is verified (TRD §9.4).
 *
 *   { jurisdiction: "NG", licence_category: "commercial_bank", name,
 *     rules: [ { code, title, kind: parameter|obligation, parameters?, citation: { instrument, section?, url?, retrieved_on? },
 *               effective_from, effective_to?, verification_status: unverified|verified, verified_by?, verified_on?, note? } ] }
 *
 * Parameter rules feed the system (limits, periods); obligation rules record
 * a duty and its citation. A code may appear more than once with
 * non-overlapping dates, so a historical decision can be re-evaluated
 * against the rule in force at the time.
 */
final class JurisdictionPack
{
    /** Machine-read rules and their parameter checks. */
    public const PARAMETER_RULES = [
        'single_obligor_limit' => 'limit_percent (0–100) of base shareholders_funds_unimpaired',
        'origination_record_retention' => 'years (1–30) origination records are kept after booking',
        'bureau_report_validity' => 'days (1–365) a credit bureau report may support a decision',
    ];

    public const LICENCE_CATEGORIES = ['commercial_bank', 'merchant_bank', 'microfinance_bank', 'primary_mortgage_bank', 'finance_company', 'digital_lender'];

    /** @param array<string, mixed> $content */
    private function __construct(private readonly array $content) {}

    /** @param array<string, mixed> $content */
    public static function of(array $content): self
    {
        return new self($content);
    }

    public static function key(string $jurisdiction, string $licenceCategory): string
    {
        return strtolower($jurisdiction).'.'.$licenceCategory;
    }

    /** @param array<string, mixed> $content */
    public static function validate(array $content): void
    {
        $e = [];
        if (! is_string($content['jurisdiction'] ?? null) || preg_match('/^[A-Z]{2}$/', $content['jurisdiction']) !== 1) {
            $e['content.jurisdiction'][] = 'jurisdiction is an ISO 3166-1 alpha-2 code (e.g. NG).';
        }
        if (! in_array($content['licence_category'] ?? null, self::LICENCE_CATEGORIES, true)) {
            $e['content.licence_category'][] = 'licence_category must be one of: '.implode(', ', self::LICENCE_CATEGORIES).'.';
        }
        if (! is_string($content['name'] ?? null) || trim($content['name']) === '') {
            $e['content.name'][] = 'name is required.';
        }
        $rules = $content['rules'] ?? null;
        if (! is_array($rules) || ! array_is_list($rules) || $rules === []) {
            $e['content.rules'][] = 'rules must list the regulatory rules.';
            $rules = [];
        }
        $spans = [];
        foreach ($rules as $i => $r) {
            $p = "content.rules.{$i}";
            if (! is_array($r)) {
                $e[$p][] = 'Each rule must be an object.';

                continue;
            }
            $code = $r['code'] ?? null;
            if (! is_string($code) || preg_match('/^[a-z][a-z0-9_]{2,63}$/', $code) !== 1) {
                $e["{$p}.code"][] = 'code must be lower-case snake_case.';
                $code = null;
            }
            if (! is_string($r['title'] ?? null) || trim($r['title']) === '') {
                $e["{$p}.title"][] = 'title is required.';
            }
            $kind = $r['kind'] ?? null;
            if (! in_array($kind, ['parameter', 'obligation'], true)) {
                $e["{$p}.kind"][] = 'kind must be parameter or obligation.';
            }
            if ($kind === 'parameter') {
                if ($code !== null && ! array_key_exists($code, self::PARAMETER_RULES)) {
                    $e["{$p}.code"][] = 'Parameter rules the system reads are: '.implode(', ', array_keys(self::PARAMETER_RULES)).'. Record anything else as an obligation.';
                } elseif ($code !== null) {
                    foreach (self::parameterErrors($code, is_array($r['parameters'] ?? null) ? $r['parameters'] : []) as $field => $msg) {
                        $e["{$p}.parameters.{$field}"][] = $msg;
                    }
                }
            }
            $citation = $r['citation'] ?? null;
            if (! is_array($citation) || ! is_string($citation['instrument'] ?? null) || trim($citation['instrument']) === '') {
                $e["{$p}.citation.instrument"][] = 'Every rule cites its instrument (Act, regulation, circular) (FR-CMP-003).';
            }
            if (is_array($citation) && isset($citation['url']) && (! is_string($citation['url']) || preg_match('#^https://#', $citation['url']) !== 1)) {
                $e["{$p}.citation.url"][] = 'url must be an https link to the primary source.';
            }
            $from = self::date($r['effective_from'] ?? null);
            $to = isset($r['effective_to']) ? self::date($r['effective_to']) : null;
            if ($from === null) {
                $e["{$p}.effective_from"][] = 'effective_from is a date (YYYY-MM-DD).';
            }
            if (isset($r['effective_to']) && ($to === null || ($from !== null && $to <= $from))) {
                $e["{$p}.effective_to"][] = 'effective_to is a date after effective_from.';
            }
            if (! in_array($r['verification_status'] ?? null, ['unverified', 'verified'], true)) {
                $e["{$p}.verification_status"][] = 'verification_status must be unverified or verified.';
            } elseif ($r['verification_status'] === 'verified' && (! is_string($r['verified_by'] ?? null) || self::date($r['verified_on'] ?? null) === null || ! is_string($citation['url'] ?? null))) {
                $e["{$p}.verification_status"][] = 'A verified rule records verified_by, verified_on and the source url (TRD §9.4).';
            }
            if ($code !== null && $from !== null) {
                foreach ($spans[$code] ?? [] as [$f, $t]) {
                    if ($from < ($t ?? '9999-12-31') && $f < ($to ?? '9999-12-31')) {
                        $e["{$p}.effective_from"][] = "Another {$code} rule is in force over the same dates.";
                    }
                }
                $spans[$code][] = [$from, $to];
            }
        }
        if ($e !== []) {
            throw ValidationFailed::with($e);
        }
    }

    /**
     * The activation gate: every rule verified (TRD §9.4).
     *
     * @param  array<string, mixed>  $content
     */
    public static function assertActivatable(array $content, bool $allowUnverified): void
    {
        if ($allowUnverified) {
            return;
        }
        $pending = [];
        foreach ((array) ($content['rules'] ?? []) as $r) {
            if (is_array($r) && ($r['verification_status'] ?? null) !== 'verified') {
                $pending[] = (string) ($r['code'] ?? '?');
            }
        }
        if ($pending !== []) {
            throw ValidationFailed::with(['content.rules' => 'A pack is activated only when every rule is verified against its primary source (TRD §9.4). Unverified: '.implode(', ', array_unique($pending)).'.']);
        }
    }

    public function jurisdiction(): string
    {
        return (string) $this->content['jurisdiction'];
    }

    public function licenceCategory(): string
    {
        return (string) $this->content['licence_category'];
    }

    public function name(): string
    {
        return (string) $this->content['name'];
    }

    /** @return list<array<string, mixed>> */
    public function rules(): array
    {
        return array_values(array_filter((array) ($this->content['rules'] ?? []), 'is_array'));
    }

    /** @return array<string, mixed>|null the rule with this code in force on the date */
    public function ruleAt(string $code, DateTimeImmutable $at): ?array
    {
        $day = $at->format('Y-m-d');
        foreach ($this->rules() as $r) {
            if (($r['code'] ?? null) === $code && (string) $r['effective_from'] <= $day && (! isset($r['effective_to']) || $day < (string) $r['effective_to'])) {
                return $r;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, string>
     */
    private static function parameterErrors(string $code, array $p): array
    {
        $decimal = static fn (mixed $v, int $max): bool => is_string($v) && preg_match('/^\d{1,3}(\.\d{1,4})?$/', $v) === 1 && ! BigDecimal::of($v)->isGreaterThan($max);

        return match ($code) {
            'single_obligor_limit' => array_filter([
                'limit_percent' => $decimal($p['limit_percent'] ?? null, 100) ? null : 'limit_percent is a decimal string between 0 and 100.',
                'base' => ($p['base'] ?? null) === 'shareholders_funds_unimpaired' ? null : 'base must be shareholders_funds_unimpaired.',
            ]),
            'origination_record_retention' => is_int($p['years'] ?? null) && $p['years'] >= 1 && $p['years'] <= 30 ? [] : ['years' => 'years is an integer between 1 and 30.'],
            'bureau_report_validity' => is_int($p['days'] ?? null) && $p['days'] >= 1 && $p['days'] <= 365 ? [] : ['days' => 'days is an integer between 1 and 365.'],
            default => [],
        };
    }

    private static function date(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
    }
}
