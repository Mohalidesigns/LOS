<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application;

use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Illuminate\Database\ConnectionInterface;

/**
 * Forces re-authentication after a privilege change (FR-SEC-015): deletes the
 * user's database sessions and bumps auth_version so any session that
 * survives (another store, a race) is rejected on its next request.
 */
final class SessionRevoker
{
    public function __construct(private readonly ConnectionInterface $db, private readonly GrantRepository $grants) {}

    public function revokeAll(string $userId): int
    {
        $this->db->table('users')->where('id', $userId)->increment('auth_version');
        $this->grants->forget($userId);

        return $this->db->table('sessions')->where('user_id', $userId)->delete();
    }

    /** @param list<string> $userIds */
    public function revokeMany(array $userIds): void
    {
        foreach (array_unique($userIds) as $id) {
            $this->revokeAll($id);
        }
    }
}
