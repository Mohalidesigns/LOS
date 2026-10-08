import { useState } from 'react';
import { useFieldArray, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { FileText, Plus, Save, Trash2 } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { creditApi, type CreditMemo, type Decision, type MemoSection as MemoSectionT } from '@/api/credit';
import type { Application } from '@/api/lending';
import { Button, CardHeader, DateTimeText, ErrorSummary, IconButton, MoneyText, Pill, SectionError, Select, Skeleton, TextArea, TextInput, useToast } from '@/components';
import { isApiProblem } from '@/api/problem';
import { appKeys, memoQuery } from '../queries';
import { Collapsible, ContentList, StaffName } from './CreditParts';
import { CREDIT_PERM, creditWorkReason, firstReason, needPermission, problemLines } from './domain';
import { memoDefaults, memoSchema, RECOMMENDATION_LABEL, toSaveBody, type MemoFormValues } from './memoForm';

export function MemoSection({ app, permissions, decision }: { app: Application; permissions: ReadonlySet<string>; decision: Decision | null }) {
  const memo = useQuery(memoQuery(app.id));
  const saveReason = firstReason(creditWorkReason(app.status), needPermission(permissions, CREDIT_PERM.analyse), decision ? null : 'Run a decision first; the memo is built on it.');

  return (
    <section aria-labelledby="memo-h" className="rounded-card border bg-surface p-5 shadow-card">
      <CardHeader
        title="Credit memo"
        titleId="memo-h"
        subtitle="Sections are filled from the application, bureau and latest decision. Add your narrative and recommendation; every save is a new version."
        actions={memo.data?.latest ? <Pill tone="info">{`v${memo.data.latest.version_no} saved`}</Pill> : undefined}
      />
      {memo.isPending ? (
        <Skeleton className="h-[16rem] w-full rounded-card" />
      ) : memo.isError ? (
        <SectionError error={memo.error} title="The credit memo couldn't load" onRetry={() => void memo.refetch()} />
      ) : (
        <>
          {memo.data.latest && decision && memo.data.latest.decision_id !== decision.id && (
            <p role="status" className="mb-3 rounded-control bg-warning p-3 text-body-sm text-primary">
              The saved memo (v{memo.data.latest.version_no}) refers to an earlier decision. Save a new version so it references decision #{decision.sequence}.
            </p>
          )}
          <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]">
            <div className="space-y-3">
              <h3 className="text-body font-semibold text-primary">Auto-populated sections</h3>
              <Sections sections={memo.data.draft_sections} empty="Sections appear once a decision has run." />
            </div>
            <MemoForm key={`${memo.data.latest?.id ?? 'new'}-${decision?.id ?? 'none'}`} app={app} latest={memo.data.latest} decision={decision} saveReason={saveReason} />
          </div>
          <VersionHistory versions={memo.data.versions} />
        </>
      )}
    </section>
  );
}

function Sections({ sections, empty }: { sections: MemoSectionT[]; empty: string }) {
  if (sections.length === 0) return <p className="rounded-control bg-subtle p-3 text-body-sm text-secondary">{empty}</p>;
  return (
    <ul className="space-y-3">
      {sections.map((s) => (
        <li key={s.key}>
          <article aria-labelledby={`ms-${s.key}`} className="rounded-card border p-4">
            <h4 id={`ms-${s.key}`} className="mb-2 flex items-center gap-2 text-body font-semibold text-emphasis">
              <FileText aria-hidden="true" className="h-icon-sm w-icon-sm text-indicator" />
              {s.title}
              <span className="text-meta font-normal text-tertiary">Read-only</span>
            </h4>
            <ContentList content={s.content} />
          </article>
        </li>
      ))}
    </ul>
  );
}

