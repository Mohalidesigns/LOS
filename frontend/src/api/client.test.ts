import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api, etagOf, formBody, policyFetch, readCookie, setSessionEndedHandler, setStepUpHandler, unwrap } from './client';
import { ApiProblem, problemFromBody } from './problem';

type Call = { url: string; method: string; headers: Headers; credentials: RequestCredentials; body: string };

function jsonResponse(status: number, body: unknown, contentType = 'application/json'): Response {
  return new Response(body === null ? null : JSON.stringify(body), { status, headers: { 'content-type': contentType } });
}

function problem(status: number, code: string, extra: Record<string, unknown> = {}) {
  return jsonResponse(
    status,
    { type: `urn:fundly:problem:${code}`, title: 'T', status, detail: `detail ${code}`, code, correlation_id: 'corr-1', ...extra },
    'application/problem+json',
  );
}

let calls: Call[] = [];
let queue: (() => Response)[] = [];

beforeEach(() => {
  calls = [];
  queue = [];
  document.cookie = 'XSRF-TOKEN=abc%3D%3Dxyz; path=/';
  vi.stubGlobal(
    'fetch',
    vi.fn(async (req: Request) => {
      calls.push({ url: req.url, method: req.method, headers: req.headers, credentials: req.credentials, body: req.body ? await req.text() : '' });
      const next = queue.shift();
      return next ? next() : jsonResponse(200, { data: {} });
    }),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
  setStepUpHandler(null);
  setSessionEndedHandler(null);
});

describe('readCookie', () => {
  it('url-decodes the XSRF cookie', () => {
    expect(readCookie('XSRF-TOKEN', 'a=1; XSRF-TOKEN=abc%3D%3Dxyz; b=2')).toBe('abc==xyz');
    expect(readCookie('missing', 'a=1')).toBeNull();
  });
});

describe('policy headers', () => {
  it('sends credentials, Accept and the decoded XSRF header on GET without an Idempotency-Key', async () => {
    queue.push(() => jsonResponse(200, { data: { id: 'u1' } }));
    const result = unwrap(await api.GET('/api/v1/me'));
    expect(result).toEqual({ data: { id: 'u1' } });
    const c = calls[0]!;
    expect(c.method).toBe('GET');
    expect(new URL(c.url).pathname).toBe('/api/v1/me');
    expect(c.credentials).toBe('include');
    expect(c.headers.get('Accept')).toBe('application/json');
    expect(c.headers.get('X-XSRF-TOKEN')).toBe('abc==xyz');
    expect(c.headers.get('Idempotency-Key')).toBeNull();
  });

  it('adds a UUID Idempotency-Key on POST and keeps the JSON body', async () => {
    queue.push(() => jsonResponse(200, { data: { status: 'mfa_required' } }));
    await api.POST('/api/v1/auth/login', { body: { email: 'a@b.test', password: 'pw' } });
    const c = calls[0]!;
    expect(c.headers.get('Idempotency-Key')).toMatch(/^[0-9a-f-]{36}$/);
    expect(JSON.parse(c.body)).toEqual({ email: 'a@b.test', password: 'pw' });
  });
});

describe('problem parsing', () => {
  it('throws a typed ApiProblem from problem+json', async () => {
    queue.push(() => problem(422, 'validation-failed', { errors: { email: ['The email is invalid.'] } }));
    const err = await api.POST('/api/v1/auth/login', { body: { email: 'x', password: 'y' } }).catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiProblem);
    const p = err as ApiProblem;
    expect(p.status).toBe(422);
    expect(p.code).toBe('validation-failed');
    expect(p.correlationId).toBe('corr-1');
    expect(p.errors.email).toEqual(['The email is invalid.']);
    expect(p.messages).toEqual(['The email is invalid.']);
  });

  it('builds a problem from a non-JSON body', () => {
    const p = problemFromBody(502, null);
    expect(p.status).toBe(502);
    expect(p.code).toBe('http-502');
    expect(p.title).toBe('Server error');
  });

  it('recognises step-up by URN suffix and extensions are kept', () => {
    const p = problemFromBody(401, { type: 'urn:fundly:problem:step-up-required', title: 't', status: 401, detail: 'd', code: 'step-up-required', max_age_minutes: 5 });
    expect(p.isStepUpRequired).toBe(true);
    expect(p.isSessionEnded).toBe(false);
    expect(p.extensions.max_age_minutes).toBe(5);
  });
});

