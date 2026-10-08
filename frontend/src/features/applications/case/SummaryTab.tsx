import { useState } from 'react';
import { Link } from 'react-router';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
import { Pencil, UserMinus, UserPlus } from 'lucide-react';
import { applicationsApi, productsApi, type Applicant, type ApplicantRole } from '@/api/lending';
import { isApiProblem } from '@/api/problem';
import {
  Banner,
  Button,
  CardHeader,
  ConfirmDialog,
  DateTimeText,
  ErrorSummary,
  KeyValueGrid,
  Modal,
  MoneyText,
  Pill,
  ProgressBar,
  Select,
  TextArea,
  TextInput,
  useToast,
} from '@/components';
import { PartyCreateForm } from '@/features/parties/components/PartyCreateForm';
import { PartySearch } from '@/features/parties/components/PartySearch';
import { canAmend, PERM } from '../domain/actions';
import { isStaleProblem, useIfMatchMutation } from '../queries';
import { REPAYMENT_FREQUENCIES, termsSchema, type TermsInput } from '../wizard/schemas';
import { useCase, type CaseContextValue } from './CaseContext';

/** "2000000.5000" → "2000000.5", "2000000.0000" → "2000000". */
export function trimDecimal(v: string): string {
  return v.includes('.') ? v.replace(/0+$/, '').replace(/\.$/, '') : v;
}

const ROLE_LABEL: Record<string, string> = { primary: 'Primary', joint: 'Joint applicant', guarantor: 'Guarantor', co_signer: 'Co-signer' };

export function SummaryTab() {
  const ctx = useCase();
  const { app, permissions } = ctx;
  const [editing, setEditing] = useState(false);
  const canOriginate = permissions.has(PERM.originate);
  const amendable = canAmend(app.status);
  const editReason = !canOriginate ? 'Requires the application:originate permission.' : !amendable ? 'Captured fields can only be amended before approval.' : null;

  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
      <div className="min-w-0 space-y-6">
        <section aria-labelledby="terms-h">
          <CardHeader
            title="Requested terms"
            titleId="terms-h"
            actions={
              !editing ? (
                <Button size="sm" variant="secondary" leadingIcon={<Pencil aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={editReason} onClick={() => setEditing(true)}>
                  Edit terms
                </Button>
              ) : undefined
            }
          />
          {editing ? (
            <TermsEditor ctx={ctx} onDone={() => setEditing(false)} />
          ) : (
            <KeyValueGrid
              columns={3}
              items={[
                { label: 'Amount', value: app.requested_amount ? <MoneyText amount={app.requested_amount.amount} currency={app.requested_amount.currency} /> : 'Not captured' },
                { label: 'Tenor', value: app.tenor_months ? `${app.tenor_months} months` : 'Not captured' },
                { label: 'Repayment', value: <span className="capitalize">{app.repayment_frequency ?? 'Not captured'}</span> },
                { label: 'Purpose', value: app.purpose ?? 'Not captured' },
                { label: 'Product', value: app.product.name, hint: 'Pinned to the version chosen at capture' },
                { label: 'Channel', value: <span className="capitalize">{app.channel}</span> },
              ]}
            />
          )}
        </section>

        <ApplicantsSection ctx={ctx} />
      </div>

      <div className="min-w-0 space-y-6">
        <section aria-labelledby="complete-h" className="rounded-card bg-muted p-5">
          <h3 id="complete-h" className="text-title text-emphasis">
            Case completeness
          </h3>
          <div className="mt-3 flex items-baseline justify-between">
            <span className="text-metric text-emphasis tabular">{app.completeness.percent}%</span>
            <span className="text-body-sm text-tertiary">{app.completeness.missing.length === 0 ? 'Nothing missing' : `${app.completeness.missing.length} item(s) missing`}</span>
          </div>
          <ProgressBar className="mt-3" value={app.completeness.percent / 100} label="Case completeness" />
          {app.completeness.missing.length > 0 && (
            <ul className="mt-3 list-disc space-y-1 pl-5 text-body-sm text-primary">
              {app.completeness.missing.map((m) => (
                <li key={m}>{m.replace(/_/g, ' ')}</li>
              ))}
            </ul>
          )}
          {app.completeness.checklist.length > 0 && (
            <>
              <h4 className="mt-4 text-body-sm font-semibold text-primary">Documents this product asks for</h4>
              <ul className="mt-1 space-y-1 text-body-sm">
                {app.completeness.checklist.map((c) => (
                  <li key={c.code} className="flex justify-between gap-2">
                    <span>{c.name}</span>
                    <span className="text-tertiary">{c.mandatory ? 'Mandatory' : 'Optional'}</span>
                  </li>
                ))}
              </ul>
              <Link to="documents" className="mt-3 inline-flex min-h-target items-center text-body-sm font-semibold text-link hover:underline">
                Open the document checklist
              </Link>
            </>
          )}
        </section>
        <section aria-labelledby="dates-h" className="rounded-card border p-5">
          <h3 id="dates-h" className="text-title text-emphasis">
            Key dates
          </h3>
          <KeyValueGrid
            className="mt-3"
            items={[
              { label: 'Created', value: <DateTimeText value={app.created_at} /> },
              { label: 'Submitted', value: app.submitted_at ? <DateTimeText value={app.submitted_at} /> : 'Not yet' },
              { label: 'Stage since', value: <DateTimeText value={app.status_changed_at} /> },
              { label: app.closed_at ? 'Closed' : 'Draft expires', value: app.closed_at ? <DateTimeText value={app.closed_at} /> : app.expires_at ? <DateTimeText value={app.expires_at} style="date" /> : '—' },
            ]}
          />
        </section>
      </div>
    </div>
  );
}

