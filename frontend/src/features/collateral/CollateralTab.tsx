import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Download, Landmark, Plus, ShieldCheck } from 'lucide-react';
import { collateralApi, type PerfectionStep, type SecuredCollateral } from '@/api/collateral';
import { documentsApi } from '@/api/lending';
import { Banner, Button, CardHeader, DateTimeText, Drawer, KeyValueGrid, MoneyText, Pill, SectionError, Skeleton, useToast } from '@/components';
import { useCase } from '@/features/applications/case/CaseContext';
import { appKeys, refreshCase } from '@/features/applications/queries';
import { meQuery } from '@/features/auth/session';
import { saveBlob } from '@/lib/download';
import { AddCollateralDialog, AllocateDialog, CollateralWaiverDialog, CompleteStepDialog, DecideCollateralWaiverDialog, InsuranceDialog, ValuationDialog, type WaiverTarget } from './CollateralDialogs';
import { COLLATERAL_PERM, INSURANCE_META, isOpenStep, manageReason, perfectionProgress, STEP_META, stepGroups, thresholdTone, VALUATION_META } from './domain';

const securityKey = (id: string) => [...appKeys.case(id), 'collateral'] as const;

type Dialog =
  | { kind: 'add' }
  | { kind: 'valuation' | 'insurance' | 'allocate' }
  | { kind: 'complete'; step: PerfectionStep }
  | { kind: 'waive'; target: WaiverTarget }
  | {
      kind: 'decide';
      changeRequestId: string;
      label: string;
      reason: string | null;
    }
  | null;

