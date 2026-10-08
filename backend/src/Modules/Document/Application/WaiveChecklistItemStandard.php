<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application;

final class WaiveChecklistItemStandard extends WaiveChecklistItemAction
{
    public function type(): string
    {
        return self::TYPE_STANDARD;
    }

    public function checkerPermission(): string
    {
        return 'document:waive_approve';
    }
}
