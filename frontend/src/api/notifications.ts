/** Typed adapters for the in-app inbox and the per-application communication log. */
import { api, idempotencyKey, unwrap } from './client';
import type { components, operations } from './schema';

type S = components['schemas'];
type Json<O extends keyof operations, C extends number> = operations[O]['responses'] extends Record<C, { content: { 'application/json': infer B } }> ? B : never;

export type InAppNotification = S['InAppNotification'];
export type Communication = S['Communication'];
export type Inbox = Json<'listMyNotifications', 200>;

const idem = (key: string) => ({ 'Idempotency-Key': key });

export const notificationsApi = {
  async inbox(unreadOnly = false): Promise<Inbox> {
    return unwrap(await api.GET('/api/v1/me/notifications', { params: { query: unreadOnly ? { 'filter[unread]': 'true' } : {} } }));
  },
  async read(id: string, key: string = idempotencyKey()): Promise<InAppNotification> {
    return unwrap(await api.POST('/api/v1/me/notifications/{id}/actions/read', { params: { path: { id }, header: idem(key) } })).data;
  },
  async readAll(key: string = idempotencyKey()): Promise<number> {
    return unwrap(await api.POST('/api/v1/me/notifications/actions/read-all', { params: { header: idem(key) } })).data.marked;
  },
  async forApplication(applicationId: string): Promise<Communication[]> {
    return unwrap(await api.GET('/api/v1/applications/{id}/communications', { params: { path: { id: applicationId } } })).data;
  },
};