function TermsEditor({ ctx, onDone }: { ctx: CaseContextValue; onDone: () => void }) {
  const { app } = ctx;
  const toast = useToast();
  const product = useQuery({ queryKey: ['products', app.product.key], queryFn: () => productsApi.get(app.product.key), staleTime: 5 * 60_000 });
  if (product.isPending) return <p className="text-body-sm text-secondary">Loading product limits…</p>;
  if (product.isError) return <ErrorSummary problem={isApiProblem(product.error) ? product.error : null} messages={isApiProblem(product.error) ? undefined : [product.error.message]} />;
  return <TermsForm ctx={ctx} product={product.data} onDone={onDone} onSaved={() => toast.show('Terms updated', 'success')} />;
}

function TermsForm({ ctx, product, onDone, onSaved }: { ctx: CaseContextValue; product: Parameters<typeof termsSchema>[0]; onDone: () => void; onSaved: () => void }) {
  const { app } = ctx;
  const schema = termsSchema(product).omit({ legal_entity_id: true, org_unit_id: true });
  const form = useForm<Omit<TermsInput, 'legal_entity_id' | 'org_unit_id'>>({
    resolver: zodResolver(schema),
    defaultValues: {
      requested_amount: app.requested_amount ? trimDecimal(app.requested_amount.amount) : '',
      tenor_months: app.tenor_months ?? product.tenor_months.min,
      purpose: app.purpose ?? '',
      repayment_frequency: (REPAYMENT_FREQUENCIES as readonly string[]).includes(app.repayment_frequency ?? '') ? (app.repayment_frequency as (typeof REPAYMENT_FREQUENCIES)[number]) : 'monthly',
    },
  });
  const save = useIfMatchMutation<{ requested_amount: string; tenor_months: number; purpose: string; repayment_frequency: string }>(
    app.id,
    (etag, body) => applicationsApi.amend(app.id, etag, { ...body, source: 'staff' }),
    {
      onStale: ctx.markStale,
      onSuccess: () => {
        onSaved();
        onDone();
      },
    },
  );
  const e = form.formState.errors;
  return (
    <form
      noValidate
      className="space-y-4"
      onSubmit={(ev) =>
        void form.handleSubmit((v) => {
          const parsed = schema.parse(v);
          save.mutate(parsed);
        })(ev)
      }
    >
      {isStaleProblem(save.error) && (
        <Banner tone="warning" title="Not saved: the application changed">
          Your edits are kept below. Reload the application (banner above), then save again.
        </Banner>
      )}
      {save.error && !isStaleProblem(save.error) && <ErrorSummary title="The terms were not saved" problem={isApiProblem(save.error) ? save.error : null} messages={isApiProblem(save.error) ? undefined : [save.error.message]} />}
      <div className="grid gap-4 sm:grid-cols-3">
        <TextInput label={`Amount (${app.currency})`} inputMode="decimal" {...form.register('requested_amount')} error={e.requested_amount?.message} />
        <TextInput label="Tenor (months)" type="number" {...form.register('tenor_months')} error={e.tenor_months?.message} />
        <Select label="Repayment" options={REPAYMENT_FREQUENCIES.map((f) => ({ value: f, label: f }))} {...form.register('repayment_frequency')} />
      </div>
      <TextArea label="Purpose" {...form.register('purpose')} error={e.purpose?.message} />
      <p className="text-meta text-tertiary">Changes are recorded in the field history with you as the source.</p>
      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onDone} disabled={save.isPending}>
          Cancel
        </Button>
        <Button type="submit" loading={save.isPending}>
          Save terms
        </Button>
      </div>
    </form>
  );
}

