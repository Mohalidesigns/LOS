<?php

declare(strict_types=1);

/*
 * Regression found by the container smoke test: a client that does not send
 * `Accept: application/json` (curl, a misconfigured integration) used to hit
 * Laravel's default guest redirect to a `login` route that does not exist in
 * this API-only application, producing a 500.
 */

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
});

it('answers unauthenticated non-JSON clients with a 401 problem document, not a login redirect', function () {
    $r = $this->call('GET', 'http://'.$this->tenant->host.'/api/v1/users', server: ['HTTP_ACCEPT' => '*/*']);

    $r->assertStatus(401);
    expect($r->headers->get('Content-Type'))->toBe('application/problem+json')
        ->and($r->json('code'))->toBe('unauthenticated')
        ->and($r->headers->has('Location'))->toBeFalse();
})->group('FR-SEC-019');
