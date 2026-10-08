import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { policyFetch } from '@/api/client';
import { StepUpProvider } from './StepUpProvider';

const json = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status, headers: { 'content-type': status >= 400 ? 'application/problem+json' : 'application/json' } });

afterEach(() => vi.unstubAllGlobals());

describe('StepUpProvider', () => {
  it('opens the dialog on step-up-required, posts /auth/step-up, then retries the original call', async () => {
    const paths: string[] = [];
    const bodies: string[] = [];
    const responses = [
      json(401, { type: 'urn:fundly:problem:step-up-required', title: 'Step-up', status: 401, detail: 'Re-authenticate', code: 'step-up-required', correlation_id: 'c', max_age_minutes: 5 }),
      json(200, { data: { step_up_ref: '0192f0aa-0000-7000-8000-000000000000', valid_for_minutes: 5 } }),
      json(200, { data: { ok: true } }),
    ];
    vi.stubGlobal(
      'fetch',
      vi.fn(async (req: Request) => {
        paths.push(`${req.method} ${new URL(req.url).pathname}`);
        bodies.push(req.body ? await req.text() : '');
        return responses.shift() ?? json(500, {});
      }),
    );

    render(
      <StepUpProvider>
        <p>app</p>
      </StepUpProvider>,
    );

    const pending = policyFetch(new Request('http://localhost:3000/api/v1/users/u1', { method: 'PATCH', body: '{"status":"disabled"}' }));

    const user = userEvent.setup();
    await user.type(await screen.findByLabelText(/Password/), 'S3cret-pass!');
    await user.type(screen.getByLabelText(/Authenticator code/), '123456');
    await user.click(screen.getByRole('button', { name: 'Confirm' }));

    const res = await pending;
    expect(res.status).toBe(200);
    expect(paths).toEqual(['PATCH /api/v1/users/u1', 'POST /api/v1/auth/step-up', 'PATCH /api/v1/users/u1']);
    expect(JSON.parse(bodies[1]!)).toEqual({ password: 'S3cret-pass!', code: '123456' });
    expect(bodies[2]).toBe('{"status":"disabled"}');
    await waitFor(() => expect(screen.queryByLabelText(/Authenticator code/)).toBeNull());
  });
});
