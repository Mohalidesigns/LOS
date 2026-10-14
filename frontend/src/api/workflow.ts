/** Typed adapters for the workflow inbox, tasks, saved views and case notes (FR-WFL-004..010). */
import { api, idempotencyKey, unwrap } from './client';
import type { components } from './schema';

type S = components['schemas'];

export type WorkflowTask = S['WorkflowTask'];
export type ApplicationWorkflow = S['ApplicationWorkflow'];
export type TaskView = S['TaskView'];
export type TaskViewFilters = S['TaskViewFilters'];
export type CaseNote = S['CaseNote'];
export type WorkflowPerson = S['WorkflowPerson'];
export type InboxView = 'mine' | 'queue' | 'team';
export type Inbox = { data: WorkflowTask[]; meta: { view: string; counts: { mine: number; mine_breached: number }; can_reassign: boolean } };

const idem = (key: string) => ({ 'Idempotency-Key': key });

export const workflowApi = {
  async inbox(filters: TaskViewFilters, sort: 'due' | '-opened_at' = 'due'): Promise<Inbox> {
    const query: Record<string, string> = { sort };
    if (filters.view) query['filter[view]'] = filters.view;
    if (filters.stage) query['filter[stage]'] = filters.stage;
    if (filters.sla) query['filter[sla]'] = filters.sla;
    return unwrap(await api.GET('/api/v1/me/tasks', { params: { query } }));
  },
  async claim(id: string, key: string = idempotencyKey()): Promise<WorkflowTask> {
    return unwrap(await api.POST('/api/v1/tasks/{id}/actions/claim', { params: { path: { id }, header: idem(key) } })).data;
  },
  async release(id: string, reason: string | null, key: string = idempotencyKey()): Promise<WorkflowTask> {
    return unwrap(await api.POST('/api/v1/tasks/{id}/actions/release', { params: { path: { id }, header: idem(key) }, body: { reason } })).data;
  },
  async reassign(id: string, assigneeId: string | null, reason: string, key: string = idempotencyKey()): Promise<WorkflowTask> {
    return unwrap(await api.POST('/api/v1/tasks/{id}/actions/reassign', { params: { path: { id }, header: idem(key) }, body: { assignee_id: assigneeId, reason } })).data;
  },
  async bulkAssign(taskIds: string[], assigneeId: string | null, reason: string | null, key: string = idempotencyKey()): Promise<{ assigned: number }> {
    return unwrap(await api.POST('/api/v1/tasks/actions/bulk-assign', { params: { header: idem(key) }, body: { task_ids: taskIds, assignee_id: assigneeId, reason } })).data;
  },
  async candidates(taskId: string): Promise<WorkflowPerson[]> {
    return unwrap(await api.GET('/api/v1/tasks/{id}/candidates', { params: { path: { id: taskId } } })).data;
  },
  async mentionable(applicationId: string): Promise<WorkflowPerson[]> {
    return unwrap(await api.GET('/api/v1/applications/{id}/mentionable', { params: { path: { id: applicationId } } })).data;
  },
  async views(): Promise<TaskView[]> {
    return unwrap(await api.GET('/api/v1/me/task-views', {})).data;
  },
  async saveView(name: string, filters: TaskViewFilters, key: string = idempotencyKey()): Promise<TaskView> {
    return unwrap(await api.POST('/api/v1/me/task-views', { params: { header: idem(key) }, body: { name, filters } })).data;
  },
  async deleteView(id: string): Promise<void> {
    unwrap(await api.DELETE('/api/v1/me/task-views/{id}', { params: { path: { id } } }));
  },
  async forApplication(applicationId: string): Promise<ApplicationWorkflow> {
    return unwrap(await api.GET('/api/v1/applications/{id}/workflow', { params: { path: { id: applicationId } } })).data;
  },
  async notes(applicationId: string): Promise<CaseNote[]> {
    return unwrap(await api.GET('/api/v1/applications/{id}/notes', { params: { path: { id: applicationId } } })).data;
  },
  async addNote(applicationId: string, body: string, visibility: 'internal' | 'customer', mentionUserIds: string[], key: string = idempotencyKey()): Promise<CaseNote> {
    return unwrap(await api.POST('/api/v1/applications/{id}/notes', { params: { path: { id: applicationId }, header: idem(key) }, body: { body, visibility, mention_user_ids: mentionUserIds } })).data;
  },
};
