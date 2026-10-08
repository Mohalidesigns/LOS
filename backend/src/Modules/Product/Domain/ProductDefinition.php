<?php

declare(strict_types=1);

namespace Fundly\Modules\Product\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Fundly\Shared\Exceptions\ValidationFailed;

/**
 * Parses and validates the content of a `product` configuration version
 * (FR-PRD-001..006). Pure: no framework, no I/O. Decimals are strings parsed
 * with brick/math, never floats.
 *
 * Shape (all money in the product currency, major units as decimal strings):
 *   category, segment, currency, applicant_types[],
 *   amount{min,max}, tenor_months{min,max},
 *   interest{basis, rate_percent, index?, spread_percent?}, repayment_frequency,
 *   moratorium_months{max}, fees[], penalty?, prepayment?,
 *   eligibility[], checklist[], bindings{workflow, rule_set, approval_matrix},
 *   offer_validity_days, approval_validity_days
 */
final class ProductDefinition
{
    public const SEGMENTS = ['retail', 'sme', 'corporate'];

    public const APPLICANT_TYPES = ['individual', 'limited_company'];

    public const INTEREST_BASES = ['flat', 'reducing_balance', 'floating'];

    public const FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'bullet'];

    public const FEE_TYPES = ['upfront', 'amortised', 'contingent'];

    public const FEE_CALCS = ['percent', 'flat'];

    public const CHANNELS = ['staff', 'api', 'portal', 'partner'];

    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @param array<string, mixed> $content */
    private function __construct(private readonly array $content) {}

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed> the normalised content
     */
    public static function validate(array $content): array
    {
        $d = new self($content);
        $d->check();
        if ($d->errors !== []) {
            throw new ValidationFailed($d->errors);
        }

        return $content;
    }

    private function check(): void
    {
        $c = $this->content;
        $category = ProductCategory::tryFrom(is_string($c['category'] ?? null) ? $c['category'] : '');
        if ($category === null) {
            $this->fail('category', 'category must be one of: '.implode(', ', array_column(ProductCategory::cases(), 'value')).'.');
        } elseif (! $category->activatableInMvp()) {
            $this->fail('category', 'Salary-backed products cannot be configured until payroll mandates are available (D-038c, FR-DSB-010).');
        }
        $this->oneOf('segment', $c['segment'] ?? null, self::SEGMENTS);
        $currency = $c['currency'] ?? null;
        if (! is_string($currency) || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $this->fail('currency', 'currency must be an ISO 4217 code.');
        }
        $types = $c['applicant_types'] ?? null;
        if (! is_array($types) || $types === [] || array_diff($types, self::APPLICANT_TYPES) !== []) {
            $this->fail('applicant_types', 'applicant_types must list one or more of: '.implode(', ', self::APPLICANT_TYPES).'.');
        }

        $amount = $this->map('amount');
        $min = $this->decimal('amount.min', $amount['min'] ?? null);
        $max = $this->decimal('amount.max', $amount['max'] ?? null);
        if ($min !== null && $max !== null && ($min->isNegativeOrZero() || $min->isGreaterThan($max))) {
            $this->fail('amount', 'amount.min must be positive and not greater than amount.max.');
        }
        $tenor = $this->map('tenor_months');
        $tMin = $this->int('tenor_months.min', $tenor['min'] ?? null, 1, 600);
        $tMax = $this->int('tenor_months.max', $tenor['max'] ?? null, 1, 600);
        if ($tMin !== null && $tMax !== null && $tMin > $tMax) {
            $this->fail('tenor_months', 'tenor_months.min must not exceed tenor_months.max.');
        }

        $interest = $this->map('interest');
        $basis = $interest['basis'] ?? null;
        $this->oneOf('interest.basis', $basis, self::INTEREST_BASES);
        $this->percent('interest.rate_percent', $interest['rate_percent'] ?? null);
        if ($basis === 'floating') {
            if (! is_string($interest['index'] ?? null) || $interest['index'] === '') {
                $this->fail('interest.index', 'A floating rate needs a reference index.');
            }
            $this->percent('interest.spread_percent', $interest['spread_percent'] ?? null);
        }
        $this->oneOf('repayment_frequency', $c['repayment_frequency'] ?? null, self::FREQUENCIES);
        $moratorium = $this->map('moratorium_months', optional: true);
        if (array_key_exists('max', $moratorium)) {
            $this->int('moratorium_months.max', $moratorium['max'], 0, 60);
        }

        $codes = [];
        foreach ($this->list('fees') as $i => $fee) {
            $p = "fees.{$i}";
            $code = $this->code("{$p}.code", $fee['code'] ?? null);
            if ($code !== null && in_array($code, $codes, true)) {
                $this->fail("{$p}.code", 'Fee codes must be unique.');
            }
            $codes[] = $code;
            $this->text("{$p}.name", $fee['name'] ?? null);
            $this->oneOf("{$p}.type", $fee['type'] ?? null, self::FEE_TYPES);
            $this->oneOf("{$p}.calc", $fee['calc'] ?? null, self::FEE_CALCS);
            ($fee['calc'] ?? null) === 'percent' ? $this->percent("{$p}.value", $fee['value'] ?? null) : $this->decimal("{$p}.value", $fee['value'] ?? null);
        }
        if (isset($c['penalty'])) {
            $penalty = $this->map('penalty');
            $this->percent('penalty.rate_percent', $penalty['rate_percent'] ?? null);
        }
        if (isset($c['prepayment'])) {
            $pre = $this->map('prepayment');
            if (! is_bool($pre['allowed'] ?? null)) {
                $this->fail('prepayment.allowed', 'prepayment.allowed must be true or false.');
            }
            if (isset($pre['fee_percent'])) {
                $this->percent('prepayment.fee_percent', $pre['fee_percent']);
            }
        }

        foreach ($this->list('eligibility', optional: true) as $i => $rule) {
            $this->code("eligibility.{$i}.code", $rule['code'] ?? null);
            $this->text("eligibility.{$i}.description", $rule['description'] ?? null);
        }

        $itemCodes = [];
        foreach ($this->list('checklist') as $i => $item) {
            $p = "checklist.{$i}";
            $code = $this->code("{$p}.code", $item['code'] ?? null);
            if ($code !== null && in_array($code, $itemCodes, true)) {
                $this->fail("{$p}.code", 'Checklist codes must be unique.');
            }
            $itemCodes[] = $code;
            $this->text("{$p}.name", $item['name'] ?? null);
            if (! is_bool($item['mandatory'] ?? null)) {
                $this->fail("{$p}.mandatory", 'mandatory must be true or false.');
            }
            $applies = $item['applies_to'] ?? [];
            if (! is_array($applies)) {
                $this->fail("{$p}.applies_to", 'applies_to must be an object.');

                continue;
            }
            foreach (['applicant_types' => self::APPLICANT_TYPES, 'segments' => self::SEGMENTS, 'channels' => self::CHANNELS] as $k => $allowed) {
                if (isset($applies[$k]) && (! is_array($applies[$k]) || array_diff($applies[$k], $allowed) !== [])) {
                    $this->fail("{$p}.applies_to.{$k}", "{$k} may contain: ".implode(', ', $allowed).'.');
                }
            }
            foreach (['amount_min', 'amount_max'] as $k) {
                if (isset($applies[$k])) {
                    $this->decimal("{$p}.applies_to.{$k}", $applies[$k]);
                }
            }
        }

        $bindings = $this->map('bindings', optional: true);
        foreach (['workflow', 'rule_set', 'approval_matrix'] as $k) {
            if (isset($bindings[$k]) && (! is_string($bindings[$k]) || preg_match('/^[a-z0-9][a-z0-9_.-]{0,95}$/', $bindings[$k]) !== 1)) {
                $this->fail("bindings.{$k}", "bindings.{$k} must be a configuration key.");
            }
        }
        $this->int('offer_validity_days', $c['offer_validity_days'] ?? null, 1, 365);
        $this->int('approval_validity_days', $c['approval_validity_days'] ?? null, 1, 365);
    }

    /** @return array<string, mixed> */
    private function map(string $key, bool $optional = false): array
    {
        $v = $this->content[$key] ?? null;
        if ($v === null && $optional) {
            return [];
        }
        if (! is_array($v) || array_is_list($v) && $v !== []) {
            $this->fail($key, "{$key} must be an object.");

            return [];
        }

        /** @var array<string, mixed> $v */
        return $v;
    }

    /** @return array<int, array<string, mixed>> */
    private function list(string $key, bool $optional = false): array
    {
        $v = $this->content[$key] ?? null;
        if ($v === null && $optional) {
            return [];
        }
        if (! is_array($v) || ! array_is_list($v)) {
            $this->fail($key, "{$key} must be a list.");

            return [];
        }
        $out = [];
        foreach ($v as $i => $item) {
            if (! is_array($item)) {
                $this->fail("{$key}.{$i}", 'Each entry must be an object.');

                continue;
            }
            /** @var array<string, mixed> $item */
            $out[$i] = $item;
        }

        return $out;
    }

    private function decimal(string $field, mixed $value): ?BigDecimal
    {
        if (! is_string($value) || preg_match('/^\d{1,16}(\.\d{1,4})?$/', $value) !== 1) {
            $this->fail($field, "{$field} must be a non-negative decimal string with at most 4 decimal places.");

            return null;
        }
        try {
            return BigDecimal::of($value);
        } catch (MathException) {
            $this->fail($field, "{$field} is not a valid decimal.");

            return null;
        }
    }

    private function percent(string $field, mixed $value): void
    {
        $d = $this->decimal($field, $value);
        if ($d !== null && $d->isGreaterThan(100)) {
            $this->fail($field, "{$field} must be between 0 and 100.");
        }
    }

    private function int(string $field, mixed $value, int $min, int $max): ?int
    {
        if (! is_int($value) || $value < $min || $value > $max) {
            $this->fail($field, "{$field} must be an integer between {$min} and {$max}.");

            return null;
        }

        return $value;
    }

    /** @param list<string> $allowed */
    private function oneOf(string $field, mixed $value, array $allowed): void
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            $this->fail($field, "{$field} must be one of: ".implode(', ', $allowed).'.');
        }
    }

    private function code(string $field, mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^[A-Z0-9][A-Z0-9_-]{0,47}$/', $value) !== 1) {
            $this->fail($field, "{$field} must be an upper-case code.");

            return null;
        }

        return $value;
    }

    private function text(string $field, mixed $value): void
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 200) {
            $this->fail($field, "{$field} is required (max 200 characters).");
        }
    }

    private function fail(string $field, string $message): void
    {
        $this->errors['content.'.$field][] = $message;
    }
}
