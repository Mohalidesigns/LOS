import { describe, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';
import { act, renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { Application, WithEtag } from '@/api/lending';
import { ApiProblem } from '@/api/problem';
import { appKeys, isStaleProblem, useIfMatchMutation } from './queries';

const app = { id: 'a1', reference: 'DEMO-2026-000001', status: 'draft', version: 1 } as unknown as Application;

function setup(etag = 'W/"v1"') {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity }, mutations: { retry: false } } });
  qc.setQueryData<WithEtag<Application>>(appKeys.detail('a1'), { data: app, etag });
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={qc}>{children}</QueryClientProvider>;
  return { qc, wrapper };
}

const stale = () => new ApiProblem({ type: 'urn:fundly:problem:stale-resource', title: 'Precondition failed', status: 412, detail: 'The resource changed.', code: 'stale-resource' });

describe('useIfMatchMutation', () => {
  it('sends the cached ETag as If-Match and caches the new ETag on success', async () => {
    const { qc, wrapper } = setup();
    const run = vi.fn((etag: string, vars: { tenor: number }) => {
      expect(etag).toBe('W/"v1"');
      const next: Application = { ...app, tenor_months: vars.tenor, version: 2 };
      return Promise.resolve({ data: next, etag: 'W/"v2"' });
    });
    const onSuccess = vi.fn();
    const { result } = renderHook(() => useIfMatchMutation('a1', run, { onSuccess }), { wrapper });
    await act(async () => {
      await result.current.mutateAsync({ tenor: 12 });
    });
    expect(run).toHaveBeenCalledWith('W/"v1"', { tenor: 12 });
    expect(onSuccess).toHaveBeenCalledWith(expect.objectContaining({ version: 2 }));
    expect(qc.getQueryData<WithEtag<Application>>(appKeys.detail('a1'))?.etag).toBe('W/"v2"');
  });

  it('calls onStale on 412 and leaves the cache untouched', async () => {
    const { qc, wrapper } = setup();
    const onStale = vi.fn();
    const { result } = renderHook(() => useIfMatchMutation('a1', () => Promise.reject(stale()), { onStale }), { wrapper });
    act(() => result.current.mutate(undefined));
    await waitFor(() => expect(result.current.isError).toBe(true));
    expect(onStale).toHaveBeenCalledTimes(1);
    expect(isStaleProblem(result.current.error)).toBe(true);
    expect(qc.getQueryData<WithEtag<Application>>(appKeys.detail('a1'))?.etag).toBe('W/"v1"');
  });

  it('does not treat other errors as stale', async () => {
    const { wrapper } = setup();
    const onStale = vi.fn();
    const problem = new ApiProblem({ type: 'urn:fundly:problem:validation-failed', title: 'Invalid', status: 422, detail: 'Bad', code: 'validation-failed' });
    const { result } = renderHook(() => useIfMatchMutation('a1', () => Promise.reject(problem), { onStale }), { wrapper });
    act(() => result.current.mutate(undefined));
    await waitFor(() => expect(result.current.isError).toBe(true));
    expect(onStale).not.toHaveBeenCalled();
  });

  it('refuses to send without a loaded ETag', async () => {
    const qc = new QueryClient();
    const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={qc}>{children}</QueryClientProvider>;
    const run = vi.fn();
    const { result } = renderHook(() => useIfMatchMutation('zzz', run), { wrapper });
    act(() => result.current.mutate(undefined));
    await waitFor(() => expect(result.current.isError).toBe(true));
    expect(run).not.toHaveBeenCalled();
  });
});
