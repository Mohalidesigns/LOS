<?php

declare(strict_types=1);

namespace Fundly\Shared\Http;

use Fundly\Shared\Exceptions\ProblemException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every API error as RFC 9457 application/problem+json with a stable
 * `type` from the catalogue, a machine `code`, the `correlation_id`, and an
 * `errors` map for validation failures (TRD §10.1). Unexpected errors never
 * leak internals.
 */
final class ProblemRenderer
{
    public const CONTENT_TYPE = 'application/problem+json';

    public function __construct(private readonly RequestContext $context)
    {
    }

    public function render(Throwable $e, Request $request): JsonResponse
    {
        [$status, $type, $title, $detail, $extensions, $headers] = $this->map($e);

        $body = array_merge([
            'type' => config('fundly.api.problem_type_base', 'urn:fundly:problem:').$type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => '/'.ltrim($request->path(), '/'),
            'code' => $extensions['code'] ?? $type,
            'correlation_id' => $this->context->correlationId(),
        ], array_diff_key($extensions, ['code' => true]));

        $response = new JsonResponse($body, $status, $headers, JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Type', self::CONTENT_TYPE);
        $response->headers->set('X-Correlation-Id', $this->context->correlationId());

        return $response;
    }

    /** @return array{0: int, 1: string, 2: string, 3: string, 4: array<string, mixed>, 5: array<string, string>} */
    private function map(Throwable $e): array
    {
        return match (true) {
            $e instanceof ProblemException => [
                $e->status(), $e->type(), $e->title(), $e->getMessage(),
                array_merge(['code' => $e->code()], $e->extensions()), $e->headers(),
            ],
            $e instanceof ValidationException => [
                422, 'validation-failed', 'Validation failed', 'The request is invalid.', ['errors' => $e->errors()], [],
            ],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Authentication required', 'Authentication is required.', [], []],
            $e instanceof AuthorizationException => [403, 'forbidden', 'Forbidden', 'You do not have permission to perform this action.', [], []],
            $e instanceof TokenMismatchException => [419, 'csrf-token-mismatch', 'CSRF token mismatch', 'The CSRF token is missing or invalid.', [], []],
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, 'not-found', 'Resource not found', 'The requested resource was not found.', [], []],
            $e instanceof MethodNotAllowedHttpException => [405, 'method-not-allowed', 'Method not allowed', 'This method is not supported for this resource.', [], $this->stringHeaders($e->getHeaders())],
            $e instanceof ThrottleRequestsException => [429, 'too-many-requests', 'Too many requests', 'Rate limit exceeded. Retry later.', [], $this->stringHeaders($e->getHeaders())],
            $e instanceof QueryException && $e->getCode() === '23505' => [409, 'conflict', 'Conflict', 'The resource conflicts with an existing one.', [], []],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'http-error', 'HTTP error', $e->getStatusCode() < 500 ? $e->getMessage() : 'Server error.', [], $this->stringHeaders($e->getHeaders())],
            default => [500, 'internal-error', 'Internal server error', 'An unexpected error occurred. Quote the correlation id when reporting it.', [], []],
        };
    }

    /**
     * @param  array<array-key, mixed>  $headers
     * @return array<string, string>
     */
    private function stringHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            if (is_string($k) && is_scalar($v)) {
                $out[$k] = (string) $v;
            }
        }

        return $out;
    }
}
