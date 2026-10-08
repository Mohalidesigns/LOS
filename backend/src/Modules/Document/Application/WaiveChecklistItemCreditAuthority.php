<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application;

final class WaiveChecklistItemCreditAuthority extends WaiveChecklistItemAction
{
    public function type(): string
    {
        return self::TYPE_CREDIT_AUTHORITY;
    }

    public function checkerPermission(): string
    {
        return 'application:approve';
    }
}
