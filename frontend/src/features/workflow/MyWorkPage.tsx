import { useMemo, useState } from 'react';
import { Link } from 'react-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Bookmark, Hand, ListChecks, Trash2, UserRoundCog } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { isApiProblem } from '@/api/problem';
import { workflowApi, type InboxView, type TaskViewFilters, type WorkflowTask } from '@/api/workflow';
import { Button, Card, CardHeader, DateTimeText, EmptyState, ErrorSummary, Modal, SectionError, Select, Skeleton, SlaChip, TextArea, TextInput, useToast } from '@/components';
import { problemLines } from '@/features/applications/credit/domain';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { remainingLabel, SLA_FILTERS, slaChip, stageOptions, VIEW_LABEL } from './domain';

const inboxKey = (f: TaskViewFilters) => ['workflow', 'inbox', f] as const;

/** FR-WFL-009: one inbox across every application and stage, with queues, filters, bulk action and saved views. */
export function MyWorkPage() {
  return (
    <RequirePermission anyOf={['application:view']}>
      <MyWork />
    </RequirePermission>
  );
}

function MyWork() {
  const qc = useQueryClient();
  const toast = useToast();
  const [filters, setFilters] = useState<TaskViewFilters>({ view: 'mine' });
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [reassigning, setReassigning] = useState<WorkflowTask[] | null>(null);
  const [saving, setSaving] = useState(false);
  const inbox = useQuery({ queryKey: inboxKey(filters), queryFn: () => workflowApi.inbox(filters), refetchInterval: 60_000 });
  const views = useQuery({ queryKey: ['workflow', 'views'], queryFn: () => workflowApi.views() });
  const refresh = async () => {
    setSelected(new Set());
    await qc.invalidateQueries({ queryKey: ['workflow'] });
    await qc.invalidateQueries({ queryKey: ['dashboard', 'nav-counts'] });
  };
  const act = useMutation({
    mutationFn: async (a: { kind: 'claim' | 'release'; task: WorkflowTask } | { kind: 'bulk-claim'; ids: string[] }) => {
      if (a.kind === 'bulk-claim') await workflowApi.bulkAssign(a.ids, null, null, idempotencyKey());
      else if (a.kind === 'claim') await workflowApi.claim(a.task.id, idempotencyKey());
      else await workflowApi.release(a.task.id, null, idempotencyKey());
    },
    onSuccess: async (_r, a) => {
      toast.show(a.kind === 'release' ? 'Task returned to its queue.' : 'Task(s) are yours now.', 'success');
      await refresh();
    },
  });
  const deleteView = useMutation({ mutationFn: (id: string) => workflowApi.deleteView(id), onSuccess: () => void qc.invalidateQueries({ queryKey: ['workflow', 'views'] }) });

  const view: InboxView = filters.view ?? 'mine';
  const tasks = useMemo(() => inbox.data?.data ?? [], [inbox.data]);
  const stages = useMemo(() => stageOptions(tasks), [tasks]);
  const canReassign = inbox.data?.meta.can_reassign ?? false;
  const picked = tasks.filter((t) => selected.has(t.id));
  const toggle = (id: string) => setSelected((s) => (s.has(id) ? new Set([...s].filter((x) => x !== id)) : new Set([...s, id])));
  const views_ = ['mine', 'queue', ...(canReassign ? ['team'] : [])] as InboxView[];

  return (
    <div className="space-y-5">
      <Card>
        <CardHeader
          title="My work"
          titleId="work-h"
          level={2}
          subtitle={
            inbox.data
              ? `${inbox.data.meta.counts.mine} task(s) with you${inbox.data.meta.counts.mine_breached > 0 ? `, ${inbox.data.meta.counts.mine_breached} past SLA` : ''}`
              : 'Tasks across every application and stage'
          }
          actions={
            <Button size="sm" variant="secondary" leadingIcon={<Bookmark aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => setSaving(true)}>
              Save view
            </Button>
          }
        />
        <div role="tablist" aria-label="Inbox" className="mb-4 flex flex-wrap gap-2">
          {views_.map((v) => (
            <Button
              key={v}
              size="sm"
              role="tab"
              aria-selected={view === v}
              variant={view === v ? 'primary' : 'secondary'}
              onClick={() => {
                setSelected(new Set());
                setFilters({ ...filters, view: v });
              }}
            >
              {VIEW_LABEL[v]}
            </Button>
          ))}
        </div>
        <div className="grid gap-3 sm:grid-cols-3">
          <Select label="Stage" value={filters.stage ?? ''} onChange={(e) => setFilters({ ...filters, stage: e.target.value || undefined })} options={stages} />
          <Select label="SLA" value={filters.sla ?? ''} onChange={(e) => setFilters({ ...filters, sla: (e.target.value || undefined) as TaskViewFilters['sla'] })} options={[...SLA_FILTERS]} />
          <Select
            label="Saved view"
            value=""
            onChange={(e) => {
              const v = views.data?.find((x) => x.id === e.target.value);
              if (v) setFilters({ view: 'mine', ...v.filters });
            }}
            options={[{ value: '', label: (views.data ?? []).length === 0 ? 'No saved views' : 'Apply a saved view' }, ...(views.data ?? []).map((v) => ({ value: v.id, label: v.name }))]}
          />
        </div>
        {(views.data ?? []).length > 0 && (
          <ul className="mt-2 flex flex-wrap gap-2 text-meta text-tertiary">
            {(views.data ?? []).map((v) => (
              <li key={v.id} className="inline-flex items-center gap-1 rounded-pill bg-subtle px-2 py-0.5">
                {v.name}
                <button type="button" aria-label={`Delete saved view ${v.name}`} className="text-tertiary hover:text-danger" onClick={() => deleteView.mutate(v.id)}>
                  <Trash2 aria-hidden="true" className="h-3 w-3" />
                </button>
              </li>
            ))}
          </ul>
        )}
      </Card>

      {act.error && <ErrorSummary title="That didn't work" problem={isApiProblem(act.error) ? act.error : null} messages={problemLines(act.error)} />}

      <Card>
        {picked.length > 0 && (
          <div className="mb-3 flex flex-wrap items-center gap-2 rounded-control bg-subtle p-2 text-body-sm">
            <span className="font-medium">{picked.length} selected</span>
            {view === 'queue' && (
              <Button size="sm" leadingIcon={<Hand aria-hidden="true" className="h-icon-sm w-icon-sm" />} loading={act.isPending} onClick={() => act.mutate({ kind: 'bulk-claim', ids: picked.map((t) => t.id) })}>
                Claim selected
              </Button>
            )}
            {canReassign && (
              <Button size="sm" variant="secondary" leadingIcon={<UserRoundCog aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => setReassigning(picked)}>
                Assign selected
              </Button>
            )}
          </div>
        )}
        {inbox.isPending ? (
          <Skeleton className="h-[16rem] w-full rounded-card" />
        ) : inbox.isError ? (
          <SectionError error={inbox.error} title="Your tasks couldn't load" onRetry={() => void inbox.refetch()} />
        ) : tasks.length === 0 ? (
          <EmptyState icon={<ListChecks aria-hidden="true" className="h-8 w-8" />} title={view === 'mine' ? 'Nothing assigned to you' : view === 'queue' ? 'No tasks waiting in your queues' : 'No open team tasks'}>
            {view === 'mine' ? 'Pick up work from "Queues I can work".' : 'Try another stage or SLA filter.'}
          </EmptyState>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[52rem] text-body-sm">
              <thead className="text-left text-meta text-tertiary">
                <tr>
                  <th className="w-8 py-2 pr-2">
                    <span className="sr-only">Select</span>
                  </th>
                  <th className="py-2 pr-3 font-medium">Application</th>
                  <th className="py-2 pr-3 font-medium">Stage</th>
                  <th className="py-2 pr-3 font-medium">With</th>
                  <th className="py-2 pr-3 font-medium">SLA</th>
                  <th className="py-2 pr-3 font-medium">Opened</th>
                  <th className="py-2 font-medium">
                    <span className="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {tasks.map((t) => {
                  const chip = slaChip(t);
                  return (
                    <tr key={t.id} className="border-t">
                      <td className="py-2.5 pr-2">
                        <input type="checkbox" aria-label={`Select ${t.application.reference}`} checked={selected.has(t.id)} onChange={() => toggle(t.id)} />
                      </td>
                      <td className="py-2.5 pr-3">
                        <Link className="ref font-semibold text-link underline-offset-2 hover:underline" to={`/applications/${t.application.id}`}>
                          {t.application.reference}
                        </Link>
                        <p className="text-meta text-tertiary">{t.application.applicant_name ?? '—'}</p>
                      </td>
                      <td className="py-2.5 pr-3">{t.stage.name}</td>
                      <td className="py-2.5 pr-3">{t.assignee?.name ?? <span className="text-tertiary">Unassigned</span>}</td>
                      <td className="py-2.5 pr-3">{chip ? <SlaChip state={chip} remaining={remainingLabel(t)} /> : <span className="text-tertiary">—</span>}</td>
                      <td className="py-2.5 pr-3">
                        <DateTimeText value={t.opened_at} />
                      </td>
                      <td className="py-2.5 text-right">
                        {view === 'queue' && (
                          <Button size="sm" loading={act.isPending} onClick={() => act.mutate({ kind: 'claim', task: t })}>
                            Claim
                          </Button>
                        )}
                        {view === 'mine' && (
                          <Button size="sm" variant="ghost" loading={act.isPending} onClick={() => act.mutate({ kind: 'release', task: t })}>
                            Release
                          </Button>
                        )}
                        {view === 'team' && (
                          <Button size="sm" variant="secondary" onClick={() => setReassigning([t])}>
                            Reassign
                          </Button>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </Card>
      {reassigning && <ReassignDialog tasks={reassigning} onClose={() => setReassigning(null)} onDone={refresh} />}
      {saving && <SaveViewDialog filters={filters} onClose={() => setSaving(false)} />}
    </div>
  );
}

/** FR-WFL-005: to someone who can work the stage, with a reason kept in the history. */
function ReassignDialog({ tasks, onClose, onDone }: { tasks: WorkflowTask[]; onClose: () => void; onDone: () => Promise<void> }) {
  const toast = useToast();
  const first = tasks[0];
  const candidates = useQuery({ queryKey: ['workflow', 'candidates', first?.id], queryFn: () => workflowApi.candidates(first?.id ?? ''), enabled: first !== undefined });
  const [assignee, setAssignee] = useState('');
  const [reason, setReason] = useState('');
  const [key] = useState(idempotencyKey);
  const m = useMutation({
    mutationFn: async (): Promise<void> => {
      if (tasks.length === 1 && first) await workflowApi.reassign(first.id, assignee || null, reason.trim(), key);
      else
        await workflowApi.bulkAssign(
          tasks.map((t) => t.id),
          assignee || null,
          reason.trim(),
          key,
        );
    },
    onSuccess: async () => {
      toast.show(assignee ? 'Reassigned.' : 'Returned to the queue.', 'success');
      await onDone();
      onClose();
    },
  });
  const invalid = reason.trim().length < 5;
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!m.isPending}
      title={tasks.length === 1 ? 'Reassign task' : `Assign ${tasks.length} tasks`}
      description={tasks.length === 1 && first ? `${first.application.reference} · ${first.stage.name}` : 'Everyone must be able to work every selected task.'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={m.isPending}>
            Cancel
          </Button>
          <Button loading={m.isPending} disabled={invalid} onClick={() => m.mutate()}>
            {assignee ? 'Assign' : 'Return to queue'}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {m.error && <ErrorSummary title="Not reassigned" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
        <Select
          label="Assign to"
          value={assignee}
          onChange={(e) => setAssignee(e.target.value)}
          options={[{ value: '', label: tasks.length === 1 ? 'Back to the queue' : 'Choose a person' }, ...(candidates.data ?? []).map((c) => ({ value: c.id, label: `${c.name ?? c.id} (${c.open_tasks} open)` }))]}
          helper="People who hold the stage's permission for this branch and product, with their current load"
        />
        <TextArea label="Reason" required rows={2} value={reason} onChange={(e) => setReason(e.target.value)} maxLength={500} error={reason !== '' && invalid ? 'Give a reason (5 characters or more).' : undefined} />
      </div>
    </Modal>
  );
}

function SaveViewDialog({ filters, onClose }: { filters: TaskViewFilters; onClose: () => void }) {
  const qc = useQueryClient();
  const toast = useToast();
  const [name, setName] = useState('');
  const m = useMutation({
    mutationFn: () => workflowApi.saveView(name.trim(), filters),
    onSuccess: async () => {
      toast.show('View saved.', 'success');
      await qc.invalidateQueries({ queryKey: ['workflow', 'views'] });
      onClose();
    },
  });
  return (
    <Modal
      open
      onClose={onClose}
      title="Save this view"
      description="Saves the inbox, stage and SLA filters under a name."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={m.isPending} disabled={name.trim() === ''} onClick={() => m.mutate()}>
            Save view
          </Button>
        </>
      }
    >
      {m.error && <ErrorSummary title="Not saved" problem={isApiProblem(m.error) ? m.error : null} messages={problemLines(m.error)} />}
      <TextInput label="Name" value={name} onChange={(e) => setName(e.target.value)} maxLength={60} />
    </Modal>
  );
}
