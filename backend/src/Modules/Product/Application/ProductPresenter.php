<?php

declare(strict_types=1);

namespace Fundly\Modules\Product\Application;

use Fundly\Modules\Product\Contracts\ProductView;

final class ProductPresenter
{
    /** @return array<string, mixed> */
    public static function summary(ProductView $p): array
    {
        return [
            'id' => $p->productId,
            'key' => $p->key,
            'name' => $p->name,
            'version_id' => $p->versionId,
            'version_no' => $p->versionNo,
            'category' => $p->category,
            'segment' => $p->segment,
            'currency' => $p->currency,
            'amount' => ['min' => $p->amountMin->toArray(), 'max' => $p->amountMax->toArray()],
            'tenor_months' => ['min' => $p->tenorMinMonths, 'max' => $p->tenorMaxMonths],
            'applicant_types' => $p->applicantTypes,
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(ProductView $p): array
    {
        return self::summary($p) + ['definition' => $p->content];
    }
}
