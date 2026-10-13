<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks\Application\Commands;

use Fundly\Integration\Webhooks\Application\WebhookQueries;
use Fundly\Integration\Webhooks\Models\WebhookEndpoint;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\NotFound;

final class DisableWebhookEndpointHandler implements CommandHandler
{
    public function __construct(private readonly WebhookQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof DisableWebhookEndpoint);
        $e = WebhookEndpoint::query()->find($command->endpointId) ?? throw new NotFound('Endpoint not found.');
        $e->forceFill(['status' => 'disabled'])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'webhook_endpoint', entityId: $e->id, after: ['status' => 'disabled']));

        return ['data' => $this->queries->endpoint($e)];
    }
}
