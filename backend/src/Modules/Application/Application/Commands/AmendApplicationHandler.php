<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application\Commands;

use Fundly\Modules\Application\Application\ApplicationQueries;
use Fundly\Modules\Application\Application\Support\EventPublisher;
use Fundly\Modules\Application\Infrastructure\ApplicationStore;
use Fundly\Modules\Application\Infrastructure\ProvenanceRecorder;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Http\ETag;

final class AmendApplicationHandler implements CommandHandler
{
    public function __construct(
        private readonly ApplicationStore $store,
        private readonly ProvenanceRecorder $provenance,
        private readonly ApplicationQueries $queries,
    ) {}

    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof AmendApplication);
        $app = $this->store->load($command->applicationId);
        ETag::assertHeader($command->ifMatch, ApplicationQueries::etag($app->id(), $app->version()));
        $changed = $app->amend($command->changes, $command->source, $command->sourceRef);
        if ($changed !== []) {
            $written = $this->store->save($app, $context->principal);
            $this->provenance->record($app->id(), $changed, $app->version(), $command->source, $command->sourceRef, $context->principal->id);
            EventPublisher::publish($context, $app, $written);
        }

        return $this->queries->viewForCommand($app->id());
    }
}
