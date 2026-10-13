<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application\Commands;

use Fundly\Modules\Disbursement\Application\BookingSaga;
use Fundly\Modules\Disbursement\Infrastructure\Models\Disbursement;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\NotFound;

final class RunBookingSagaHandler implements CommandHandler
{
    public function __construct(private readonly BookingSaga $saga) {}

    /** @return array{outcome: string, status: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RunBookingSaga);
        $d = Disbursement::query()->lockForUpdate()->find($command->disbursementId) ?? throw new NotFound('Disbursement not found.');
        $outcome = $this->saga->run($d, $context);

        return ['outcome' => $outcome, 'status' => $d->status];
    }
}
