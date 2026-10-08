import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { ArrowRight, Gavel, History, Play, RotateCcw } from 'lucide-react';
import { creditApi, type Decision, type ReplayResult, type Terms } from '@/api/credit';
import type { Application } from '@/api/lending';
import { Banner, Button, Card, CardHeader, DateTimeText, EmptyState, ErrorSummary, MoneyText, Pill, useToast } from '@/components';
import { isApiProblem } from '@/api/problem';
import { cn } from '@/lib/cn';
import { appKeys } from '../queries';
import { Collapsible, DsrMeter, GradeBadge, gradeText, KeyValueTable, OutcomeChip, StaffName } from './CreditParts';
import { CREDIT_PERM, creditWorkReason, dsrBar, firstReason, needPermission, num, outcomeMeta, problemLines, stageLabel, staleInputs } from './domain';

export function DecisionSection({ app, permissions, decisions, hasValidPrimaryReport }: { app: Application; permissions: ReadonlySet<string>; decisions: Decision[]; hasValidPrimaryReport: boolean }) {
  const qc = useQueryClient();
  const toast = useToast();
  const sorted = [...decisions].sort((a, b) => b.sequence - a.sequence);
  const [latest, ...earlier] = sorted;
  const stale = latest ? staleInputs(latest, app) : [];
  const runReason = firstReason(
    creditWorkReason(app.status),
    needPermission(permissions, CREDIT_PERM.analyse),
    hasValidPrimaryReport ? null : 'Pull a valid bureau report for the primary applicant first.',
  );
  const run = useMutation({
    mutationFn: () => creditApi.runDecision(app.id),
    onSuccess: async (d) => {
      toast.show(`Decision #${d.sequence}: ${outcomeMeta(d.outcome).label} · grade ${d.risk_grade}`, 'success');
      await Promise.all([
        qc.invalidateQueries({ queryKey: appKeys.decisions(app.id) }),
        qc.invalidateQueries({ queryKey: appKeys.memo(app.id) }),
        qc.invalidateQueries({ queryKey: appKeys.timeline(app.id) }),
      ]);
    },
  });

  return (
    <section aria-labelledby="decision-h" className="rounded-card border bg-surface p-5 shadow-card">
      <CardHeader
        title="Decision"
        titleId="decision-h"
        subtitle="Knock-out → policy → grade → affordability → pricing → outcome, using the product’s bound rule set. Every run is an immutable snapshot."
        actions={
          <Button size="sm" leadingIcon={<Play aria-hidden="true" className="h-icon-sm w-icon-sm" />} loading={run.isPending} disabledReason={runReason} onClick={() => run.mutate()}>
            {latest ? 'Run again' : 'Run decision'}
          </Button>
        }
      />
      {run.error && (
        <div className="mb-4">
          <ErrorSummary title="The decision did not run" problem={isApiProblem(run.error) ? run.error : null} messages={problemLines(run.error)} />
        </div>
      )}
      {latest && stale.length > 0 && (
        <div className="mb-4">
          <Banner tone="warning" title="Inputs changed after this decision">
            The application’s {stale.join(', ')} changed since decision #{latest.sequence} ran. Run the decision again so the memo and approval use current figures.
          </Banner>
        </div>
      )}
      {latest ? (
        <DecisionCard decision={latest} />
      ) : (
        <EmptyState icon={<Gavel aria-hidden="true" className="h-icon-lg w-icon-lg" />} title="No decision yet" headingLevel={3}>
          {hasValidPrimaryReport ? 'Run the decision to grade the application and check affordability.' : 'Pull the primary applicant’s bureau report, then run the decision.'}
        </EmptyState>
      )}
      {earlier.length > 0 && (
        <div className="mt-4">
          <Collapsible
            summary={
              <span className="inline-flex items-center gap-2">
                <History aria-hidden="true" className="h-icon-sm w-icon-sm text-indicator" />
                Earlier decisions ({earlier.length})
              </span>
            }
          >
            <ul className="space-y-2">
              {earlier.map((d) => (
                <li key={d.id}>
                  <Collapsible
                    summary={
                      <span className="flex flex-wrap items-center gap-2">
                        <span>Decision #{d.sequence}</span>
                        <OutcomeChip outcome={d.outcome} />
                        <span className="font-normal text-secondary">
                          grade {d.risk_grade} · <DateTimeText value={d.decided_at} /> · rule set v{d.rule_set.version_no}
                        </span>
                      </span>
                    }
                  >
                    <DecisionCard decision={d} compact />
                  </Collapsible>
                </li>
              ))}
            </ul>
          </Collapsible>
        </div>
      )}
    </section>
  );
}

