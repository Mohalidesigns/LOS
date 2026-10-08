<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Application;

use Fundly\Modules\Access\Contracts\ChangeRequestGateway;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;

final class RequestLicenceImportHandler implements CommandHandler
{
    public function __construct(private readonly ChangeRequestGateway $changeRequests) {}

    /** @return array<string, mixed> */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RequestLicenceImport);

        return $this->changeRequests->submit(LicenceImportAction::TYPE, ['document' => $command->document, 'signature' => $command->signature], $command->reason, $context);
    }
}
