import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { BadgeCheck, CircleSlash, History, ShieldQuestion } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { partiesApi, type ConsentBody, type IdentityType, type Party } from '@/api/lending';
import { isApiProblem } from '@/api/problem';
import { Button, CardHeader, DateTimeText, ErrorSummary, MaskedValue, Modal, Pill, SectionError, Select, Skeleton, TextInput, useToast } from '@/components';
import { partyKeys } from './DirectorsEditor';

const IDENTITY_LABEL: Record<string, string> = { bvn: 'BVN', nin: 'NIN', passport: 'Passport', drivers_licence: "Driver's licence", voters_card: "Voter's card" };
const VERIFIABLE: readonly string[] = ['bvn', 'nin', 'passport', 'drivers_licence', 'voters_card'];

export const CONSENT_PURPOSES: readonly { value: ConsentBody['purpose']; label: string }[] = [
  { value: 'data_processing', label: 'Data processing' },
  { value: 'credit_bureau', label: 'Credit bureau enquiry' },
  { value: 'credit_reporting', label: 'Credit reporting' },
  { value: 'marketing', label: 'Marketing' },
  { value: 'third_party_sharing', label: 'Third-party sharing' },
];
const CHANNELS: readonly { value: ConsentBody['channel']; label: string }[] = [
  { value: 'branch', label: 'Branch' },
  { value: 'digital', label: 'Digital' },
  { value: 'call_centre', label: 'Call centre' },
  { value: 'agent', label: 'Agent' },
  { value: 'api', label: 'API' },
];

export function purposeLabel(p: string): string {
  return CONSENT_PURPOSES.find((x) => x.value === p)?.label ?? p.replace(/_/g, ' ');
}

export function usePartyQuery(id: string) {
  return useQuery({ queryKey: partyKeys.detail(id), queryFn: () => partiesApi.get(id) });
}

function verificationPill(status: string) {
  if (status === 'verified') return <Pill tone="success">Verified</Pill>;
  if (status === 'failed') return <Pill tone="danger">Failed</Pill>;
  return <Pill tone="neutral">Not verified</Pill>;
}

