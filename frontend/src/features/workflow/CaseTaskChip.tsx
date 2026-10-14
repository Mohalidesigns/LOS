import { useQuery } from '@tanstack/react-query';
import { workflowApi } from '@/api/workflow';
import { SlaChip } from '@/components';
import { appKeys } from '@/features/applications/queries';
import { remainingLabel, slaChip } from './domain';

/** Case header: the open stage task, who has it and its SLA (FR-WFL-006). */
export function CaseTaskChip({ applicationId }: { applicationId: string }) {
  const q = useQuery({ queryKey: [...appKeys.case(applicationId), 'workflow'], queryFn: () => workflowApi.forApplication(applicationId), refetchInterval: 60_000 });
  const task = q.data?.current;
  if (!task) return null;
  const chip = slaChip(task);
  return (
    <p className="mt-1 flex flex-wrap items-center gap-x-2 text-meta text-tertiary">
      <span>
        {task.stage.name} · {task.assignee ? `with ${task.assignee.name ?? 'staff'}` : 'waiting in the queue'}
      </span>
      {chip && <SlaChip state={chip} remaining={remainingLabel(task)} />}
    </p>
  );
}
