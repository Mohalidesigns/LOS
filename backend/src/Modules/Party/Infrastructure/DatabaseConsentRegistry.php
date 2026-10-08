<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Infrastructure;

use Fundly\Modules\Party\Contracts\ConsentRegistry;
use Illuminate\Support\Facades\DB;

final class DatabaseConsentRegistry implements ConsentRegistry
{
    public function active(string $partyId, string $purpose): bool
    {
        $latest = DB::table('party_consents')->where('party_id', $partyId)->where('purpose', $purpose)->orderByDesc('recorded_at')->orderByDesc('id')->value('action');

        return $latest === 'grant';
    }
}
