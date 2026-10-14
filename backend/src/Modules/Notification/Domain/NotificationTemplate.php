<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Domain;

use Fundly\Shared\Exceptions\ValidationFailed;

/**
 * A notification template (FR-NTF-002): per event, per channel, per
 * language. Built-in defaults ship for every event; a tenant overrides one
 * with a `notification.template` configuration version whose key is the
 * event code (a version whose `event` differs from its key is ignored),
 * activated through maker-checker.
 *
 *   { event, audience: customer|staff,
 *     languages: { en: { email?: {subject, body}, sms?: {body}, whatsapp?: {template, body}, in_app?: {title, body} }, … } }
 *
 * A WhatsApp body must match the wording approved for its template.
 *
 * Bodies are plain text with {{ variable }} placeholders.
 */
final class NotificationTemplate
{
    public const VARIABLES = [
        'reference', 'applicant_name', 'product_name', 'amount', 'tenor_months', 'status', 'link',
        'valid_until', 'offer_sequence', 'loan_account_no', 'net_amount', 'value_date', 'error_code', 'bank_name',
        'collateral', 'due_date', 'stage', 'due_at', 'author',
    ];

    public const CHANNELS = ['email' => ['subject', 'body'], 'sms' => ['body'], 'whatsapp' => ['template', 'body'], 'in_app' => ['title', 'body']];

    /** Three concatenated GSM segments; longer texts cost more and arrive split. */
    public const SMS_MAX = 459;

    /** WhatsApp template body limit. */
    public const WHATSAPP_MAX = 1024;

    /** @param array<string, mixed> $content */
    private function __construct(private readonly array $content) {}

    /** @param array<string, mixed> $content */
    public static function of(array $content): self
    {
        return new self($content);
    }

    /** @param array<string, mixed> $content */
    public static function validate(array $content): void
    {
        $e = [];
        $event = $content['event'] ?? null;
        if (! is_string($event) || ! array_key_exists($event, DefaultTemplates::all())) {
            $e['content.event'][] = 'Unknown event. Templates exist for: '.implode(', ', array_keys(DefaultTemplates::all())).'.';
        }
        if (! in_array($content['audience'] ?? null, ['customer', 'staff'], true)) {
            $e['content.audience'][] = 'audience must be customer or staff.';
        }
        $languages = $content['languages'] ?? null;
        if (! is_array($languages) || $languages === [] || ! isset($languages['en'])) {
            $e['content.languages'][] = 'languages must include at least en.';
            $languages = [];
        }
        foreach ($languages as $lang => $channels) {
            if (preg_match('/^[a-z]{2}$/', (string) $lang) !== 1) {
                $e["content.languages.{$lang}"][] = 'Language codes are ISO 639-1 (e.g. en, yo, ha, ig).';
            }
            if (! is_array($channels) || $channels === []) {
                $e["content.languages.{$lang}"][] = 'Give at least one channel.';

                continue;
            }
            foreach ($channels as $channel => $parts) {
                $fields = self::CHANNELS[$channel] ?? null;
                if ($fields === null) {
                    $e["content.languages.{$lang}.{$channel}"][] = 'Channels are email, sms, whatsapp and in_app (push arrives later).';

                    continue;
                }
                foreach ($fields as $f) {
                    $text = is_array($parts) ? ($parts[$f] ?? null) : null;
                    if (! is_string($text) || trim($text) === '') {
                        $e["content.languages.{$lang}.{$channel}.{$f}"][] = "{$f} is required.";

                        continue;
                    }
                    if ($channel === 'whatsapp' && $f === 'template' && preg_match('/^[a-z0-9_]{1,512}$/', $text) !== 1) {
                        $e["content.languages.{$lang}.{$channel}.{$f}"][] = 'The template is the approved WhatsApp template name (lower-case letters, digits, underscores).';
                    }
                    if ($channel === 'whatsapp' && $f === 'body' && mb_strlen($text) > self::WHATSAPP_MAX) {
                        $e["content.languages.{$lang}.{$channel}.{$f}"][] = 'A WhatsApp template body may be at most '.self::WHATSAPP_MAX.' characters.';
                    }
                    if ($channel === 'sms' && mb_strlen($text) > self::SMS_MAX) {
                        $e["content.languages.{$lang}.{$channel}.{$f}"][] = 'An SMS body may be at most '.self::SMS_MAX.' characters (three segments).';
                    }
                    preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $text, $m);
                    $unknown = array_diff($m[1], self::VARIABLES);
                    if ($unknown !== []) {
                        $e["content.languages.{$lang}.{$channel}.{$f}"][] = 'Unknown placeholders: '.implode(', ', $unknown).'.';
                    }
                }
            }
        }
        if ($e !== []) {
            throw new ValidationFailed($e);
        }
    }

    public function event(): string
    {
        return (string) $this->content['event'];
    }

    public function audience(): string
    {
        return (string) $this->content['audience'];
    }

    /** @return list<string> channels this template sends on, for the language */
    public function channels(string $language): array
    {
        return array_keys($this->language($language));
    }

    /**
     * @param  array<string, string>  $vars
     * @return array{subject: string, body: string}|null
     */
    public function render(string $channel, string $language, array $vars): ?array
    {
        $parts = $this->language($language)[$channel] ?? null;
        if (! is_array($parts)) {
            return null;
        }
        $fill = static fn (string $t): string => (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static fn (array $m): string => $vars[$m[1]] ?? '', $t);

        return ['subject' => $fill((string) ($parts['subject'] ?? $parts['title'] ?? $parts['template'] ?? '')), 'body' => $fill((string) ($parts['body'] ?? ''))];
    }

    /** @return array<string, mixed> */
    private function language(string $language): array
    {
        $langs = (array) $this->content['languages'];

        return (array) ($langs[$language] ?? $langs['en'] ?? []);
    }
}
