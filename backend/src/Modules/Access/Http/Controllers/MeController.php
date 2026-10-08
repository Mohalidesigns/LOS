<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Controllers;

use Fundly\Modules\Access\Application\Queries\AuthenticatedUserQuery;
use Fundly\Modules\Access\Application\Queries\EffectiveAccessQuery;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;

final class MeController
{
    public function __construct(
        private readonly CurrentPrincipal $principal,
        private readonly AuthenticatedUserQuery $me,
        private readonly EffectiveAccessQuery $effective,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::json(['data' => $this->me->profile($this->principal->require()->id)]);
    }

    public function effectiveAccess(): JsonResponse
    {
        $p = $this->principal->require();

        return ApiResponse::json(['data' => $this->effective->forUser($p->id, $p->tokenAbilities)]);
    }
}
