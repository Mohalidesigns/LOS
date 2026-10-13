<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Domain;

use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Money\Money;

/**
 * The `approval.authority_limits` configuration (FR-APV-002): limits per
 * role code, per org unit and per user, in one currency. A user's effective
 * limit is the most restrictive of every limit that applies to them,
 * including the max amount on the role assignment's scope. When nothing
 * applies there is no authority at all: approvals fail closed.
 */
final class AuthorityLimits
{
    /** @param array<string, mixed> $content */
    private function __construct(private readonly array $content) {}

    /** @param array<string, mixed> $content */
    public static function validate(array $content): void
    {
        $errors = [];
        if (! is_string($content['currency'] ?? null) || preg_match('/^[A-Z]{3}$/', $content['currency']) !== 1) {
            $errors['content.currency'][] = 'currency must be an ISO 4217 code.';
        }
        foreach (['roles' => '/^[a-z][a-z0-9_]{0,63}$/', 'org_units' => '/^[0-9a-f-]{36}$/', 'users' => '/^[0-9a-f-]{36}$/'] as $group => $keyPattern) {
            $entries = $content[$group] ?? [];
            if (! is_array($entries) || ($entries !== [] && array_is_list($entries))) {
                $errors["content.{$group}"][] = "{$group} must map ids or codes to amounts.";

                continue;
            }
            foreach ($entries as $k => $v) {
                if (preg_match($keyPattern, (string) $k) !== 1) {
                    $errors["content.{$group}.{$k}"][] = $group === 'roles' ? 'Keys are role codes.' : 'Keys are ids.';
                }
                if (! is_string($v) || preg_match('/^\d{1,16}(\.\d{1,4})?$/', $v) !== 1) {
                    $errors["content.{$group}.{$k}"][] = 'Limits are non-negative decimal strings.';
                }
            }
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }
    }

    /** @param array<string, mixed> $content */
    public static function of(array $content): self
    {
        return new self($content);
    }

    public static function none(string $currency): self
    {
        return new self(['currency' => $currency]);
    }

    public function currency(): string
    {
        return (string) $this->content['currency'];
    }

    /**
     * Every limit that applies to one grant, and the minimum.
     *
     * @param  list<string>  $userIds  the voter, plus the delegator for a delegated grant (FR-APV-006 cap)
     * @param  list<string>  $orgUnitIds  configured org units whose subtree contains the voter's home org unit
     * @return array{limit: ?Money, source: ?string, components: list<array{source: string, ref: string, amount: array{amount: string, currency: string}}>}
     */
    public function effective(string $roleCode, ?Money $scopeMax, array $userIds, array $orgUnitIds): array
    {
        $components = [];
        if ($scopeMax !== null) {
            $components[] = ['source' => 'assignment_scope', 'ref' => $roleCode, 'money' => $scopeMax];
        }
        foreach ($userIds as $u) {
            if (isset($this->content['users'][$u])) {
                $components[] = ['source' => 'user', 'ref' => $u, 'money' => Money::of((string) $this->content['users'][$u], $this->currency())];
            }
        }
        if (isset($this->content['roles'][$roleCode])) {
            $components[] = ['source' => 'role', 'ref' => $roleCode, 'money' => Money::of((string) $this->content['roles'][$roleCode], $this->currency())];
        }
        foreach ($orgUnitIds as $o) {
            if (isset($this->content['org_units'][$o])) {
                $components[] = ['source' => 'org_unit', 'ref' => $o, 'money' => Money::of((string) $this->content['org_units'][$o], $this->currency())];
            }
        }
        $min = null;
        foreach ($components as $c) {
            // a limit in another currency cannot be compared, so it allows nothing
            $m = $c['money']->currency->code === $this->currency() ? $c['money'] : Money::zero($this->currency());
            if ($min === null || $m->compareTo($min['money']) < 0) {
                $min = ['money' => $m, 'source' => $c['source']];
            }
        }

        return [
            'limit' => $min['money'] ?? null,
            'source' => $min['source'] ?? null,
            'components' => array_map(static fn (array $c): array => ['source' => $c['source'], 'ref' => $c['ref'], 'amount' => $c['money']->toArray()], $components),
        ];
    }

    /** @return list<string> */
    public function orgUnitIds(): array
    {
        return array_map('strval', array_keys((array) ($this->content['org_units'] ?? [])));
    }
}
