<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking;

use Fundly\Integration\Ports\CoreBanking\Dto\ChargeItem;
use Fundly\Integration\Ports\CoreBanking\Dto\DisbursementRequest;
use Fundly\Integration\Ports\CoreBanking\Dto\Posting;
use Fundly\Integration\Ports\CoreBanking\Dto\PostingResult;

/**
 * Postings sub-port. Never blind-retry a posting: on an unknown outcome look it up by reference first (register §2.3).
 */
interface PostingsPort
{
    /**
     * State-changing.
     */
    public function disburse(DisbursementRequest $request, string $idempotencyKey): PostingResult;

    /**
     * State-changing.
     *
     * @param  list<ChargeItem>  $items
     * @return list<string> posting references
     */
    public function postCharges(string $loanAccountNo, array $items, string $idempotencyKey): array;

    /**
     * State-changing. Returns the reversal reference.
     */
    public function reversePosting(string $postingRef, string $reason, string $idempotencyKey): string;

    public function getPostingByReference(string $reference): ?Posting;
}
