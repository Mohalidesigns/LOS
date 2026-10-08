import type { components } from './schema';

export type ProblemDocument = components['schemas']['Problem'];

/** Machine codes the client reacts to (backend ProblemRenderer catalogue). */
export const PROBLEM = {
  stepUpRequired: 'step-up-required',
  unauthenticated: 'unauthenticated',
  sessionExpired: 'session-expired',
  sessionRevoked: 'session-revoked',
  authenticationFailed: 'authentication-failed',
  csrfMismatch: 'csrf-token-mismatch',
  validationFailed: 'validation-failed',
  network: 'network-error',
} as const;

/** Codes meaning "the session is gone": route to /login with the session-ended banner. */
export const SESSION_ENDED_CODES: readonly string[] = [PROBLEM.unauthenticated, PROBLEM.sessionExpired, PROBLEM.sessionRevoked];

/**
 * Typed RFC 9457 problem thrown by the API client for every non-2xx response.
 * `errors` carries field-level validation messages (422).
 */
export class ApiProblem extends Error {
  readonly type: string;
  readonly title: string;
  readonly status: number;
  readonly detail: string;
  readonly code: string;
  readonly correlationId: string | null;
  readonly instance: string | null;
  readonly errors: Readonly<Record<string, readonly string[]>>;
  readonly extensions: Readonly<Record<string, unknown>>;

  constructor(doc: {
    type: string;
    title: string;
    status: number;
    detail: string;
    code: string;
    correlation_id?: string | null;
    instance?: string | null;
    errors?: Record<string, string[]>;
    extensions?: Record<string, unknown>;
  }) {
    super(doc.detail || doc.title);
    this.name = 'ApiProblem';
    this.type = doc.type;
    this.title = doc.title;
    this.status = doc.status;
    this.detail = doc.detail;
    this.code = doc.code;
    this.correlationId = doc.correlation_id ?? null;
    this.instance = doc.instance ?? null;
    this.errors = doc.errors ?? {};
    this.extensions = doc.extensions ?? {};
  }

  /** True when the code (or the URN suffix of `type`) matches. */
  is(code: string): boolean {
    return this.code === code || this.type.endsWith(`:${code}`) || this.type === code;
  }

  get isStepUpRequired(): boolean {
    return this.is(PROBLEM.stepUpRequired);
  }

  get isSessionEnded(): boolean {
    return this.status === 401 && SESSION_ENDED_CODES.some((c) => this.is(c));
  }

  get isCsrfMismatch(): boolean {
    return this.status === 419 || this.is(PROBLEM.csrfMismatch);
  }

  /** Flattened messages for an ErrorSummary. */
  get messages(): string[] {
    const fieldMessages = Object.values(this.errors).flat();
    return fieldMessages.length > 0 ? fieldMessages : [this.detail || this.title];
  }
}

function isRecord(v: unknown): v is Record<string, unknown> {
  return typeof v === 'object' && v !== null && !Array.isArray(v);
}

function str(v: unknown, fallback: string): string {
  return typeof v === 'string' && v.length > 0 ? v : fallback;
}

function parseErrors(v: unknown): Record<string, string[]> | undefined {
  if (!isRecord(v)) return undefined;
  const out: Record<string, string[]> = {};
  for (const [k, list] of Object.entries(v)) {
    if (Array.isArray(list)) out[k] = list.filter((m): m is string => typeof m === 'string');
    else if (typeof list === 'string') out[k] = [list];
  }
  return out;
}

const KNOWN_KEYS = new Set(['type', 'title', 'status', 'detail', 'code', 'correlation_id', 'instance', 'errors']);

/** Build an ApiProblem from any response body (problem+json, plain JSON or nothing). */
export function problemFromBody(status: number, body: unknown, correlationHeader: string | null = null): ApiProblem {
  const doc = isRecord(body) ? body : {};
  const code = str(doc.code, status === 419 ? PROBLEM.csrfMismatch : `http-${status}`);
  const extensions: Record<string, unknown> = {};
  for (const [k, val] of Object.entries(doc)) if (!KNOWN_KEYS.has(k)) extensions[k] = val;
  return new ApiProblem({
    type: str(doc.type, `urn:fundly:problem:${code}`),
    title: str(doc.title, status >= 500 ? 'Server error' : 'Request failed'),
    status: typeof doc.status === 'number' ? doc.status : status,
    detail: str(doc.detail, str(doc.message, 'The request could not be completed.')),
    code,
    correlation_id: typeof doc.correlation_id === 'string' ? doc.correlation_id : correlationHeader,
    instance: typeof doc.instance === 'string' ? doc.instance : null,
    errors: parseErrors(doc.errors),
    extensions,
  });
}

export async function problemFromResponse(response: Response): Promise<ApiProblem> {
  let body: unknown = null;
  const type = response.headers.get('content-type') ?? '';
  if (type.includes('json')) {
    try {
      body = await response.json();
    } catch {
      body = null;
    }
  }
  return problemFromBody(response.status, body, response.headers.get('x-correlation-id'));
}

export function networkProblem(cause: unknown): ApiProblem {
  return new ApiProblem({
    type: `urn:fundly:problem:${PROBLEM.network}`,
    title: 'Network error',
    status: 0,
    detail: 'The server could not be reached. Check your connection and try again.',
    code: PROBLEM.network,
    extensions: { cause: cause instanceof Error ? cause.message : String(cause) },
  });
}

export function isApiProblem(e: unknown): e is ApiProblem {
  return e instanceof ApiProblem;
}