/** Contact + identity numbers (masked as served) with Verify through the identity port. */
export function IdentitiesPanel({ party, canManage, headingLevel = 3 }: { party: Party; canManage: boolean; headingLevel?: 2 | 3 }) {
  const qc = useQueryClient();
  const toast = useToast();
  const verify = useMutation({
    mutationFn: (type: IdentityType) => partiesApi.verifyIdentity(party.id, type),
    onSuccess: async (p, type) => {
      qc.setQueryData(partyKeys.detail(party.id), p);
      const result = p.identities.find((i) => i.type === type)?.verification_status;
      toast.show(`${IDENTITY_LABEL[type] ?? type} ${result === 'verified' ? 'verified' : 'verification failed'}`, result === 'verified' ? 'success' : 'danger');
      await qc.invalidateQueries({ queryKey: ['applications', 'case'] });
    },
  });
  return (
    <section aria-labelledby={`id-${party.id}`} className="rounded-card border p-4">
      <CardHeader title="Identity and contact" titleId={`id-${party.id}`} level={headingLevel} className="mb-3" />
      {verify.error && <ErrorSummary title="Verification could not run" problem={isApiProblem(verify.error) ? verify.error : null} messages={isApiProblem(verify.error) ? undefined : [verify.error.message]} />}
      <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
        <div>
          <dt className="text-meta text-tertiary">Phone</dt>
          <dd className="text-body text-primary">
            <MaskedValue label="Phone" value={party.phone_masked} />
          </dd>
        </div>
        <div>
          <dt className="text-meta text-tertiary">Email</dt>
          <dd className="text-body text-primary">
            <MaskedValue label="Email" value={party.email_masked} />
          </dd>
        </div>
        {party.type === 'individual' ? (
          <div>
            <dt className="text-meta text-tertiary">Date of birth</dt>
            <dd className="text-body text-primary">
              <MaskedValue label="Date of birth" value={party.date_of_birth_masked ?? null} />
            </dd>
          </div>
        ) : (
          <div>
            <dt className="text-meta text-tertiary">TIN</dt>
            <dd className="text-body text-primary">
              <MaskedValue label="TIN" value={party.tin_masked ?? null} />
            </dd>
          </div>
        )}
      </dl>
      <h4 className="mt-4 text-body-sm font-semibold text-primary">Identity numbers</h4>
      {party.identities.length === 0 ? (
        <p className="mt-1 text-body-sm text-secondary">{party.type === 'individual' ? 'No BVN or NIN recorded. KYC needs a verified BVN or NIN.' : 'Companies are identified by their RC number.'}</p>
      ) : (
        <ul className="mt-2 divide-y divide-subtle">
          {party.identities.map((i) => {
            const label = IDENTITY_LABEL[i.type] ?? i.type;
            return (
              <li key={i.id} className="flex flex-wrap items-center justify-between gap-3 py-2">
                <div className="min-w-0">
                  <p className="text-body-sm font-semibold text-primary">{label}</p>
                  <MaskedValue label={label} value={i.value_masked} className="text-body-sm" />
                  {i.verified_at && (
                    <p className="text-meta text-tertiary">
                      {i.verification_status === 'verified' ? 'Verified' : 'Checked'} <DateTimeText value={i.verified_at} />
                      {i.provider ? ` · ${i.provider}` : ''}
                      {i.match_score ? ` · match ${i.match_score}` : ''}
                    </p>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  {verificationPill(i.verification_status)}
                  {VERIFIABLE.includes(i.type) && i.verification_status !== 'verified' && (
                    <Button
                      size="sm"
                      variant="secondary"
                      leadingIcon={<BadgeCheck aria-hidden="true" className="h-icon-sm w-icon-sm" />}
                      loading={verify.isPending && verify.variables === i.type}
                      disabledReason={canManage ? null : 'Requires the party:manage permission.'}
                      onClick={() => verify.mutate(i.type as IdentityType)}
                      aria-label={`Verify ${label}`}
                    >
                      Verify
                    </Button>
                  )}
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}

/** Current consent per purpose, grant/withdraw dialog and the full history. */
export function ConsentsPanel({ partyId, canManage, applicationId, headingLevel = 3 }: { partyId: string; canManage: boolean; applicationId?: string; headingLevel?: 2 | 3 }) {
  const consents = useQuery({ queryKey: partyKeys.consents(partyId), queryFn: () => partiesApi.consents(partyId) });
  const [dialog, setDialog] = useState<{ purpose: ConsentBody['purpose']; action: ConsentBody['action'] } | null>(null);
  const [showHistory, setShowHistory] = useState(false);

  return (
    <section aria-labelledby={`consent-${partyId}`} className="rounded-card border p-4">
      <CardHeader
        title="Consents"
        titleId={`consent-${partyId}`}
        level={headingLevel}
        className="mb-3"
        actions={
          consents.data && consents.data.history.length > 0 ? (
            <Button size="sm" variant="ghost" leadingIcon={<History aria-hidden="true" className="h-icon-sm w-icon-sm" />} aria-expanded={showHistory} onClick={() => setShowHistory((s) => !s)}>
              {showHistory ? 'Hide history' : 'History'}
            </Button>
          ) : undefined
        }
      />
      {consents.isPending && <Skeleton className="h-[5rem] w-full" />}
      {consents.isError && <SectionError error={consents.error} onRetry={() => void consents.refetch()} />}
      {consents.data && (
        <ul className="divide-y divide-subtle">
          {CONSENT_PURPOSES.map((p) => {
            const c = consents.data.current[p.value];
            const granted = c?.status === 'granted';
            return (
              <li key={p.value} className="flex flex-wrap items-center justify-between gap-2 py-2">
                <div>
                  <p className="text-body-sm font-semibold text-primary">{p.label}</p>
                  <p className="text-meta text-tertiary">
                    {c ? (
                      <>
                        {granted ? 'Given' : 'Withdrawn'} <DateTimeText value={c.since} style="date" /> · {c.channel.replace(/_/g, ' ')} · terms {c.terms_version}
                      </>
                    ) : (
                      'Not asked'
                    )}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  {c ? granted ? <Pill tone="success">Given</Pill> : <Pill tone="warning">Withdrawn</Pill> : <Pill tone="neutral">Not asked</Pill>}
                  <Button
                    size="sm"
                    variant="secondary"
                    leadingIcon={granted ? <CircleSlash aria-hidden="true" className="h-icon-sm w-icon-sm" /> : <ShieldQuestion aria-hidden="true" className="h-icon-sm w-icon-sm" />}
                    disabledReason={canManage ? null : 'Requires the party:manage permission.'}
                    onClick={() => setDialog({ purpose: p.value, action: granted ? 'withdraw' : 'grant' })}
                    aria-label={`${granted ? 'Withdraw' : 'Record'} consent: ${p.label}`}
                  >
                    {granted ? 'Withdraw' : 'Record'}
                  </Button>
                </div>
              </li>
            );
          })}
        </ul>
      )}
      {showHistory && consents.data && (
        <div className="mt-3 overflow-x-auto">
          <table className="w-full text-table">
            <caption className="sr-only">Consent history</caption>
            <thead>
              <tr className="text-left text-meta text-tertiary">
                <th scope="col" className="py-1 pr-3 font-medium">When</th>
                <th scope="col" className="py-1 pr-3 font-medium">Purpose</th>
                <th scope="col" className="py-1 pr-3 font-medium">Action</th>
                <th scope="col" className="py-1 pr-3 font-medium">Channel</th>
                <th scope="col" className="py-1 font-medium">Evidence</th>
              </tr>
            </thead>
            <tbody>
              {consents.data.history.map((h, i) => (
                <tr key={`${h.recorded_at}-${i}`} className="border-t border-subtle">
                  <td className="py-1.5 pr-3">
                    <DateTimeText value={h.recorded_at} />
                  </td>
                  <td className="py-1.5 pr-3">{purposeLabel(h.purpose)}</td>
                  <td className="py-1.5 pr-3">{h.action === 'grant' ? 'Given' : 'Withdrawn'}</td>
                  <td className="py-1.5 pr-3">{h.channel.replace(/_/g, ' ')}</td>
                  <td className="py-1.5">{h.evidence_ref ? <span className="ref">{h.evidence_ref}</span> : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {dialog && <ConsentDialog partyId={partyId} applicationId={applicationId} initial={dialog} onClose={() => setDialog(null)} />}
    </section>
  );
}

function ConsentDialog({ partyId, applicationId, initial, onClose }: { partyId: string; applicationId?: string | undefined; initial: { purpose: ConsentBody['purpose']; action: ConsentBody['action'] }; onClose: () => void }) {
  const qc = useQueryClient();
  const toast = useToast();
  const [key] = useState(idempotencyKey);
  const [purpose, setPurpose] = useState(initial.purpose);
  const [channel, setChannel] = useState<ConsentBody['channel']>('branch');
  const [terms, setTerms] = useState('T&C-2026.1');
  const [evidence, setEvidence] = useState('');
  const [touched, setTouched] = useState(false);
  const save = useMutation({
    mutationFn: () => partiesApi.recordConsent(partyId, { purpose, action: initial.action, channel, terms_version: terms.trim(), evidence_ref: evidence.trim() || null, application_id: applicationId ?? null }, key),
    onSuccess: async (data) => {
      qc.setQueryData(partyKeys.consents(partyId), data);
      toast.show(`Consent ${initial.action === 'grant' ? 'recorded' : 'withdrawn'}: ${purposeLabel(purpose)}`, 'success');
      await qc.invalidateQueries({ queryKey: ['applications', 'case'] });
      onClose();
    },
  });
  const termsError = touched && terms.trim() === '' ? 'Enter the terms version the customer agreed to.' : undefined;
  return (
    <Modal
      open
      onClose={onClose}
      dismissible={!save.isPending}
      title={initial.action === 'grant' ? 'Record consent' : 'Withdraw consent'}
      description={initial.action === 'withdraw' ? 'Withdrawing credit bureau consent blocks bureau enquiries for in-flight applications.' : 'Record the consent the customer gave, with the evidence reference.'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            Cancel
          </Button>
          <Button
            variant={initial.action === 'withdraw' ? 'danger' : 'primary'}
            loading={save.isPending}
            onClick={() => {
              setTouched(true);
              if (terms.trim()) save.mutate();
            }}
          >
            {initial.action === 'grant' ? 'Record consent' : 'Withdraw consent'}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {save.error && <ErrorSummary problem={isApiProblem(save.error) ? save.error : null} messages={isApiProblem(save.error) ? undefined : [save.error.message]} />}
        <Select label="Purpose" value={purpose} onChange={(e) => setPurpose(e.target.value as ConsentBody['purpose'])} options={CONSENT_PURPOSES} />
        <Select label="Channel" value={channel} onChange={(e) => setChannel(e.target.value as ConsentBody['channel'])} options={CHANNELS} />
        <TextInput label="Terms version" required value={terms} onChange={(e) => setTerms(e.target.value)} error={termsError} />
        <TextInput label="Evidence reference" value={evidence} onChange={(e) => setEvidence(e.target.value)} helper="Signed form number or e-consent id" />
      </div>
    </Modal>
  );
}
