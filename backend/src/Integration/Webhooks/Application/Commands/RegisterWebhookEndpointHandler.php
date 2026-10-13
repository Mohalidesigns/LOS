<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks\Application\Commands;

use Fundly\Integration\Webhooks\Application\WebhookQueries;
use Fundly\Integration\Webhooks\Models\WebhookEndpoint;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Crypto\FieldEncryptor;
use Fundly\Shared\Exceptions\ValidationFailed;

final class RegisterWebhookEndpointHandler implements CommandHandler
{
    public function __construct(private readonly FieldEncryptor $crypto, private readonly WebhookQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RegisterWebhookEndpoint);
        $url = (string) $command->url;
        $host = (string) parse_url($url, PHP_URL_HOST);
        // plain http only to a local receiver outside production; everything else must be https
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.localhost');
        if (! str_starts_with($url, 'https://') && (config('fundly.installation.environment') === 'production' || ! $local)) {
            throw ValidationFailed::with(['url' => 'Webhook endpoints must use https.']);
        }
        $secret = 'whsec_'.bin2hex(random_bytes(24));
        $endpoint = new WebhookEndpoint;
        $endpoint->forceFill([
            'name' => (string) $command->name, 'url' => $url, 'secret_enc' => $this->crypto->encrypt($secret, 'webhook.secret'),
            'events' => array_values(array_unique(array_map('strval', $command->events))), 'status' => 'active', 'created_by' => $context->principal->id,
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'webhook_endpoint', entityId: $endpoint->id, after: ['name' => $endpoint->name, 'url' => $endpoint->url, 'events' => $endpoint->events]));

        return ['data' => $this->queries->endpoint($endpoint) + ['secret' => $secret]];
    }
}