export function DecisionCard({ decision: d, compact = false }: { decision: Decision; compact?: boolean }) {
  const meta = outcomeMeta(d.outcome);
  const bar = dsrBar(d.affordability);
  const a = d.affordability;
  return (
    <div className="space-y-4">
      {/* Hero: loan-ui dark card — outcome, grade, recommended amount. */}
      <Card tone="brand" className="relative overflow-hidden">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="min-w-0">
            <p className="text-meta text-on-brand-muted">
              Decision #{d.sequence} · <DateTimeText value={d.decided_at} /> · by <StaffName id={d.decided_by} />
            </p>
            <div className="mt-2 flex flex-wrap items-center gap-3">
              <OutcomeChip outcome={d.outcome} className="text-body-sm" />
              {!d.is_latest && <Pill tone="neutral">Superseded</Pill>}
            </div>
            <p className="mt-4 text-meta text-on-brand-muted">Recommended amount</p>
            <p className="text-hero text-on-brand">
              <MoneyText amount={d.recommended_terms.amount.amount} currency={d.recommended_terms.amount.currency} />
            </p>
            <p className="mt-1 text-body-sm text-on-brand-muted">
              {d.recommended_terms.tenor_months} months · {Number(d.recommended_terms.rate_percent).toFixed(2)}% p.a.
              {d.recommended_terms.monthly_instalment && (
                <>
                  {' '}
                  · <MoneyText amount={d.recommended_terms.monthly_instalment.amount} currency={d.recommended_terms.monthly_instalment.currency} /> / month
                </>
              )}
            </p>
          </div>
          <div className="flex flex-col items-end gap-1 text-right">
            <GradeBadge grade={d.risk_grade} size="lg" />
            <p className="text-meta text-on-brand-muted" aria-hidden="true">
              {gradeText(d.risk_grade)}
            </p>
          </div>
        </div>
        {meta.summary && <p className="mt-4 border-t border-decorative pt-3 text-body-sm text-on-brand">{meta.summary}</p>}
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        <section aria-label="Reason codes" className="rounded-card border p-4">
          <h4 className="text-body font-semibold text-primary">Reason codes</h4>
          <p className="text-meta text-tertiary">In evaluation order</p>
          {d.reason_codes.length === 0 ? (
            <p className="mt-2 text-body-sm text-secondary">No reason codes.</p>
          ) : (
            <ol className="mt-2 space-y-1.5">
              {d.reason_codes.map((r, i) => (
                <li key={`${r.code}-${i}`} className="flex items-center justify-between gap-2 rounded-control bg-subtle px-3 py-1.5">
                  <span className="flex min-w-0 items-center gap-2">
                    <span className="text-meta text-tertiary tabular">{i + 1}.</span>
                    <span className="ref font-semibold text-primary">{r.code}</span>
                  </span>
                  <Pill tone={r.stage === 'knockout' ? 'danger' : r.stage === 'policy' ? 'warning' : 'neutral'}>{stageLabel(r.stage)}</Pill>
                </li>
              ))}
            </ol>
          )}
        </section>

        <section aria-label="Requested and recommended terms" className="rounded-card border p-4">
          <h4 className="text-body font-semibold text-primary">Terms</h4>
          <p className="text-meta text-tertiary">Requested → recommended</p>
          <TermsTable requested={d.requested_terms} recommended={d.recommended_terms} />
        </section>
      </div>

      <section aria-label="Affordability" className="rounded-card border p-4">
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <h4 className="text-body font-semibold text-primary">Affordability</h4>
          {a.max_affordable_amount && (
            <p className="text-body-sm text-secondary">
              Max affordable: <MoneyText className="font-semibold text-primary" amount={a.max_affordable_amount.amount} currency={a.max_affordable_amount.currency} />
            </p>
          )}
        </div>
        <div className="mt-3">
          <DsrMeter bar={bar} />
        </div>
        <dl className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
          <MoneyFact label="Monthly income" value={a.monthly_income} />
          <MoneyFact label="Existing obligations" value={a.existing_obligations} />
          <MoneyFact label="New instalment" value={a.new_instalment} />
        </dl>
      </section>

      {!compact && <ProvenancePanel decision={d} />}
    </div>
  );
}

