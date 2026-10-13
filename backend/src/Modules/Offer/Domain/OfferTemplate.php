<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Domain;

use Fundly\Shared\Exceptions\ValidationFailed;

/**
 * An `offer.template` configuration version (FR-OFR-001, LOS-FR-314).
 *
 *   title             document title
 *   body_html         HTML with {{ variable }} placeholders and {{ block }} sections
 *   execution_method  wet_sign (e_sign arrives with the P4 provider, FR-OFR-006)
 *   signing_order     roles in the order they sign: primary, joint, guarantor, co_signer, bank
 *   validity_days     optional; otherwise the product's offer_validity_days
 *   max_reissues      how often an issued offer may be re-issued (FR-OFR-007)
 *   disclosure        {method, cooling_off_days} for the key-facts statement (FR-CMP-025)
 *
 * Templates are inert: no scripts, no event handlers, no remote URLs, so the
 * renderer can never fetch anything or run code.
 */
final class OfferTemplate
{
    public const VARIABLES = [
        'applicant.name', 'application.reference', 'product.name', 'bank.name',
        'offer.sequence', 'offer.date', 'offer.valid_until',
        'terms.amount', 'terms.tenor_months', 'terms.rate_percent', 'terms.instalment', 'terms.repayment_frequency',
    ];

    public const BLOCKS = ['key_facts', 'schedule', 'conditions', 'signatures'];

    public const ROLES = ['primary', 'joint', 'guarantor', 'co_signer', 'bank'];

    public const DISCLOSURE_METHODS = ['ng_v0'];

    /** @param array<string, mixed> $content */
    private function __construct(private readonly array $content) {}

    /** @param array<string, mixed> $content */
    public static function validate(array $content): void
    {
        $e = [];
        if (! is_string($content['title'] ?? null) || trim($content['title']) === '' || strlen($content['title']) > 200) {
            $e['content.title'][] = 'A title of up to 200 characters is required.';
        }
        $body = $content['body_html'] ?? null;
        if (! is_string($body) || trim($body) === '') {
            $e['content.body_html'][] = 'body_html is required.';
        } else {
            if (preg_match('/<\s*(script|iframe|object|embed|link|meta|base)\b|\son\w+\s*=|javascript:|(src|href)\s*=\s*["\']?\s*(https?:)?\/\//i', $body) === 1) {
                $e['content.body_html'][] = 'Templates may not contain scripts, event handlers or remote URLs.';
            }
            preg_match_all('/\{\{\s*([a-z_.]+)\s*\}\}/', $body, $m);
            $unknown = array_values(array_diff(array_unique($m[1]), self::VARIABLES, self::BLOCKS));
            if ($unknown !== []) {
                $e['content.body_html'][] = 'Unknown placeholders: '.implode(', ', $unknown).'. Available: '.implode(', ', array_merge(self::VARIABLES, self::BLOCKS)).'.';
            }
            foreach (['key_facts', 'schedule', 'signatures'] as $required) {
                if (! in_array($required, $m[1], true)) {
                    $e['content.body_html'][] = "The template must place {{ {$required} }} (FR-OFR-002/003/009).";
                }
            }
        }
        $method = $content['execution_method'] ?? null;
        if ($method === 'e_sign') {
            $e['content.execution_method'][] = 'e_sign needs an e-signature provider, which arrives in P4 (FR-OFR-006); use wet_sign.';
        } elseif ($method !== 'wet_sign') {
            $e['content.execution_method'][] = 'execution_method must be wet_sign.';
        }
        $order = $content['signing_order'] ?? null;
        if (! is_array($order) || ! array_is_list($order) || $order === [] || array_diff($order, self::ROLES) !== [] || count(array_unique($order)) !== count($order) || ! in_array('primary', $order, true)) {
            $e['content.signing_order'][] = 'signing_order lists distinct roles from '.implode(', ', self::ROLES).' and must include primary.';
        }
        foreach (['validity_days' => [1, 365], 'max_reissues' => [0, 10]] as $k => [$min, $max]) {
            if (isset($content[$k]) && (! is_int($content[$k]) || $content[$k] < $min || $content[$k] > $max)) {
                $e["content.{$k}"][] = "{$k} must be between {$min} and {$max}.";
            }
        }
        $d = $content['disclosure'] ?? null;
        if (! is_array($d) || ! in_array($d['method'] ?? null, self::DISCLOSURE_METHODS, true)) {
            $e['content.disclosure.method'][] = 'disclosure.method must be one of: '.implode(', ', self::DISCLOSURE_METHODS).'.';
        }
        if (is_array($d) && isset($d['cooling_off_days']) && (! is_int($d['cooling_off_days']) || $d['cooling_off_days'] < 0 || $d['cooling_off_days'] > 30)) {
            $e['content.disclosure.cooling_off_days'][] = 'cooling_off_days must be between 0 and 30.';
        }
        if ($e !== []) {
            throw new ValidationFailed($e);
        }
    }

    /** @param array<string, mixed> $content validated content */
    public static function of(array $content): self
    {
        return new self($content);
    }

    public function title(): string
    {
        return (string) $this->content['title'];
    }

    public function body(): string
    {
        return (string) $this->content['body_html'];
    }

    public function executionMethod(): string
    {
        return (string) $this->content['execution_method'];
    }

    /** @return list<string> */
    public function signingOrder(): array
    {
        return array_values(array_map('strval', (array) $this->content['signing_order']));
    }

    public function validityDays(): ?int
    {
        return is_int($this->content['validity_days'] ?? null) ? $this->content['validity_days'] : null;
    }

    public function maxReissues(): int
    {
        return is_int($this->content['max_reissues'] ?? null) ? $this->content['max_reissues'] : 2;
    }

    public function disclosureMethod(): string
    {
        return (string) ((array) $this->content['disclosure'])['method'];
    }

    public function coolingOffDays(): int
    {
        $v = ((array) $this->content['disclosure'])['cooling_off_days'] ?? 0;

        return is_int($v) ? $v : 0;
    }
}
