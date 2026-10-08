<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Controllers;

use Fundly\Modules\Access\Application\Auth\AuthenticationService;
use Fundly\Modules\Access\Application\Auth\LoginResult;
use Fundly\Modules\Access\Application\Queries\AuthenticatedUserQuery;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController
{
    public function __construct(
        private readonly AuthenticationService $auth,
        private readonly AuthenticatedUserQuery $me,
        private readonly CurrentPrincipal $principal,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
        $result = $this->auth->attemptPassword((string) $data['email'], (string) $data['password'], $request->session());

        return $this->loginResponse($result);
    }

    public function verifyMfa(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $result = $this->auth->verifyMfa((string) $data['code'], $request->session());

        return $this->loginResponse($result);
    }

    public function stepUp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'max:1024'],
            'code' => ['nullable', 'string', 'size:6'],
        ]);
        $user = $this->me->sessionUser($this->principal->require());
        $code = $data['code'] ?? null;
        $ref = $this->auth->stepUp($user, (string) $data['password'], is_string($code) ? $code : null, $request->session());

        return ApiResponse::json(['data' => ['step_up_ref' => $ref, 'valid_for_minutes' => (int) config('fundly.auth.step_up_default_minutes', 5)]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($this->me->sessionUser($this->principal->require()), $request->session());

        return ApiResponse::noContent();
    }

    private function loginResponse(LoginResult $result): JsonResponse
    {
        $body = ['status' => $result->status];
        if ($result->enrollment !== null) {
            $body['mfa_enrollment'] = $result->enrollment;
        }
        if ($result->userId !== null) {
            $body['user'] = $this->me->profile($result->userId);
        }

        return ApiResponse::json(['data' => $body]);
    }
}
