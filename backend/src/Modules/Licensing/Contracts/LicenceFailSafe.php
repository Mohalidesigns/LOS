<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Contracts;

/**
 * Marker for queued work that licence state must never block (D-034):
 * in-flight sagas, outbox delivery and reconciliation.
 */
interface LicenceFailSafe
{
}