describe('retries', () => {
  it('on 419 refreshes the csrf cookie and retries once with the same Idempotency-Key', async () => {
    queue.push(
      () => jsonResponse(419, { type: 'urn:fundly:problem:http-error', title: 'HTTP error', status: 419, detail: 'CSRF token mismatch.', code: 'http-error' }),
      () => new Response(null, { status: 204 }),
      () => jsonResponse(200, { data: { status: 'authenticated' } }),
    );
    await api.POST('/api/v1/auth/login', { body: { email: 'a@b.test', password: 'pw' } });
    expect(calls.map((c) => new URL(c.url).pathname)).toEqual(['/api/v1/auth/login', '/api/v1/auth/csrf-cookie', '/api/v1/auth/login']);
    expect(calls[2]!.headers.get('Idempotency-Key')).toBe(calls[0]!.headers.get('Idempotency-Key'));
    expect(JSON.parse(calls[2]!.body)).toEqual({ email: 'a@b.test', password: 'pw' });
  });

  it('does not loop on a second 419', async () => {
    const mismatch = () => jsonResponse(419, { code: 'http-error', status: 419, title: 'x', detail: 'CSRF token mismatch.', type: 'x' });
    queue.push(mismatch, () => new Response(null, { status: 204 }), mismatch);
    await expect(policyFetch(new Request('http://localhost/api/v1/me'))).rejects.toMatchObject({ status: 419 });
    expect(calls).toHaveLength(3);
  });

  it('opens step-up on step-up-required and retries after success', async () => {
    const handler = vi.fn(() => Promise.resolve(true));
    setStepUpHandler(handler);
    queue.push(() => problem(401, 'step-up-required', { max_age_minutes: 5 }), () => jsonResponse(200, { data: { ok: true } }));
    const res = await policyFetch(new Request('http://localhost/api/v1/users/1', { method: 'PATCH', body: '{"a":1}' }));
    expect(res.status).toBe(200);
    expect(handler).toHaveBeenCalledOnce();
    expect(calls).toHaveLength(2);
    expect(calls[1]!.body).toBe('{"a":1}');
  });

  it('throws the step-up problem when the user cancels', async () => {
    setStepUpHandler(() => Promise.resolve(false));
    queue.push(() => problem(401, 'step-up-required'));
    await expect(policyFetch(new Request('http://localhost/api/v1/users/1', { method: 'PATCH' }))).rejects.toMatchObject({ code: 'step-up-required' });
  });
});

describe('session ended', () => {
  it('notifies on 401 unauthenticated from a normal endpoint', async () => {
    const onEnded = vi.fn();
    setSessionEndedHandler(onEnded);
    queue.push(() => problem(401, 'session-expired'));
    await expect(api.GET('/api/v1/me/effective-access')).rejects.toBeInstanceOf(ApiProblem);
    expect(onEnded).toHaveBeenCalledOnce();
  });

  it('does not treat a failed login as a session end', async () => {
    const onEnded = vi.fn();
    setSessionEndedHandler(onEnded);
    queue.push(() => problem(401, 'authentication-failed'));
    await expect(api.POST('/api/v1/auth/login', { body: { email: 'a', password: 'b' } })).rejects.toMatchObject({ code: 'authentication-failed' });
    expect(onEnded).not.toHaveBeenCalled();
  });
});

describe('multipart uploads (formBody)', () => {
  it('sends FormData through the policy client with XSRF, Idempotency-Key and the browser boundary', async () => {
    queue.push(() => jsonResponse(201, { data: { id: 'd1' } }));
    const file = new File(['%PDF-1.4'], 'statement.pdf', { type: 'application/pdf' });
    const fields = { document_type: 'STATEMENT_6M', checklist_item_id: 'c1', party_id: null, title: null };
    unwrap(
      await api.POST('/api/v1/applications/{id}/documents', {
        params: { path: { id: 'a1' }, header: { 'Idempotency-Key': 'upload-key-123' } },
        body: { ...fields, file: file.name },
        bodySerializer: formBody({ ...fields, file }),
      }),
    );
    const c = calls[0]!;
    expect(c.method).toBe('POST');
    expect(c.headers.get('Idempotency-Key')).toBe('upload-key-123');
    expect(c.headers.get('X-XSRF-TOKEN')).toBe('abc==xyz');
    expect(c.headers.get('Content-Type')).toMatch(/^multipart\/form-data; boundary=/);
    expect(c.body).toContain('name="document_type"');
    expect(c.body).toContain('STATEMENT_6M');
    expect(c.body).toMatch(/name="file"; filename=/);
    expect(c.body).not.toContain('name="party_id"');
    // The serializer itself keeps the File and its name (jsdom→undici re-encoding in tests drops it).
    const form = formBody({ ...fields, file })();
    expect((form.get('file') as File).name).toBe('statement.pdf');
    expect(form.has('title')).toBe(false);
  });

  it('reads binary responses as blobs and exposes the ETag helper', async () => {
    queue.push(() => new Response('abc', { status: 200, headers: { 'content-type': 'application/octet-stream' } }));
    const r = await api.GET('/api/v1/documents/{id}/versions/{versionId}/content', { params: { path: { id: 'd1', versionId: 'v1' } }, parseAs: 'blob' });
    const blob = unwrap(r);
    expect(blob.size).toBe(3);
    expect(typeof blob.text).toBe('function');
    expect(etagOf(new Response(null, { headers: { ETag: 'W/"7"' } }))).toBe('W/"7"');
  });
});
