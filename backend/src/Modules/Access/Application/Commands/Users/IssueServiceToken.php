<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Users;

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/**
 * Personal access token for a service/partner principal (FR-SEC-014). The
 * token's abilities are permission codes and can only *narrow* what the
 * account's role assignments grant; '*' means "whatever the roles grant".
 */
#[HandledBy(IssueServiceTokenHandler::class)]
final readonly class IssueServiceToken implements Command, ValidatesInput
{
    /** @param list<string> $abilities */
    public function __construct(public string $userId, public string $name, public array $abilities, public ?string $expiresAt) {}

    public function action(): string
    {
        return 'access.token.issued';
    }

    public function permission(): string
    {
        return Permission::UserManageTokens->value;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('user', $this->userId);
    }

    public function data(): array
    {
        return ['name' => $this->name, 'abilities' => $this->abilities, 'expires_at' => $this->expiresAt];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', 'in:*,'.implode(',', Permission::codes())],
            'expires_at' => ['required', 'date', 'after:now'],
        ];
    }
}
