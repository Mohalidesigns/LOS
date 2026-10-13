<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property int $sequence
 * @property string $status
 * @property string $offer_id
 * @property string $agreement_id
 * @property string $principal_amount
 * @property string $net_amount
 * @property string $currency
 * @property list<array{code: string, name: string, amount: string}> $fees
 * @property Carbon $value_date
 * @property array{bank_code: string, account: string, account_name: ?string, check_id: string} $destination
 * @property string $account_no_enc
 * @property string|null $change_request_id
 * @property string $made_by
 * @property Carbon $made_at
 * @property string|null $released_by
 * @property Carbon|null $released_at
 * @property string|null $cba_customer_id
 * @property string|null $loan_account_no
 * @property string|null $posting_ref
 * @property list<string>|null $fee_posting_refs
 * @property list<array<string, mixed>>|null $cba_schedule
 * @property array<string, mixed>|null $variance
 * @property string|null $last_error_code
 * @property string|null $last_error_message
 * @property int $saga_runs
 * @property Carbon|null $booked_at
 * @property string|null $advice_document_id
 * @property string|null $advice_version_id
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 */
final class Disbursement extends TenantModel
{
    public $timestamps = false;

    protected $table = 'disbursements';

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'saga_runs' => 'integer', 'fees' => 'array', 'destination' => 'array', 'fee_posting_refs' => 'array', 'cba_schedule' => 'array', 'variance' => 'array', 'value_date' => 'date', 'made_at' => 'datetime', 'released_at' => 'datetime', 'booked_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
