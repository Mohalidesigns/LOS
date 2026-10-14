/** Pure workflow presentation: SLA chips and remaining time, inbox filters (FR-WFL-006/009). */
import type { WorkflowTask } from '@/api/workflow';
import type { SlaState } from '@/components/SlaChip';

/** The chip for a task: paused while on hold; no chip when the stage has no SLA. */
export function slaChip(task: Pick<WorkflowTask, 'sla' | 'status'>): SlaState | null {
  if (task.status !== 'open' || task.sla.state === 'none') return null;
  return task.sla.state;
}

/** "3h 20m left", "2h over", "paused": wall-clock to the breach deadline (business-hour deadlines are computed server-side). */
export function remainingLabel(task: Pick<WorkflowTask, 'sla'>, now: Date = new Date()): string | undefined {
  if (task.sla.state === 'paused') return 'paused';
  if (!task.sla.breach_at) return undefined;
  const minutes = Math.round((new Date(task.sla.breach_at).getTime() - now.getTime()) / 60_000);
  const abs = Math.abs(minutes);
  const text = abs >= 1440 ? `${Math.floor(abs / 1440)}d ${Math.floor((abs % 1440) / 60)}h` : abs >= 60 ? `${Math.floor(abs / 60)}h ${abs % 60}m` : `${abs}m`;
  return minutes >= 0 ? `${text} left` : `${text} over`;
}

export const VIEW_LABEL: Record<'mine' | 'queue' | 'team', string> = { mine: 'My tasks', queue: 'Queues I can work', team: 'Team' };

export const SLA_FILTERS = [
  { value: '', label: 'Any SLA' },
  { value: 'breached', label: 'Breached' },
  { value: 'at_risk', label: 'At risk' },
  { value: 'on_track', label: 'On track' },
  { value: 'paused', label: 'Paused (on hold)' },
] as const;

/** Distinct stages present in a task list, for the stage filter. */
export function stageOptions(tasks: readonly Pick<WorkflowTask, 'stage'>[]): { value: string; label: string }[] {
  const seen = new Map<string, string>();
  for (const t of tasks) seen.set(t.stage.code, t.stage.name);
  return [{ value: '', label: 'Any stage' }, ...[...seen].map(([value, label]) => ({ value, label })).sort((a, b) => a.label.localeCompare(b.label))];
}

/** @mentions typed as "@Name" resolved against the people who can be mentioned. */
export function mentionIds(body: string, people: readonly { id: string; name: string | null }[]): string[] {
  const lower = body.toLowerCase();
  return people.filter((p) => p.name !== null && lower.includes(`@${p.name.toLowerCase()}`)).map((p) => p.id);
}
