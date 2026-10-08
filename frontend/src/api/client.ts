/**
 * The ONLY network boundary of the SPA (lint forbids fetch/axios elsewhere).
 *
 * A thin policy layer around the openapi-fetch client generated from
 * api/openapi/openapi.yaml (`npm run gen:api` → schema.d.ts):
 *  - same-origin Sanctum cookie session: `credentials: 'include'`
 *  - `X-XSRF-TOKEN` from the `XSRF-TOKEN` cookie on every request
 *  - `Idempotency-Key` (crypto.randomUUID) on every POST, reused on retries
 *  - `Accept: application/json`
 *  - non-2xx → typed `ApiProblem` (RFC 9457) is thrown
 *  - 419 / CSRF mismatch → refresh the csrf cookie and retry once
 *  - 401 `step-up-required` → ask the registered step-up handler, retry once
 *  - 401 session ended → notify the registered session-expired handler
 * Nothing is ever written to browser storage.
 */
import createClient from 'openapi-fetch';
import type { paths } from './schema';
import { ApiProblem, networkProblem, problemFromResponse } from './problem';

export const CSRF_COOKIE_PATH = '/api/v1/auth/csrf-cookie';
const XSRF_COOKIE = 'XSRF-TOKEN';

/** Endpoints whose 401 means "bad credentials", never "your session ended". */
const AUTH_ENTRY_PATHS = ['/api/v1/auth/login', '/api/v1/auth/mfa/verify', '/api/v1/auth/step-up', CSRF_COOKIE_PATH];

type StepUpHandler = (problem: ApiProblem) => Promise<boolean>;
type SessionEndedHandler = (problem: ApiProblem) => void;

const handlers: { stepUp: StepUpHandler | null; sessionEnded: SessionEndedHandler | null } = {
  stepUp: null,
  sessionEnded: null,
};

/** Registered by <StepUpProvider>. Resolve true when the user re-authenticated. */
export function setStepUpHandler(handler: StepUpHandler | null): void {
  handlers.stepUp = handler;
}

/** Registered by the auth layer. Called on a 401 that means the session is gone. */
export function setSessionEndedHandler(handler: SessionEndedHandler | null): void {
  handlers.sessionEnded = handler;
}

export function readCookie(name: string, cookieString: string = document.cookie): string | null {
  for (const part of cookieString.split(';')) {
    const [rawKey, ...rest] = part.trim().split('=');
    if (rawKey === name) {
      try {
        return decodeURIComponent(rest.join('='));
      } catch {
        return rest.join('=');
      }
    }
  }
  return null;
}

function newIdempotencyKey(): string {
  return crypto.randomUUID();
}

function pathOf(request: Request): string {
  try {
    return new URL(request.url).pathname;
  } catch {
    return request.url;
  }
}

/** Apply the per-request headers. The XSRF header is re-read on every attempt (it rotates). */
function withPolicyHeaders(request: Request): Request {
  const headers = new Headers(request.headers);
  headers.set('Accept', 'application/json');
  headers.set('X-Requested-With', 'XMLHttpRequest');
  const xsrf = readCookie(XSRF_COOKIE);
  if (xsrf) headers.set('X-XSRF-TOKEN', xsrf);
  else headers.delete('X-XSRF-TOKEN');
  return new Request(request, { headers, credentials: 'include' });
}

async function rawSend(request: Request): Promise<Response> {
  try {
    return await fetch(withPolicyHeaders(request));
  } catch (cause) {
    throw networkProblem(cause);
  }
}

/** GET /api/v1/auth/csrf-cookie — sets the XSRF-TOKEN cookie (Sanctum SPA bootstrap). */
export async function ensureCsrfCookie(): Promise<void> {
  const res = await rawSend(new Request(new URL(CSRF_COOKIE_PATH, window.location.origin), { method: 'GET' }));
  if (!res.ok) throw await problemFromResponse(res);
}

/**
 * The fetch implementation handed to openapi-fetch. Exported for tests.
 * Throws ApiProblem for every non-2xx response.
 */
export async function policyFetch(input: Request): Promise<Response> {
  let template = input;
  if (template.method.toUpperCase() === 'POST' && !template.headers.has('Idempotency-Key')) {
    const headers = new Headers(template.headers);
    headers.set('Idempotency-Key', newIdempotencyKey());
    template = new Request(template, { headers });
  }

  const path = pathOf(template);
  let csrfRetried = false;
  let stepUpRetried = false;

  for (;;) {
    const response = await rawSend(template.clone());
    if (response.ok) return response;

    const problem = await problemFromResponse(response);

    if (problem.isCsrfMismatch && !csrfRetried) {
      csrfRetried = true;
      await ensureCsrfCookie();
      continue;
    }

    if (problem.isStepUpRequired && !stepUpRetried && handlers.stepUp) {
      stepUpRetried = true;
      const ok = await handlers.stepUp(problem);
      if (ok) continue;
      throw problem;
    }

    if (problem.isSessionEnded && !AUTH_ENTRY_PATHS.includes(path)) {
      handlers.sessionEnded?.(problem);
    }
    throw problem;
  }
}

export const api = createClient<paths>({
  baseUrl: typeof window === 'undefined' ? '' : window.location.origin,
  credentials: 'include',
  fetch: policyFetch,
});

/** Unwrap an openapi-fetch result (errors are already thrown by policyFetch). */
export function unwrap<T>(result: { data?: T; error?: unknown; response: Response }): T {
  if (result.data === undefined) {
    if (result.response.status === 204) return undefined as T;
    throw new ApiProblem({
      type: 'urn:fundly:problem:empty-response',
      title: 'Empty response',
      status: result.response.status,
      detail: 'The server returned no data.',
      code: 'empty-response',
    });
  }
  return result.data;
}