function MemoForm({ app, latest, decision, saveReason }: { app: Application; latest: CreditMemo | null; decision: Decision | null; saveReason: string | null }) {
  const qc = useQueryClient();
  const toast = useToast();
  const [key, setKey] = useState(idempotencyKey);
  const form = useForm<MemoFormValues>({ resolver: zodResolver(memoSchema), defaultValues: memoDefaults(latest, decision) });
  const conditions = useFieldArray({ control: form.control, name: 'conditions' });
  const errors = form.formState.errors;
  const save = useMutation({
    mutationFn: (v: MemoFormValues) => creditApi.saveMemo(app.id, toSaveBody(v), key),
    onSuccess: async (m) => {
      toast.show(`Credit memo v${m.version_no} saved`, 'success');
      setKey(idempotencyKey());
      await qc.invalidateQueries({ queryKey: appKeys.memo(app.id) });
    },
  });
  const recommendation = useWatch({ control: form.control, name: 'recommendation' });

  return (
    <form
      noValidate
      aria-label="Analyst narrative and recommendation"
      className="space-y-4 rounded-card bg-muted p-4"
      onSubmit={form.handleSubmit((v) => {
        if (saveReason) return;
        save.mutate(v);
      })}
    >
      <h3 className="text-body font-semibold text-primary">Recommendation</h3>
      {save.error && <ErrorSummary title="The memo was not saved" problem={isApiProblem(save.error) ? save.error : null} messages={problemLines(save.error)} />}
      <Select
        label="Recommendation"
        required
        {...form.register('recommendation')}
        options={(Object.keys(RECOMMENDATION_LABEL) as (keyof typeof RECOMMENDATION_LABEL)[]).map((k) => ({ value: k, label: RECOMMENDATION_LABEL[k] }))}
        error={errors.recommendation?.message}
        helper={decision ? `Engine outcome: decision #${decision.sequence}` : undefined}
      />
      <div className="grid gap-3 sm:grid-cols-2">
        <TextInput
          label={recommendation === 'counter_offer' ? 'Recommended amount (₦)' : 'Recommended amount (₦, optional)'}
          inputMode="decimal"
          required={recommendation === 'counter_offer'}
          {...form.register('recommended_amount')}
          error={errors.recommended_amount?.message}
        />
        <TextInput
          label={recommendation === 'counter_offer' ? 'Tenor (months)' : 'Tenor (months, optional)'}
          inputMode="numeric"
          required={recommendation === 'counter_offer'}
          {...form.register('recommended_tenor_months')}
          error={errors.recommended_tenor_months?.message}
        />
      </div>
      <TextArea label="Analyst narrative" required rows={8} {...form.register('narrative')} error={errors.narrative?.message} helper="Business, repayment capacity, risks and mitigants" />
      <fieldset>
        <legend className="text-body-sm font-semibold text-primary">Conditions</legend>
        <p className="text-meta text-tertiary">Conditions precedent or subsequent you propose for the approval</p>
        {conditions.fields.length === 0 && <p className="mt-2 text-body-sm text-secondary">No conditions.</p>}
        <ol className="mt-2 space-y-2">
          {conditions.fields.map((f, i) => (
            <li key={f.id} className="flex items-start gap-2">
              <TextInput className="flex-1" label={`Condition ${i + 1}`} hideLabel {...form.register(`conditions.${i}.text` as const)} error={errors.conditions?.[i]?.text?.message} />
              <IconButton label={`Remove condition ${i + 1}`} icon={<Trash2 className="h-icon-sm w-icon-sm" />} onClick={() => conditions.remove(i)} />
            </li>
          ))}
        </ol>
        <Button size="sm" variant="ghost" className="mt-2" leadingIcon={<Plus aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => conditions.append({ text: '' })} disabled={conditions.fields.length >= 30}>
          Add condition
        </Button>
      </fieldset>
      <div className="flex justify-end">
        <Button type="submit" leadingIcon={<Save aria-hidden="true" className="h-icon-sm w-icon-sm" />} loading={save.isPending} disabledReason={saveReason}>
          {latest ? `Save as v${latest.version_no + 1}` : 'Save memo'}
        </Button>
      </div>
    </form>
  );
}

function VersionHistory({ versions }: { versions: CreditMemo[] }) {
  if (versions.length === 0) return null;
  const sorted = [...versions].sort((a, b) => b.version_no - a.version_no);
  return (
    <div className="mt-5">
      <h3 className="mb-2 text-body font-semibold text-primary">Version history</h3>
      <ul className="space-y-2">
        {sorted.map((v) => (
          <li key={v.id}>
            <Collapsible
              summary={
                <span className="flex flex-wrap items-center gap-2">
                  <span>v{v.version_no}</span>
                  <Pill tone={v.recommendation === 'decline' ? 'danger' : v.recommendation === 'counter_offer' ? 'info' : 'success'}>{RECOMMENDATION_LABEL[v.recommendation]}</Pill>
                  <span className="font-normal text-secondary">
                    <StaffName id={v.authored_by} /> · <DateTimeText value={v.authored_at} />
                  </span>
                </span>
              }
            >
              <div className="space-y-3">
                <p className="text-body-sm text-secondary">
                  {v.recommended_amount ? <MoneyText amount={v.recommended_amount.amount} currency={v.recommended_amount.currency} /> : 'No amount'} ·{' '}
                  {v.recommended_tenor_months ? `${v.recommended_tenor_months} months` : 'no tenor'} · decision <span className="ref">{v.decision_id.slice(0, 8)}</span>
                </p>
                <p className="whitespace-pre-wrap text-body-sm text-primary">{v.narrative}</p>
                {v.conditions.length > 0 && (
                  <ol className="list-decimal pl-5 text-body-sm text-primary">
                    {v.conditions.map((c, i) => (
                      <li key={i}>{c}</li>
                    ))}
                  </ol>
                )}
                <Sections sections={v.sections} empty="No sections in this version." />
              </div>
            </Collapsible>
          </li>
        ))}
      </ul>
    </div>
  );
}