function MoneyFact({ label, value }: { label: string; value: { amount: string; currency: string } | null | undefined }) {
  return (
    <div className="rounded-control bg-subtle px-3 py-2">
      <dt className="text-meta text-tertiary">{label}</dt>
      <dd className="text-body font-semibold text-primary">{value ? <MoneyText amount={value.amount} currency={value.currency} /> : '—'}</dd>
    </div>
  );
}

function TermsTable({ requested: rq, recommended: rc }: { requested: Terms; recommended: Terms }) {
  const rows: { label: string; from: React.ReactNode; to: React.ReactNode; changed: boolean }[] = [
    {
      label: 'Amount',
      from: <MoneyText amount={rq.amount.amount} currency={rq.amount.currency} compact />,
      to: <MoneyText amount={rc.amount.amount} currency={rc.amount.currency} compact />,
      changed: num(rq.amount.amount) !== num(rc.amount.amount),
    },
    { label: 'Tenor', from: `${rq.tenor_months} months`, to: `${rc.tenor_months} months`, changed: rq.tenor_months !== rc.tenor_months },
    { label: 'Rate', from: `${Number(rq.rate_percent).toFixed(2)}%`, to: `${Number(rc.rate_percent).toFixed(2)}%`, changed: num(rq.rate_percent) !== num(rc.rate_percent) },
    {
      label: 'Monthly instalment',
      from: rq.monthly_instalment ? <MoneyText amount={rq.monthly_instalment.amount} currency={rq.monthly_instalment.currency} /> : '—',
      to: rc.monthly_instalment ? <MoneyText amount={rc.monthly_instalment.amount} currency={rc.monthly_instalment.currency} /> : '—',
      changed: num(rq.monthly_instalment?.amount) !== num(rc.monthly_instalment?.amount),
    },
  ];
  return (
    <table className="mt-2 w-full text-table">
      <caption className="sr-only">Requested versus recommended terms</caption>
      <thead>
        <tr className="text-left text-meta text-tertiary">
          <th scope="col" className="py-1.5 font-medium">Term</th>
          <th scope="col" className="py-1.5 text-right font-medium">Requested</th>
          <th scope="col" className="w-6 py-1.5"><span className="sr-only">to</span></th>
          <th scope="col" className="py-1.5 text-right font-medium">Recommended</th>
        </tr>
      </thead>
      <tbody>
        {rows.map((r) => (
          <tr key={r.label} className="border-t border-subtle">
            <th scope="row" className="py-2 text-left font-normal text-secondary">{r.label}</th>
            <td className="py-2 text-right">{r.from}</td>
            <td className="py-2 text-center"><ArrowRight aria-hidden="true" className="inline h-icon-sm w-icon-sm text-indicator" /></td>
            <td className={cn('py-2 text-right', r.changed ? 'font-bold text-emphasis' : 'text-primary')}>
              {r.to}
              {r.changed && <span className="sr-only"> (changed)</span>}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

/** Versions, replay, "How this was decided" trace and the raw facts. */
function ProvenancePanel({ decision: d }: { decision: Decision }) {
  const [result, setResult] = useState<ReplayResult | null>(null);
  const replay = useMutation({ mutationFn: () => creditApi.replay(d.id), onSuccess: setResult });
  return (
    <section aria-label="Decision provenance" className="space-y-3 rounded-card bg-muted p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <dl className="flex flex-wrap gap-x-6 gap-y-1 text-body-sm">
          <div>
            <dt className="text-meta text-tertiary">Rule set</dt>
            <dd className="ref font-semibold text-primary">
              {d.rule_set.key} v{d.rule_set.version_no}
            </dd>
          </div>
          <div>
            <dt className="text-meta text-tertiary">Evaluator</dt>
            <dd className="ref font-semibold text-primary">{d.evaluator_version}</dd>
          </div>
          <div>
            <dt className="text-meta text-tertiary">Bureau report</dt>
            <dd className="ref font-semibold text-primary">{d.bureau_report_id ? d.bureau_report_id.slice(0, 8) : '—'}</dd>
          </div>
        </dl>
        <Button size="sm" variant="secondary" leadingIcon={<RotateCcw aria-hidden="true" className="h-icon-sm w-icon-sm" />} loading={replay.isPending} onClick={() => replay.mutate()}>
          Replay decision
        </Button>
      </div>
      {result &&
        (result.identical ? (
          <Banner tone="success" title={`Replayed with evaluator ${result.evaluator_version} and rule set v${d.rule_set.version_no}: identical`} onDismiss={() => setResult(null)}>
            The recorded inputs, versions and outputs reproduce this decision exactly.
          </Banner>
        ) : (
          <Banner tone="danger" title={`Replayed with evaluator ${result.evaluator_version} and rule set v${d.rule_set.version_no}: ${result.differences.length} difference${result.differences.length === 1 ? '' : 's'}`} onDismiss={() => setResult(null)}>
            <ul className="mt-1 space-y-0.5">
              {result.differences.map((x) => (
                <li key={x.path}>
                  <span className="ref">{x.path}</span>: recorded {display(x.recorded)}, replayed {display(x.replayed)}
                </li>
              ))}
            </ul>
          </Banner>
        ))}
      {replay.error && <ErrorSummary title="Replay failed" problem={isApiProblem(replay.error) ? replay.error : null} messages={problemLines(replay.error)} />}
      <Collapsible summary={`How this was decided (${d.trace.length} step${d.trace.length === 1 ? '' : 's'})`} className="bg-surface">
        <TraceList trace={d.trace} />
      </Collapsible>
      <Collapsible summary="Facts used" className="bg-surface">
        <KeyValueTable value={d.facts} caption="Facts the rule set evaluated" empty="No facts recorded." />
      </Collapsible>
    </section>
  );
}

function display(v: unknown): string {
  if (v === undefined || v === null) return '—';
  return typeof v === 'string' ? v : JSON.stringify(v);
}

function stepTitle(entry: Record<string, unknown>, i: number): string {
  const stage = typeof entry.stage === 'string' ? entry.stage : null;
  const name = typeof entry.table === 'string' ? entry.table : typeof entry.name === 'string' ? entry.name : null;
  const label = stage ? stageLabel(stage) : (name ?? `Step ${i + 1}`);
  return name && stage && name !== stage ? `${label} · ${name}` : label;
}

function TraceList({ trace }: { trace: Record<string, unknown>[] }) {
  if (trace.length === 0) return <p className="text-body-sm text-secondary">No trace recorded.</p>;
  return (
    <ol className="space-y-2">
      {trace.map((entry, i) => (
        <li key={i}>
          <Collapsible summary={<span><span className="text-tertiary tabular">{i + 1}.</span> {stepTitle(entry, i)}</span>}>
            <TraceStep entry={entry} index={i} />
          </Collapsible>
        </li>
      ))}
    </ol>
  );
}

type RuleRow = { row?: unknown; when?: unknown; result?: unknown };

/** A decision-table step: each row's condition and whether it hit; other keys as a key/value table. */
function TraceStep({ entry, index }: { entry: Record<string, unknown>; index: number }) {
  const rows = Array.isArray(entry.rows) ? (entry.rows as RuleRow[]) : null;
  const rest = Object.fromEntries(Object.entries(entry).filter(([k]) => k !== 'rows' && k !== 'stage'));
  return (
    <div className="space-y-3">
      {rows && (
        <table className="w-full text-table">
          <caption className="sr-only">{`Rules evaluated in step ${index + 1}`}</caption>
          <thead>
            <tr className="text-left text-meta text-tertiary">
              <th scope="col" className="w-10 py-1 font-medium">Row</th>
              <th scope="col" className="py-1 font-medium">Condition</th>
              <th scope="col" className="w-20 py-1 text-right font-medium">Result</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r, j) => (
              <tr key={j} className="border-t border-subtle align-top">
                <td className="py-1.5 tabular text-secondary">{typeof r.row === 'number' ? r.row + 1 : j + 1}</td>
                <th scope="row" className="py-1.5 text-left font-normal">
                  <span className="ref break-all">{typeof r.when === 'string' ? r.when : JSON.stringify(r.when)}</span>
                </th>
                <td className="py-1.5 text-right">{r.result === true ? <Pill tone="warning">Hit</Pill> : <span className="text-secondary">No</span>}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {Object.keys(rest).length > 0 && <KeyValueTable value={rest} caption={`Trace step ${index + 1} details`} />}
    </div>
  );
}
