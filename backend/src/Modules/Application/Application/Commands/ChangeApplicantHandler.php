<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application\Commands;

use Fundly\Modules\Application\Application\ApplicationQueries;
use Fundly\Modules\Application\Application\Support\EventPublisher;
use Fundly\Modules\Application\Infrastructure\ApplicationStore;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Http\ETag;

final class ChangeApplicantHandler implements CommandHandler
{
    public function __construct(
        private readonly ApplicationStore $store,
        private readonly PartyDirectory $parties,
        private readonly ApplicationQueries $queries,
    ) {}

    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof ChangeApplicant);
        $app = $this->store->load($command->applicationId);
        ETag::assertHeader($command->ifMatch, ApplicationQueries::etag($app->id(), $app->version()));
        if ($command->operation === 'add') {
            $party = $this->parties->find($command->partyId) ?? throw ValidationFailed::with(['party_id' => 'Unknown party.']);
            $app->addApplicant($party->id, (string) $command->role, $party->type, $party->displayName);
        } else {
            $app->removeApplicant($command->partyId);
        }
        EventPublisher::publish($context, $app, $this->store->save($app, $context->principal));

        return $this->queries->viewForCommand($app->id());
    }
}