function ApplicantsSection({ ctx }: { ctx: CaseContextValue }) {
  const { app, permissions } = ctx;
  const toast = useToast();
  const [adding, setAdding] = useState(false);
  const [removing, setRemoving] = useState<Applicant | null>(null);
  const canOriginate = permissions.has(PERM.originate);
  const editable = canAmend(app.status);
  const reason = !canOriginate ? 'Requires the application:originate permission.' : !editable ? 'Applicants can only change before approval.' : null;
  const remove = useIfMatchMutation<string>(app.id, (etag, partyId) => applicationsApi.removeApplicant(app.id, etag, partyId), {
    onStale: ctx.markStale,
    onSuccess: () => {
      toast.show(`${removing?.display_name ?? 'Applicant'} removed`, 'success');
      setRemoving(null);
    },
  });

  return (
    <section aria-labelledby="applicants-h">
      <CardHeader
        title="Applicants"
        titleId="applicants-h"
        actions={
          <Button size="sm" variant="secondary" leadingIcon={<UserPlus aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={reason} onClick={() => setAdding(true)}>
            Add guarantor or joint
          </Button>
        }
      />
      <ul className="divide-y divide-subtle rounded-card border">
        {app.applicants.map((a) => (
          <li key={a.party_id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div className="min-w-0">
              <Link to={`/parties/${a.party_id}`} className="font-semibold text-link hover:underline">
                {a.display_name}
              </Link>
              <p className="text-meta text-tertiary">{a.party_type === 'limited_company' ? 'Limited company' : 'Individual'}</p>
            </div>
            <div className="flex items-center gap-2">
              <Pill tone={a.role === 'primary' ? 'brand' : 'neutral'}>{ROLE_LABEL[a.role] ?? a.role}</Pill>
              {a.role !== 'primary' && (
                <Button size="sm" variant="ghost" leadingIcon={<UserMinus aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabledReason={reason} onClick={() => setRemoving(a)} aria-label={`Remove ${a.display_name}`}>
                  Remove
                </Button>
              )}
            </div>
          </li>
        ))}
      </ul>
      {remove.error && !isStaleProblem(remove.error) && <div className="mt-3"><ErrorSummary problem={isApiProblem(remove.error) ? remove.error : null} messages={isApiProblem(remove.error) ? undefined : [remove.error.message]} /></div>}
      <ConfirmDialog
        open={removing !== null}
        title={`Remove ${removing?.display_name ?? ''}?`}
        description="They are removed from this application only; the customer record is kept."
        confirmLabel="Remove applicant"
        destructive
        busy={remove.isPending}
        onCancel={() => setRemoving(null)}
        onConfirm={() => removing && remove.mutate(removing.party_id)}
      />
      {adding && <AddApplicantDialog ctx={ctx} onClose={() => setAdding(false)} />}
    </section>
  );
}

function AddApplicantDialog({ ctx, onClose }: { ctx: CaseContextValue; onClose: () => void }) {
  const { app } = ctx;
  const toast = useToast();
  const [role, setRole] = useState<ApplicantRole>('guarantor');
  const [mode, setMode] = useState<'search' | 'create'>('search');
  const add = useIfMatchMutation<string>(app.id, (etag, partyId) => applicationsApi.addApplicant(app.id, etag, { party_id: partyId, role }), {
    onStale: ctx.markStale,
    onSuccess: () => {
      toast.show(`${ROLE_LABEL[role] ?? role} added`, 'success');
      onClose();
    },
  });
  const canCreate = ctx.permissions.has(PERM.partyManage);
  return (
    <Modal open onClose={onClose} dismissible={!add.isPending} title="Add an applicant" description="Joint applicants share the obligation; guarantors and co-signers support it." className="!max-w-[min(720px,calc(100%-2rem))]">
      <div className="space-y-4">
        {isStaleProblem(add.error) && <Banner tone="warning" title="The application changed. Reload it, then add the applicant again." />}
        {add.error && !isStaleProblem(add.error) && <ErrorSummary problem={isApiProblem(add.error) ? add.error : null} messages={isApiProblem(add.error) ? undefined : [add.error.message]} />}
        <Select
          label="Role"
          value={role}
          onChange={(e) => setRole(e.target.value as ApplicantRole)}
          options={[
            { value: 'guarantor', label: 'Guarantor' },
            { value: 'joint', label: 'Joint applicant' },
            { value: 'co_signer', label: 'Co-signer' },
          ]}
          className="max-w-[16rem]"
        />
        {mode === 'search' ? (
          <>
            <PartySearch selectLabel="Add" excludeIds={app.applicants.map((a) => a.party_id)} onSelect={(p) => add.mutate(p.id)} />
            <Button variant="ghost" size="sm" disabledReason={canCreate ? null : 'Requires the party:manage permission.'} onClick={() => setMode('create')}>
              Not found? Create a new customer
            </Button>
          </>
        ) : (
          <PartyCreateForm submitLabel="Create and add" onCreated={(p) => add.mutate(p.id)} onUseExisting={(m) => add.mutate(m.party_id)} onCancel={() => setMode('search')} />
        )}
      </div>
    </Modal>
  );
}
