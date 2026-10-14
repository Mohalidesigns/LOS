import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { MessageSquareText, Send } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { isApiProblem } from '@/api/problem';
import { workflowApi } from '@/api/workflow';
import { Button, Card, CardHeader, Checkbox, DateTimeText, EmptyState, ErrorSummary, Pill, SectionError, Skeleton, TextArea, useToast } from '@/components';
import { useCase } from '@/features/applications/case/CaseContext';
import { problemLines } from '@/features/applications/credit/domain';
import { appKeys } from '@/features/applications/queries';
import { mentionIds } from './domain';

const notesKey = (id: string) => [...appKeys.case(id), 'notes'] as const;

/** FR-WFL-010: the case collaboration thread; type @Name to mention someone who can see the case. */
export function NotesTab() {
  const { app, permissions } = useCase();
  const qc = useQueryClient();
  const toast = useToast();
  const notes = useQuery({ queryKey: notesKey(app.id), queryFn: () => workflowApi.notes(app.id) });
  const people = useQuery({ queryKey: [...notesKey(app.id), 'people'], queryFn: () => workflowApi.mentionable(app.id), staleTime: 300_000 });
  const [body, setBody] = useState('');
  const [customer, setCustomer] = useState(false);
  const [key, setKey] = useState(idempotencyKey);
  const mentions = mentionIds(body, people.data ?? []);
  const add = useMutation({
    mutationFn: () => workflowApi.addNote(app.id, body.trim(), customer ? 'customer' : 'internal', mentions, key),
    onSuccess: async () => {
      toast.show(mentions.length > 0 ? `Note added; ${mentions.length} person(s) notified.` : 'Note added.', 'success');
      setBody('');
      setCustomer(false);
      setKey(idempotencyKey());
      await qc.invalidateQueries({ queryKey: notesKey(app.id) });
    },
  });

  return (
    <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <Card>
        <CardHeader title="Case notes" titleId="notes-h" level={2} subtitle="Newest first. Notes cannot be edited; add a correction instead." />
        {notes.isPending ? (
          <Skeleton className="h-[12rem] w-full rounded-card" />
        ) : notes.isError ? (
          <SectionError error={notes.error} title="Notes couldn't load" onRetry={() => void notes.refetch()} />
        ) : notes.data.length === 0 ? (
          <EmptyState icon={<MessageSquareText aria-hidden="true" className="h-8 w-8" />} title="No notes yet">
            Keep the team in the loop here: what the customer said, what you are waiting for.
          </EmptyState>
        ) : (
          <ul className="space-y-3">
            {notes.data.map((n) => (
              <li key={n.id} className="rounded-control border p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="text-body-sm font-semibold text-primary">{n.author?.name ?? 'Staff'}</p>
                  <span className="flex items-center gap-2 text-meta text-tertiary">
                    {n.visibility === 'customer' && <Pill tone="info">Customer-visible</Pill>}
                    <DateTimeText value={n.created_at} />
                  </span>
                </div>
                <p className="mt-1 whitespace-pre-wrap text-body-sm text-secondary">{n.body}</p>
                {n.mentions.length > 0 && <p className="mt-1 text-meta text-tertiary">Mentioned: {n.mentions.map((m) => m?.name ?? 'staff').join(', ')}</p>}
              </li>
            ))}
          </ul>
        )}
      </Card>
      <aside aria-label="Add a note">
        <Card>
          <CardHeader title="Add a note" titleId="add-note-h" level={2} />
          <div className="space-y-3">
            {add.error && <ErrorSummary title="Not added" problem={isApiProblem(add.error) ? add.error : null} messages={problemLines(add.error)} />}
            <TextArea label="Note" rows={5} value={body} onChange={(e) => setBody(e.target.value)} maxLength={4000} helper="Type @ and a colleague's full name to notify them" />
            {mentions.length > 0 && (
              <p className="text-meta text-tertiary">
                Will notify:{' '}
                {(people.data ?? [])
                  .filter((p) => mentions.includes(p.id))
                  .map((p) => p.name)
                  .join(', ')}
              </p>
            )}
            {permissions.has('application:originate') && (
              <Checkbox label="Customer-visible" description="Shown to the applicant on the portal when it launches (P4)." checked={customer} onChange={(e) => setCustomer(e.target.checked)} />
            )}
            <Button className="w-full" leadingIcon={<Send aria-hidden="true" className="h-icon-sm w-icon-sm" />} loading={add.isPending} disabled={body.trim().length < 2} onClick={() => add.mutate()}>
              Add note
            </Button>
          </div>
        </Card>
      </aside>
    </div>
  );
}
