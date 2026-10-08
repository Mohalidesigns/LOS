<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Contracts;

/**
 * Clear identity numbers for outbound integration calls only (bureau,
 * verification). Never use it to display values: presentation is masked.
 */
interface IdentityNumberResolver
{
    /**
     * @param  list<string>  $types  e.g. ['bvn', 'nin'] or ['rc_number']
     * @return array{type: string, value: string}|null the first recorded identifier of the given types, in order
     */
    public function resolve(string $partyId, array $types): ?array;
}
