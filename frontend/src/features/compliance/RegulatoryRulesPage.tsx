import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ExternalLink, Scale } from 'lucide-react';
import { orgApi } from '@/api/lending';
import { regulatoryApi } from '@/api/regulatory';
import { Banner, Card, CardHeader, DateTimeText, EmptyState, MoneyText, Pill, SectionError, Select, Skeleton } from '@/components';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { citationLine, orderRules, parameterSummary } from './regulatoryDomain';

/** FR-CMP-001/003: the jurisdiction pack and rule register in force for each booking entity. */
export function RegulatoryRulesPage() {
  return (
    <RequirePermission anyOf={['config:read']}>
      <RegulatoryRules />
    </RequirePermission>
  );
}

function RegulatoryRules() {
  const entities = useQuery({ queryKey: ['legal-entities'], queryFn: () => orgApi.legalEntities() });
  const [chosen, setChosen] = useState<string | null>(null);
  const legalEntityId = chosen ?? entities.data?.[0]?.id ?? null;
  const register = useQuery({ queryKey: ['regulatory-rules', legalEntityId], queryFn: () => regulatoryApi.register(legalEntityId ?? ''), enabled: legalEntityId !== null });

  if (entities.isPending) return <Skeleton className="h-[20rem] w-full rounded-card" />;
  if (entities.isError) return <SectionError error={entities.error} title="Legal entities couldn't load" onRetry={() => void entities.refetch()} />;
  if (entities.data.length === 0)
    return (
      <EmptyState icon={<Scale aria-hidden="true" className="h-8 w-8" />} title="No legal entities yet">
        Create a booking legal entity first.
      </EmptyState>
    );

  return (
    <div className="space-y-5">
      <Card>
        <CardHeader title="Regulatory rules" titleId="reg-h" level={2} subtitle="The jurisdiction pack in force for a booking entity, resolved from its jurisdiction and licence category" />
        <Select
          label="Booking legal entity"
          value={legalEntityId ?? ''}
          onChange={(e) => setChosen(e.target.value)}
          options={entities.data.map((le) => ({ value: le.id, label: `${le.name} (${le.jurisdiction} · ${le.licence_category.replace(/_/g, ' ')})` }))}
        />
      </Card>
      {register.isPending ? (
        <Skeleton className="h-[16rem] w-full rounded-card" />
      ) : register.isError ? (
        <SectionError error={register.error} title="The register couldn't load" onRetry={() => void register.refetch()} />
      ) : (
        <>
          {register.data.pack === null ? (
            <Banner tone="warning" title="No jurisdiction pack is active for this entity">
              Regulatory parameters fall back to installation defaults and the single-obligor limit is not checked. Author a <code>jurisdiction.pack</code> version (key{' '}
              <code>{`${register.data.legal_entity.jurisdiction.toLowerCase()}.${register.data.legal_entity.licence_category}`}</code>) from the draft pack, verify every rule against its primary source, and activate it
              through maker-checker.
            </Banner>
          ) : (
            <Card>
              <CardHeader
                title={register.data.pack.name}
                titleId="pack-h"
                level={2}
                subtitle={
                  <>
                    {register.data.pack.key} · version {register.data.pack.version_no}
                    {register.data.pack.activated_at && (
                      <>
                        {' '}
                        · live since <DateTimeText value={register.data.pack.activated_at} style="date" />
                      </>
                    )}
                  </>
                }
                actions={register.data.pack.unverified_rules > 0 ? <Pill tone="warning">{register.data.pack.unverified_rules} unverified rule(s)</Pill> : <Pill tone="success">All rules verified</Pill>}
              />
              <p className="text-body-sm text-secondary">
                Prudential base:{' '}
                {register.data.prudential ? (
                  <>
                    <MoneyText amount={register.data.prudential.shareholders_funds_unimpaired.amount} /> shareholders' funds unimpaired from <DateTimeText value={register.data.prudential.effective_from} style="date" /> (
                    {register.data.prudential.source})
                  </>
                ) : (
                  'not set: maintain prudential.parameters for this entity (LOS-FR-307).'
                )}
              </p>
            </Card>
          )}
          {register.data.rules.length > 0 && (
            <Card>
              <CardHeader title="Rule register" titleId="rules-h" level={2} subtitle="Every rule with its citation and effective dates (FR-CMP-003)" />
              <ul className="divide-y">
                {orderRules(register.data.rules).map((r) => {
                  const url = typeof r.citation.url === 'string' ? r.citation.url : null;
                  const summary = parameterSummary(r);
                  return (
                    <li key={`${r.code}-${r.effective_from}`} className="py-3">
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <p className="min-w-0 flex-1 text-body-sm font-semibold text-primary">{r.title}</p>
                        <span className="flex flex-wrap gap-1.5">
                          <Pill tone="neutral">{r.kind === 'parameter' ? 'Parameter' : 'Obligation'}</Pill>
                          {!r.in_force && <Pill tone="neutral">Not in force</Pill>}
                          <Pill tone={r.verification_status === 'verified' ? 'success' : 'warning'}>{r.verification_status === 'verified' ? 'Verified' : 'Unverified'}</Pill>
                        </span>
                      </div>
                      {summary && <p className="mt-1 text-body-sm text-secondary">{summary}</p>}
                      <p className="mt-1 text-meta text-tertiary">
                        {citationLine(r.citation)} · from <DateTimeText value={r.effective_from} style="date" />
                        {r.effective_to && (
                          <>
                            {' '}
                            to <DateTimeText value={r.effective_to} style="date" />
                          </>
                        )}
                        {r.verified_by && ` · verified by ${r.verified_by}${r.verified_on ? ` on ${r.verified_on}` : ''}`}
                        {url && (
                          <a className="ml-1 inline-flex items-center gap-0.5 text-link underline-offset-2 hover:underline" href={url} target="_blank" rel="noreferrer noopener">
                            source <ExternalLink aria-hidden="true" className="h-3 w-3" />
                          </a>
                        )}
                      </p>
                      {r.note && <p className="mt-1 text-meta text-tertiary">{r.note}</p>}
                    </li>
                  );
                })}
              </ul>
            </Card>
          )}
        </>
      )}
    </div>
  );
}
