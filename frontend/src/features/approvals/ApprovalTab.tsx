import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { CheckCircle2, CircleDashed, Gavel, KeyRound, Lock, ShieldCheck } from 'lucide-react';
import type { ApprovalRequest, ApprovalRequestWithPacket, ApprovalVote } from '@/api/approvals';
import { Banner, Button, CardHeader, DateTimeText, MoneyText, Pill, SectionError, Skeleton } from '@/components';
import { useCase } from '@/features/applications/case/CaseContext';
import { GradeBadge, OutcomeChip } from '@/features/applications/credit/CreditParts';
import { cn } from '@/lib/cn';
import { applicationApprovalsQuery, approvalRequestQuery } from './queries';
import { ADDED_BY_LABEL, APPROVE_PERM, basisParts, currentRequest, daysLeft, DECISION_META, levelState, REQUEST_STATUS_META } from './domain';
import { VoteDialog } from './VoteDialog';

/** SCR-APV-01/02: the approval chain, decision packet, votes with authority basis, and voting. */
export function ApprovalTab() {
  const { app, permissions } = useCase();
  const list = useQuery(applicationApprovalsQuery(app.id));

  if (list.isPending) return <Skeleton className="h-[20rem] w-full rounded-card" />;
  if (list.isError) return <SectionError error={list.error} title="Approvals couldn't load" onRetry={() => void list.refetch()} />;

  const current = currentRequest(list.data);
  if (!current) {
    return app.status === 'recommended' ? (
      <Banner tone="warning" title="Waiting for approval routing">
        The application is recommended but no approval request was opened. The product may not bind an active approval matrix, or no matrix row matched; ask a configuration administrator to check the matrix.
      </Banner>
    ) : (
      <Banner tone="info" title="Approval opens after the recommendation">
        When the analyst recommends the application, it is routed through the product’s approval matrix and the required authority levels appear here.
      </Banner>
    );
  }
  const earlier = list.data.filter((r) => r.id !== current.id);

  return (
    <div className="space-y-5">
      <RequestDetail summary={current} canApprove={permissions.has(APPROVE_PERM)} />
      {earlier.length > 0 && (
        <section aria-labelledby="apv-hist" className="rounded-card border bg-surface p-5 shadow-card">
          <CardHeader title="Earlier approval requests" titleId="apv-hist" level={2} />
          <ul className="space-y-2">
            {earlier.map((r) => (
              <li key={r.id} className="flex flex-wrap items-center justify-between gap-2 rounded-control border p-3 text-body-sm">
                <span>
                  Request #{r.sequence} · opened <DateTimeText value={r.opened_at} />
                  {r.votes.length > 0 && ` · ${r.votes.map((v) => `${v.level_code} ${DECISION_META[v.decision].verb}`).join(', ')}`}
                </span>
                <Pill tone={REQUEST_STATUS_META[r.status].tone}>{REQUEST_STATUS_META[r.status].label}</Pill>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}

function RequestDetail({ summary, canApprove }: { summary: ApprovalRequest; canApprove: boolean }) {
  const detail = useQuery(approvalRequestQuery(summary.id));
  const [voting, setVoting] = useState(false);
  const r: ApprovalRequest = detail.data ?? summary;
  const left = daysLeft(r.valid_until);

  return (
    <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <div className="min-w-0 space-y-5">
        <section aria-labelledby="apv-chain" className="rounded-card border bg-surface p-5 shadow-card">
          <CardHeader
            title="Approval chain"
            titleId="apv-chain"
            level={2}
            subtitle={`Routed by ${r.matrix.key} v${r.matrix.version_no}${r.matrix.row !== null ? `, row ${r.matrix.row + 1}` : ''}. Levels vote in order.`}
            actions={<Pill tone={REQUEST_STATUS_META[r.status].tone}>{REQUEST_STATUS_META[r.status].label}</Pill>}
          />
          <ol className="space-y-3">
            {r.levels.map((l) => {
              const vote = r.votes.find((v) => v.level_index === l.index);
              const state = levelState(l);
              const active = l.state === 'awaiting_vote';
              return (
                <li key={l.code} className={cn('rounded-card border p-4', active && 'border-accent bg-subtle')}>
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                      {vote ? <CheckCircle2 aria-hidden="true" className="h-icon w-icon text-success" /> : <CircleDashed aria-hidden="true" className="h-icon w-icon text-tertiary" />}
                      <h3 className="text-body font-semibold text-emphasis">
                        <span className="ref">{l.code}</span> · {l.name}
                      </h3>
                    </div>
                    <Pill tone={state.tone}>{state.label}</Pill>
                  </div>
                  <p className="mt-1 text-meta text-tertiary">
                    {ADDED_BY_LABEL[l.added_by]} · eligible roles: {l.roles.join(', ')}
                  </p>
                  {l.note && <p className="mt-1 text-body-sm text-secondary">{l.note}</p>}
                  {vote && <VoteRecord vote={vote} />}
                </li>
              );
            })}
          </ol>
        </section>

        {r.conditions.length > 0 && (
          <section aria-labelledby="apv-cond" className="rounded-card border bg-surface p-5 shadow-card">
            <CardHeader title="Conditions" titleId="apv-cond" level={2} subtitle="Attached by approvers. Conditions precedent block disbursement until met or waived." />
            <ul className="space-y-2">
              {r.conditions.map((c) => (
                <li key={c.id} className="flex flex-wrap items-center justify-between gap-2 rounded-control border p-3">
                  <span className="text-body-sm text-primary">{c.description}</span>
                  <span className="flex gap-2">
                    <Pill tone="info">{c.kind === 'precedent' ? 'CP' : 'CS'}</Pill>
                    <Pill tone={c.status === 'open' ? 'warning' : 'success'}>{c.status === 'open' ? 'Open' : c.status === 'satisfied' ? 'Satisfied' : 'Waived'}</Pill>
                  </span>
                </li>
              ))}
            </ul>
          </section>
        )}
      </div>

      <aside aria-label="Decision packet and your vote" className="min-w-0 space-y-5">
        <div className="space-y-5 xl:sticky xl:top-4">
          <section aria-labelledby="apv-me" className="rounded-card border bg-surface p-5 shadow-card">
            <CardHeader title="Your vote" titleId="apv-me" level={2} />
            {r.status !== 'pending' ? (
              <p className="text-body-sm text-secondary">
                {r.status === 'approved' && r.valid_until ? (
                  <>
                    Approved. Valid until <DateTimeText value={r.valid_until} />
                    {left !== null && (left >= 0 ? ` (${left} day${left === 1 ? '' : 's'} left)` : ' (lapsed)')}; after that it expires and needs re-approval.
                  </>
                ) : (
                  'Voting is closed on this request.'
                )}
              </p>
            ) : !canApprove ? (
              <p className="text-body-sm text-secondary">You can follow the approval here. Voting needs an approver role.</p>
            ) : detail.isPending ? (
              <Skeleton className="h-[4rem] w-full rounded-card" />
            ) : r.my_eligibility.can_vote ? (
              <div className="space-y-3">
                <p className="text-body-sm text-secondary">
                  You can vote at <span className="ref">{r.levels[r.current_level ?? 0]?.code}</span>
                  {r.my_eligibility.basis && typeof r.my_eligibility.basis.role_name === 'string' && <> as {r.my_eligibility.basis.role_name}</>}.
                </p>
                {r.my_eligibility.step_up_required && (
                  <p className="flex items-start gap-2 text-meta text-tertiary">
                    <KeyRound aria-hidden="true" className="mt-px h-icon-sm w-icon-sm shrink-0" />
                    Above the step-up threshold: you may be asked to re-enter your password and code.
                  </p>
                )}
                <Button className="w-full" leadingIcon={<Gavel aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={() => setVoting(true)}>
                  Vote
                </Button>
              </div>
            ) : (
              <div className="space-y-2">
                <p className="flex items-center gap-2 text-body-sm font-semibold text-primary">
                  <Lock aria-hidden="true" className="h-icon-sm w-icon-sm" /> You can’t vote on this request
                </p>
                <ul className="list-disc space-y-1 pl-5 text-body-sm text-secondary">
                  {r.my_eligibility.blockers.map((b) => (
                    <li key={b}>{b}</li>
                  ))}
                </ul>
              </div>
            )}
          </section>
          {detail.data && <Packet request={detail.data} />}
        </div>
      </aside>
      {voting && detail.data && <VoteDialog request={detail.data} onClose={() => setVoting(false)} />}
    </div>
  );
}

function VoteRecord({ vote }: { vote: ApprovalVote }) {
  const b = basisParts(vote.authority_basis);
  return (
    <div className="mt-3 space-y-2 border-t pt-3">
      <p className="text-body-sm text-primary">
        <span className="font-semibold">{vote.voter.name ?? 'An approver'}</span> {DECISION_META[vote.decision].verb} · <DateTimeText value={vote.voted_at} />
      </p>
      <blockquote className="rounded-control bg-subtle p-3 text-body-sm text-primary">{vote.rationale}</blockquote>
      {vote.reason_codes.length > 0 && (
        <p className="flex flex-wrap gap-1.5">
          {vote.reason_codes.map((c) => (
            <span key={c} className="ref rounded-pill border px-2 py-0.5 text-meta">
              {c}
            </span>
          ))}
        </p>
      )}
      {vote.counter_terms && (
        <p className="text-body-sm text-primary">
          Counter-offer: <MoneyText amount={vote.counter_terms.amount.amount} currency={vote.counter_terms.amount.currency} /> over {vote.counter_terms.tenor_months} months
        </p>
      )}
      <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-meta text-tertiary">
        <span className="inline-flex items-center gap-1">
          <ShieldCheck aria-hidden="true" className="h-icon-sm w-icon-sm" />
          Authority: {b.role} · {b.limitSource} <MoneyText amount={vote.authority_basis.effective_limit.amount} currency={vote.authority_basis.effective_limit.currency} />
        </span>
        {b.delegated && <span>Delegated authority</span>}
        {vote.stepped_up && <span>Step-up verified</span>}
      </p>
    </div>
  );
}

/** FR-APV-010: what the approver needs in one place. */
function Packet({ request }: { request: ApprovalRequestWithPacket }) {
  const { packet } = request;
  const credit = packet.credit;
  return (
    <section aria-labelledby="apv-packet" className="rounded-card border bg-surface p-5 shadow-card">
      <CardHeader title="Decision packet" titleId="apv-packet" level={2} />
      <dl className="space-y-3 text-body-sm">
        <div>
          <dt className="text-meta text-tertiary">Exposure</dt>
          <dd className="text-title text-emphasis">
            <MoneyText amount={request.exposure.amount} currency={request.exposure.currency} />
          </dd>
        </div>
        <div>
          <dt className="text-meta text-tertiary">Product</dt>
          <dd className="text-primary">
            {packet.application.product.name} v{packet.application.product.version_no} · {packet.application.tenor_months ?? '—'} months
          </dd>
        </div>
        {credit ? (
          <>
            <div>
              <dt className="text-meta text-tertiary">Credit decision</dt>
              <dd className="mt-1 flex flex-wrap items-center gap-2">
                <OutcomeChip outcome={credit.outcome} />
                <GradeBadge grade={credit.risk_grade} />
                {!credit.is_routed_decision && <Pill tone="warning">Newer than the routed decision</Pill>}
              </dd>
            </div>
            <div>
              <dt className="text-meta text-tertiary">Recommended terms</dt>
              <dd className="text-primary">
                <MoneyText amount={credit.recommended_terms.amount.amount} currency={credit.recommended_terms.amount.currency} /> · {credit.recommended_terms.tenor_months} months · {Number(credit.recommended_terms.rate_percent).toFixed(2)}% p.a.
              </dd>
            </div>
            <div>
              <dt className="text-meta text-tertiary">Exceptions</dt>
              <dd className="text-primary">{credit.exception_count === 0 ? 'None' : `${credit.exception_count} (highest severity ${credit.max_exception_severity ?? '—'})`}</dd>
            </div>
            <div>
              <dt className="text-meta text-tertiary">Memo recommendation</dt>
              <dd className="capitalize text-primary">{credit.memo_recommendation?.replace(/_/g, ' ') ?? '—'}</dd>
            </div>
          </>
        ) : (
          <p className="text-secondary">No credit decision is recorded.</p>
        )}
        <div>
          <dt className="text-meta text-tertiary">Bureau report</dt>
          <dd>{packet.bureau_valid ? <Pill tone="success">Current</Pill> : <Pill tone="danger">Lapsed: pull again before approving</Pill>}</dd>
        </div>
        <div>
          <dt className="text-meta text-tertiary">People</dt>
          <dd className="text-primary">
            Originated by {request.originator.name ?? 'staff'} · recommended by {request.recommended_by.name ?? 'staff'}
          </dd>
        </div>
      </dl>
      <p className="mt-3 text-meta text-tertiary">Full bureau, decision trace and memo are on the Credit tab.</p>
    </section>
  );
}
