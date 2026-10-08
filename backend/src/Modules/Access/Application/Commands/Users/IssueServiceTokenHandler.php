<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Users;

use DateTimeImmutable;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;

final class IssueServiceTokenHandler implements CommandHandler
{
    /** @return array{id: string, token: string, abilities: list<string>, expires_at: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof IssueServiceToken);
        $user = User::query()->findOrFail($command->userId);
        if (! $user->isService() || ! $user->isActive()) {
            throw new DomainRuleViolation('Tokens can only be issued to active service accounts.');
        }
        $expires = new DateTimeImmutable((string) $command->expiresAt);
        $token = $user->createToken($command->name, $command->abilities, $expires);

        $context->audit(new AuditEntry(
            action: $command->action(),
            entityType: 'user',
            entityId: $user->id,
            after: ['token_id' => $token->accessToken->getKey(), 'name' => $command->name, 'abilities' => $command->abilities, 'expires_at' => $expires->format(DATE_ATOM)],
        ));

        return [
            'id' => (string) $token->accessToken->getKey(),
            'token' => $token->plainTextToken,
            'abilities' => $command->abilities,
            'expires_at' => $expires->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
