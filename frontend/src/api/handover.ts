/** Typed adapters for handover (package, acknowledgement) and outbound webhooks (endpoints, deliveries). */
import { api, idempotencyKey, unwrap } from './client';
import type { components, operations } from './schema';

type S = components['schemas'];
type Json<O extends keyof operations, C extends number> = operations[O]['responses'] extends Record<C, { content: { 'application/json': infer B } }> ? B : never;

export type HandoverPackage = S['HandoverPackage'];
export type HandoverView = Json<'getApplicationHandover', 200>['data'];
export type WebhookEndpoint = S['WebhookEndpoint'];
export type WebhookDelivery = S['WebhookDelivery'];
export type RegisteredEndpoint = Json<'registerWebhookEndpoint', 201>['data'];

const idem = (key: string) => ({ 'Idempotency-Key': key });

export const handoverApi = {
  async forApplication(applicationId: string): Promise<HandoverView> {
    return unwrap(await api.GET('/api/v1/applications/{id}/handover', { params: { path: { id: applicationId } } })).data;
  },
  async acknowledge(packageId: string, note: string, key: string = idempotencyKey()): Promise<HandoverPackage> {
    return unwrap(await api.POST('/api/v1/handover-packages/{id}/actions/acknowledge', { params: { path: { id: packageId }, header: idem(key) }, body: { note } })).data;
  },
};

export const webhooksApi = {
  async endpoints(): Promise<WebhookEndpoint[]> {
    return unwrap(await api.GET('/api/v1/webhook-endpoints', {})).data;
  },
  async register(body: { name: string; url: string; events: string[] }, key: string = idempotencyKey()): Promise<RegisteredEndpoint> {
    return unwrap(await api.POST('/api/v1/webhook-endpoints', { params: { header: idem(key) }, body })).data;
  },
  async disable(id: string, key: string = idempotencyKey()): Promise<WebhookEndpoint> {
    return unwrap(await api.POST('/api/v1/webhook-endpoints/{id}/actions/disable', { params: { path: { id }, header: idem(key) } })).data;
  },
  async deliveries(): Promise<WebhookDelivery[]> {
    return unwrap(await api.GET('/api/v1/webhook-deliveries', {})).data;
  },
  async redeliver(id: string, key: string = idempotencyKey()): Promise<WebhookDelivery> {
    return unwrap(await api.POST('/api/v1/webhook-deliveries/{id}/actions/redeliver', { params: { path: { id }, header: idem(key) } })).data;
  },
};
