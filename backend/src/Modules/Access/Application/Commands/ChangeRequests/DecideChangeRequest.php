<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\ChangeRequests;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/**
 * Approve / reject (checker) or cancel (maker) a change request. The
 * permission is the action type's checker permission (or the maker's for
 * cancel), resolved by the controller from the request being decided.
 */
#[HandledBy(DecideChangeRequestHandler::class)]
final readonly class DecideChangeRequest implements Command, ValidatesInput
{
    public const APPROVE = 'approve';

    public const REJECT = 'reject';

    public const CANCEL = 'cancel';

    public function __construct(
        public string $changeRequestId,
        public string $decision,
        public ?string $reason,
        public string $requiredPermission,
    ) {}

    public function action(): string
    {
        return 'change_request.'.$this->decision;
    }

    public function permission(): string
    {
        return $this->requiredPermission;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('change_request', $this->changeRequestId);
    }

    public function data(): array
    {
        return ['decision' => $this->decision, 'reason' => $this->reason];
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject,cancel'],
            'reason' => [$this->decision === self::REJECT ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }
}
