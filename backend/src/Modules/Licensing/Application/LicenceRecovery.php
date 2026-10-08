<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Application;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Routing\Route;

/**
 * An expired installation must still be able to install a new licence, which
 * needs a checker to approve the licence-import change request.
 */
final class LicenceRecovery
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function isRecoveryRequest(Route $route): bool
    {
        if (! in_array($route->getName(), ['change-requests.approve', 'change-requests.reject', 'change-requests.show'], true)) {
            return false;
        }
        $id = $route->parameter('id');

        return is_string($id) && $this->db->table('change_requests')->where('id', $id)->where('action_type', LicenceImportAction::TYPE)->exists();
    }
}
