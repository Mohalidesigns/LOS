<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Application;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(RequestLicenceImportHandler::class)]
final readonly class RequestLicenceImport implements Command
{
    public function __construct(public string $document, public string $signature, public ?string $reason) {}

    public function action(): string
    {
        return 'licensing.licence.import_requested';
    }

    public function permission(): string
    {
        return 'licence:import_request';
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }
}