/** SCR-APP-07: security & cover, the collateral register, and each item's valuation, perfection, insurance and allocation. */
export function CollateralTab() {
  const { app, permissions } = useCase();
  const q = useQuery({
    queryKey: securityKey(app.id),
    queryFn: () => collateralApi.forApplication(app.id),
  });
  const [openId, setOpenId] = useState<string | null>(null);
  const [dialog, setDialog] = useState<Dialog>(null);

  if (q.isPending) return <Skeleton className="h-[20rem] w-full rounded-card" />;
  if (q.isError) return <SectionError error={q.error} title="Collateral couldn't load" onRetry={() => void q.refetch()} />;
  const f = q.data;
  const reason = manageReason(app.status, permissions);
  const open = f.items.find((i) => i.id === openId) ?? null;

  return (
    <div className="space-y-5">
      <section aria-labelledby="cover-h" className="rounded-card border bg-surface p-5 shadow-card">
        <CardHeader
          title="Security & cover"
          titleId="cover-h"
          level={2}
          subtitle={
            <>
              Against exposure of <MoneyText amount={f.exposure.amount} />
            </>
          }
          actions={
            <Button size="sm" leadingIcon={<Plus aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={reason} onClick={() => setDialog({ kind: 'add' })}>
              Add collateral
            </Button>
          }
        />
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <Metric
            label="Security cover"
            value={f.cover?.cover_percent ? `${f.cover.cover_percent}%` : '—'}
            target={f.policy.min_cover_percent ? `min ${Number(f.policy.min_cover_percent)}%` : 'no minimum'}
            meets={f.cover?.meets_cover ?? (f.policy.min_cover_percent ? false : null)}
          />
          <Metric
            label="Loan-to-value"
            value={f.cover?.ltv_percent ? `${f.cover.ltv_percent}%` : '—'}
            target={f.policy.max_ltv_percent ? `max ${Number(f.policy.max_ltv_percent)}%` : 'no maximum'}
            meets={f.cover?.meets_ltv ?? null}
          />
          <Metric
            label="Lending value"
            value={<MoneyText amount={f.cover?.lending_value.amount ?? 0} compact />}
            target={
              f.cover ? (
                <>
                  market <MoneyText amount={f.cover.market_value.amount} compact />
                </>
              ) : (
                'nothing allocated'
              )
            }
            meets={null}
          />
          <Metric
            label="Perfection & insurance"
            value={!f.security.applicable ? 'Not required' : f.security.blockers.length === 0 ? 'Clear' : `${f.security.blockers.length} outstanding`}
            target={f.security.open_subsequent_steps > 0 ? `${f.security.open_subsequent_steps} step(s) after booking` : 'before disbursement'}
            meets={f.security.applicable ? f.security.blockers.length === 0 : null}
          />
        </div>
        {f.security.blockers.length > 0 && (
          <Banner className="mt-4" tone="warning" title="Disbursement is blocked by security">
            <ul className="list-disc space-y-0.5 pl-5">
              {f.security.blockers.map((b) => (
                <li key={b}>{b}</li>
              ))}
            </ul>
          </Banner>
        )}
      </section>

      <section aria-labelledby="items-h" className="rounded-card border bg-surface p-5 shadow-card">
        <CardHeader title="Collateral" titleId="items-h" level={2} subtitle={f.policy.required ? 'This product requires security' : 'Optional for this product'} />
        {f.items.length === 0 ? (
          <p className="rounded-control bg-subtle p-3 text-body-sm text-secondary">{f.policy.required ? 'No collateral yet. Add the security the approval relies on.' : 'Collateral is not required for this product.'}</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[48rem] text-body-sm">
              <thead className="text-left text-meta text-tertiary">
                <tr>
                  <th className="py-2 pr-3 font-medium">Item</th>
                  <th className="py-2 pr-3 text-right font-medium">Market value</th>
                  <th className="py-2 pr-3 text-right font-medium">Haircut</th>
                  <th className="py-2 pr-3 text-right font-medium">Forced-sale</th>
                  <th className="py-2 pr-3 text-right font-medium">Allocated here</th>
                  <th className="py-2 pr-3 font-medium">Perfection</th>
                  <th className="py-2 font-medium">Insurance</th>
                </tr>
              </thead>
              <tbody>
                {f.items.map((c) => {
                  const p = perfectionProgress(c.perfection.steps);
                  return (
                    <tr key={c.id} className="border-t">
                      <td className="py-2.5 pr-3">
                        <button type="button" className="text-left font-semibold text-primary underline-offset-2 hover:underline" onClick={() => setOpenId(c.id)}>
                          {c.description}
                        </button>
                        <p className="text-meta text-tertiary">
                          {c.type.name} · {c.owner.name}
                        </p>
                      </td>
                      <td className="py-2.5 pr-3 text-right">
                        <MoneyText amount={c.market_value.amount} />
                      </td>
                      <td className="py-2.5 pr-3 text-right tabular">{Number(c.haircut_percent)}%</td>
                      <td className="py-2.5 pr-3 text-right">
                        <MoneyText amount={c.forced_sale_value.amount} />
                      </td>
                      <td className="py-2.5 pr-3 text-right">
                        <MoneyText amount={c.allocated_here.amount} />
                        {c.allocation.over_allocated && (
                          <Pill className="ml-1.5" tone="danger">
                            Over-allocated
                          </Pill>
                        )}
                      </td>
                      <td className="py-2.5 pr-3">
                        <Pill tone={c.perfection.precedent_complete ? 'success' : 'warning'}>
                          {p.done}/{p.total} done
                        </Pill>
                      </td>
                      <td className="py-2.5">
                        <Pill tone={INSURANCE_META[c.insurance.status].tone}>{INSURANCE_META[c.insurance.status].label}</Pill>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {open && <ItemDrawer item={open} applicationId={app.id} reason={reason} permissions={permissions} onClose={() => setOpenId(null)} onDialog={setDialog} />}
      {dialog?.kind === 'add' && <AddCollateralDialog applicationId={app.id} applicant={app.primary_applicant} onClose={() => setDialog(null)} />}
      {open && dialog?.kind === 'valuation' && <ValuationDialog applicationId={app.id} collateral={open} onClose={() => setDialog(null)} />}
      {open && dialog?.kind === 'insurance' && <InsuranceDialog applicationId={app.id} collateral={open} onClose={() => setDialog(null)} />}
      {open && dialog?.kind === 'allocate' && <AllocateDialog applicationId={app.id} collateral={open} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'complete' && <CompleteStepDialog applicationId={app.id} step={dialog.step} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'waive' && <CollateralWaiverDialog applicationId={app.id} target={dialog.target} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'decide' && <DecideCollateralWaiverDialog applicationId={app.id} changeRequestId={dialog.changeRequestId} label={dialog.label} requestedBecause={dialog.reason} onClose={() => setDialog(null)} />}
    </div>
  );
}

function Metric({ label, value, target, meets }: { label: string; value: React.ReactNode; target: React.ReactNode; meets: boolean | null }) {
  const tone = thresholdTone(meets);
  return (
    <div className="rounded-control border p-3">
      <div className="flex items-center justify-between gap-2">
        <p className="text-meta text-tertiary">{label}</p>
        {tone !== 'neutral' && <Pill tone={tone}>{meets ? 'Pass' : 'Fail'}</Pill>}
      </div>
      <p className="mt-1 text-heading-sm font-semibold text-primary tabular">{value}</p>
      <p className="text-meta text-tertiary">{target}</p>
    </div>
  );
}

function ItemDrawer({
  item: c,
  applicationId,
  reason,
  permissions,
  onClose,
  onDialog,
}: {
  item: SecuredCollateral;
  applicationId: string;
  reason: string | null;
  permissions: ReadonlySet<string>;
  onClose: () => void;
  onDialog: (d: Dialog) => void;
}) {
  const qc = useQueryClient();
  const toast = useToast();
  const me = useQuery(meQuery);
  const groups = stepGroups(c.perfection.steps);
  const download = useMutation({
    mutationFn: async (d: { document_id: string; version_id: string }) => saveBlob(await documentsApi.content(d.document_id, d.version_id), 'collateral-document'),
  });
  const assign = useMutation({
    mutationFn: (s: PerfectionStep) =>
      collateralApi.updateStep(s.id, {
        responsible_user_id: me.data?.id ?? null,
        start: true,
      }),
    onSuccess: async () => {
      toast.show('Step assigned to you and started.', 'success');
      await refreshCase(qc, applicationId);
    },
  });
  const latest = c.valuation.history[0];
  const report = latest?.report ?? null;
  const insuranceWaiver = c.insurance.waiver;
  const policy = c.insurance.policies.find((p) => p.id === c.insurance.current_policy_id);
  const canDecide = permissions.has(COLLATERAL_PERM.waiveApprove);

  const steps = (list: PerfectionStep[], empty: string) =>
    list.length === 0 ? (
      <p className="text-meta text-tertiary">{empty}</p>
    ) : (
      <ul className="space-y-2">
        {list.map((s) => {
          const w = s.waiver;
          const doc =
            s.evidence?.document_id && s.evidence.version_id
              ? {
                  document_id: s.evidence.document_id,
                  version_id: s.evidence.version_id,
                }
              : null;
          return (
            <li key={s.id} className="rounded-control border p-3">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <p className="min-w-0 flex-1 text-body-sm font-medium text-primary">{s.name}</p>
                <span className="flex gap-1.5">
                  {s.is_overdue && <Pill tone="danger">Overdue</Pill>}
                  <Pill tone={STEP_META[s.status].tone}>{STEP_META[s.status].label}</Pill>
                </span>
              </div>
              <p className="mt-1 text-meta text-tertiary">
                {s.responsible ? `Responsible: ${s.responsible.name ?? 'staff'}` : 'Unassigned'}
                {s.due_date && (
                  <>
                    {' '}
                    · due <DateTimeText value={s.due_date} style="date" />
                  </>
                )}
                {s.completion && (
                  <>
                    {' '}
                    · {s.status === 'waived' ? 'waived' : 'completed'} by {s.completion.by?.name ?? 'staff'} <DateTimeText value={s.completion.at} style="date" />
                  </>
                )}
                {s.evidence?.reference && ` · ref ${s.evidence.reference}`}
              </p>
              {s.status === 'waiver_pending' && s.waiver?.reason && <p className="mt-1 text-meta text-tertiary">Waiver requested: {s.waiver.reason}</p>}
              <div className="mt-2 flex flex-wrap gap-2">
                {isOpenStep(s) && (
                  <>
                    <Button size="sm" disabledReason={reason} onClick={() => onDialog({ kind: 'complete', step: s })}>
                      Complete
                    </Button>
                    {s.status === 'pending' && (
                      <Button size="sm" variant="ghost" disabledReason={reason} loading={assign.isPending} onClick={() => assign.mutate(s)}>
                        Assign to me
                      </Button>
                    )}
                    {s.timing === 'precedent' && (
                      <Button
                        size="sm"
                        variant="secondary"
                        disabledReason={reason}
                        onClick={() =>
                          onDialog({
                            kind: 'waive',
                            target: {
                              kind: 'perfection_step',
                              id: s.id,
                              label: `${c.description}: ${s.name}`,
                            },
                          })
                        }
                      >
                        {s.status === 'waiver_pending' ? 'Request again' : 'Request waiver'}
                      </Button>
                    )}
                  </>
                )}
                {s.status === 'waiver_pending' && w && canDecide && (
                  <Button
                    size="sm"
                    variant="accent"
                    leadingIcon={<ShieldCheck aria-hidden="true" className="h-icon-sm w-icon-sm" />}
                    onClick={() =>
                      onDialog({
                        kind: 'decide',
                        changeRequestId: w.change_request_id,
                        label: `${c.description}: ${s.name}`,
                        reason: w.reason,
                      })
                    }
                  >
                    Decide waiver
                  </Button>
                )}
                {doc && (
                  <Button size="sm" variant="ghost" leadingIcon={<Download aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => download.mutate(doc)}>
                    Evidence
                  </Button>
                )}
              </div>
            </li>
          );
        })}
      </ul>
    );

  return (
    <Drawer open onClose={onClose} title={c.description} subtitle={`${c.type.name} · ${c.owner.name}${c.state_code ? ` · ${c.state_code}` : ''}`}>
      <div className="space-y-6">
        <section aria-labelledby="val-h">
          <div className="flex items-center justify-between gap-2">
            <h3 id="val-h" className="text-body font-semibold text-primary">
              Valuation
            </h3>
            <Pill tone={VALUATION_META[c.valuation.status].tone}>{VALUATION_META[c.valuation.status].label}</Pill>
          </div>
          <KeyValueGrid
            className="mt-2"
            items={[
              {
                label: 'Market value',
                value: <MoneyText amount={c.market_value.amount} />,
                hint: c.value_basis === 'declared' ? 'declared' : undefined,
              },
              {
                label: 'Forced-sale value',
                value: <MoneyText amount={c.forced_sale_value.amount} />,
                hint: `${Number(c.haircut_percent)}% haircut`,
              },
              {
                label: 'Valuer',
                value: latest ? `${latest.valuer} (${latest.method.replace(/_/g, ' ')})` : '—',
              },
              {
                label: 'Valid until',
                value: c.valuation.valid_until ? <DateTimeText value={c.valuation.valid_until} style="date" /> : '—',
              },
            ]}
          />
          <div className="mt-2 flex flex-wrap gap-2">
            <Button size="sm" variant="secondary" disabledReason={reason} onClick={() => onDialog({ kind: 'valuation' })}>
              Record valuation
            </Button>
            {report && (
              <Button size="sm" variant="ghost" leadingIcon={<Download aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => download.mutate(report)}>
                Report
              </Button>
            )}
          </div>
        </section>

        <section aria-labelledby="perf-h">
          <h3 id="perf-h" className="text-body font-semibold text-primary">
            Perfection
          </h3>
          <p className="mb-2 text-meta text-tertiary">Before disbursement</p>
          {steps(groups.precedent, 'No steps before disbursement.')}
          <p className="mb-2 mt-4 text-meta text-tertiary">After booking (tracked by servicing)</p>
          {steps(groups.subsequent, 'No steps after booking.')}
        </section>

        <section aria-labelledby="ins-h">
          <div className="flex items-center justify-between gap-2">
            <h3 id="ins-h" className="text-body font-semibold text-primary">
              Insurance
            </h3>
            <Pill tone={INSURANCE_META[c.insurance.status].tone}>{INSURANCE_META[c.insurance.status].label}</Pill>
          </div>
          {policy ? (
            <KeyValueGrid
              className="mt-2"
              items={[
                {
                  label: 'Policy',
                  value: `${policy.policy_no} · ${policy.insurer}`,
                },
                {
                  label: 'Sum insured',
                  value: <MoneyText amount={policy.sum_insured.amount} />,
                  hint: policy.under_insured ? 'below market value' : undefined,
                },
                {
                  label: 'Expires',
                  value: <DateTimeText value={policy.expiry_date} style="date" />,
                },
                {
                  label: "Bank's interest",
                  value: policy.bank_interest_noted ? 'Noted' : 'Not noted',
                },
              ]}
            />
          ) : (
            <p className="mt-2 text-meta text-tertiary">{c.insurance.requirement === 'not_required' ? 'This type does not need insurance.' : 'No policy recorded.'}</p>
          )}
          {c.insurance.requirement === 'waiver_pending' && c.insurance.waiver?.reason && <p className="mt-1 text-meta text-tertiary">Waiver requested: {c.insurance.waiver.reason}</p>}
          <div className="mt-2 flex flex-wrap gap-2">
            {c.insurance.requirement !== 'not_required' && (
              <Button size="sm" variant="secondary" disabledReason={reason} onClick={() => onDialog({ kind: 'insurance' })}>
                Record policy
              </Button>
            )}
            {(c.insurance.requirement === 'required' || c.insurance.requirement === 'waiver_pending') && c.insurance.status !== 'in_force' && c.insurance.status !== 'expiring' && (
              <Button
                size="sm"
                variant="ghost"
                disabledReason={reason}
                onClick={() =>
                  onDialog({
                    kind: 'waive',
                    target: {
                      kind: 'insurance',
                      id: c.id,
                      label: `Insurance on ${c.description}`,
                    },
                  })
                }
              >
                Request waiver
              </Button>
            )}
            {c.insurance.requirement === 'waiver_pending' && insuranceWaiver && canDecide && (
              <Button
                size="sm"
                variant="accent"
                leadingIcon={<ShieldCheck aria-hidden="true" className="h-icon-sm w-icon-sm" />}
                onClick={() =>
                  onDialog({
                    kind: 'decide',
                    changeRequestId: insuranceWaiver.change_request_id,
                    label: `Insurance on ${c.description}`,
                    reason: insuranceWaiver.reason,
                  })
                }
              >
                Decide waiver
              </Button>
            )}
          </div>
        </section>

        <section aria-labelledby="alloc-h">
          <h3 id="alloc-h" className="text-body font-semibold text-primary">
            Allocation
          </h3>
          <ul className="mt-2 space-y-1 text-body-sm">
            {c.allocation.items
              .filter((a) => a.status === 'active')
              .map((a) => (
                <li key={a.id} className="flex justify-between gap-2">
                  <span className="flex items-center gap-1.5 text-secondary">
                    <Landmark aria-hidden="true" className="h-icon-sm w-icon-sm" />
                    {a.application_reference ?? a.application_id}
                    {a.application_id === applicationId && ' (this facility)'}
                  </span>
                  <MoneyText amount={a.amount.amount} />
                </li>
              ))}
          </ul>
          <p className="mt-1 text-meta text-tertiary">
            Available <MoneyText amount={c.allocation.available.amount} />
            {c.allocation.over_allocated && ' · allocations exceed the forced-sale value after re-valuation; reduce them'}
          </p>
          <Button className="mt-2" size="sm" variant="secondary" disabledReason={reason} onClick={() => onDialog({ kind: 'allocate' })}>
            Change allocation
          </Button>
        </section>
      </div>
    </Drawer>
  );
}
