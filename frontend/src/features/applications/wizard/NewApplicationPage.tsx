import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { ArrowLeft, ArrowRight, Building2, CheckCircle2, Package, User, UserPlus } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { applicationsApi, orgApi, type ProductSummary } from '@/api/lending';
import { isApiProblem } from '@/api/problem';
import {
  Banner,
  Button,
  Card,
  CardHeader,
  ErrorSummary,
  KeyValueGrid,
  MoneyText,
  SectionError,
  Select,
  Skeleton,
  Stepper,
  TextArea,
  TextInput,
  useToast,
} from '@/components';
import { cn } from '@/lib/cn';
import { RequirePermission } from '@/features/auth/RequirePermission';
import { usePermissions, useSession } from '@/features/auth/session';
import { PartyCreateForm } from '@/features/parties/components/PartyCreateForm';
import { PartySearch } from '@/features/parties/components/PartySearch';
import { DirectorsEditor } from '@/features/parties/components/DirectorsEditor';
import { PERM } from '../domain/actions';
import { appKeys, productsQuery } from '../queries';
import { REPAYMENT_FREQUENCIES, termsSchema, type TermsInput, type TermsValues } from './schemas';

const STEPS = ['Product', 'Applicant', 'Facility', 'Review'] as const;

export type ChosenParty = { id: string; display_name: string; type: string };

export function NewApplicationPage() {
  return (
    <RequirePermission anyOf={[PERM.originate]}>
      <Wizard />
    </RequirePermission>
  );
}

function Wizard() {
  const [step, setStep] = useState(0);
  const [product, setProduct] = useState<ProductSummary | null>(null);
  const [party, setParty] = useState<ChosenParty | null>(null);
  const [terms, setTerms] = useState<TermsValues | null>(null);
  const headingRef = useRef<HTMLHeadingElement>(null);

  // Move focus to the step heading on every step change (screen readers hear the new step).
  useEffect(() => {
    headingRef.current?.focus();
  }, [step]);

  return (
    <div className="mx-auto max-w-form space-y-6">
      <Link to="/applications" className="inline-flex min-h-target items-center gap-1.5 rounded-pill px-2 text-body-sm font-semibold text-link hover:bg-hover-nav">
        <ArrowLeft aria-hidden="true" className="h-icon-sm w-icon-sm" />
        Applications
      </Link>
      <Card>
        <Stepper steps={STEPS} current={step} label="New application steps" />
        <h2 ref={headingRef} tabIndex={-1} className="mt-6 text-title-lg text-emphasis focus-visible:outline-0">
          {['Choose a product', 'Primary applicant', 'Facility request', 'Review and create'][step]}
        </h2>
        <div className="mt-4">
          {step === 0 && (
            <ProductStep
              selected={product}
              onNext={(p) => {
                setProduct(p);
                if (terms && product?.key !== p.key) setTerms(null);
                setStep(1);
              }}
            />
          )}
          {step === 1 && product && (
            <ApplicantStep
              product={product}
              party={party}
              onChange={setParty}
              onBack={() => setStep(0)}
              onNext={() => setStep(2)}
            />
          )}
          {step === 2 && product && (
            <TermsStep
              product={product}
              initial={terms}
              onBack={() => setStep(1)}
              onNext={(t) => {
                setTerms(t);
                setStep(3);
              }}
            />
          )}
          {step === 3 && product && party && terms && <ReviewStep product={product} party={party} terms={terms} onBack={() => setStep(2)} onEdit={setStep} />}
        </div>
      </Card>
    </div>
  );
}

function rangeText(p: ProductSummary) {
  return (
    <>
      <MoneyText amount={p.amount.min.amount} currency={p.amount.min.currency} compact /> – <MoneyText amount={p.amount.max.amount} currency={p.amount.max.currency} compact />
    </>
  );
}

function ProductStep({ selected, onNext }: { selected: ProductSummary | null; onNext: (p: ProductSummary) => void }) {
  const products = useQuery(productsQuery);
  const [choice, setChoice] = useState<string | null>(selected?.key ?? null);
  if (products.isPending)
    return (
      <div className="grid gap-4 sm:grid-cols-2" aria-busy="true">
        <span className="sr-only" role="status">
          Loading products
        </span>
        <Skeleton className="h-[10rem] rounded-card" />
        <Skeleton className="h-[10rem] rounded-card" />
      </div>
    );
  if (products.isError) return <SectionError error={products.error} title="Products couldn't load" onRetry={() => void products.refetch()} />;
  if (products.data.length === 0)
    return (
      <Banner tone="info" title="No live products yet">
        A product must be activated (maker-checker) before applications can be captured. For local demo data run <span className="ref">deploy/dev/bootstrap-local.sh seed-demo</span>.
      </Banner>
    );
  const chosen = products.data.find((p) => p.key === choice) ?? null;
  return (
    <div className="space-y-5">
      <fieldset>
        <legend className="sr-only">Product</legend>
        <div className="grid gap-4 sm:grid-cols-2">
          {products.data.map((p) => {
            const on = p.key === choice;
            return (
              <label
                key={p.key}
                className={cn(
                  'relative flex cursor-pointer flex-col rounded-card border-2 p-5 transition-colors has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-focus',
                  on ? 'border-accent bg-accent-subtle' : 'border-subtle bg-surface hover:border-strong',
                )}
              >
                <input type="radio" name="product" value={p.key} checked={on} onChange={() => setChoice(p.key)} className="sr-only" />
                <span className="flex items-start justify-between gap-2">
                  <span aria-hidden="true" className="inline-flex h-icon-tile w-icon-tile items-center justify-center rounded-mark bg-muted text-emphasis">
                    <Package className="h-icon-lg w-icon-lg" />
                  </span>
                  {on && <CheckCircle2 aria-hidden="true" className="h-icon-lg w-icon-lg text-accent" />}
                </span>
                <span className="mt-4 text-title text-emphasis">{p.name}</span>
                <span className="mt-1 text-meta text-tertiary">
                  v{p.version_no} · {p.segment.toUpperCase()} · {p.applicant_types.map((t) => (t === 'limited_company' ? 'Companies' : 'Individuals')).join(' & ')}
                </span>
                <span className="mt-3 grid grid-cols-2 gap-2 text-body-sm">
                  <span>
                    <span className="block text-meta text-tertiary">Amount</span>
                    <span className="font-semibold text-primary">{rangeText(p)}</span>
                  </span>
                  <span>
                    <span className="block text-meta text-tertiary">Tenor</span>
                    <span className="font-semibold text-primary">
                      {p.tenor_months.min}–{p.tenor_months.max} months
                    </span>
                  </span>
                </span>
              </label>
            );
          })}
        </div>
      </fieldset>
      <p className="text-meta text-tertiary">The application stays on the product version you choose here, even if a newer version goes live.</p>
      <div className="flex justify-end">
        <Button trailingIcon={<ArrowRight aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabled={!chosen} onClick={() => chosen && onNext(chosen)}>
          Continue
        </Button>
      </div>
    </div>
  );
}

function ApplicantStep({ product, party, onChange, onBack, onNext }: { product: ProductSummary; party: ChosenParty | null; onChange: (p: ChosenParty | null) => void; onBack: () => void; onNext: () => void }) {
  const permissions = usePermissions();
  const canCreate = permissions.has(PERM.partyManage);
  const [mode, setMode] = useState<'search' | 'create'>('search');
  const typeAllowed = !party || product.applicant_types.includes(party.type);

  if (party) {
    const Icon = party.type === 'limited_company' ? Building2 : User;
    return (
      <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-card border bg-muted p-4">
          <div className="flex items-center gap-3">
            <Icon aria-hidden="true" className="h-icon-lg w-icon-lg text-emphasis" />
            <div>
              <p className="text-body font-semibold text-primary">{party.display_name}</p>
              <p className="text-meta text-tertiary">{party.type === 'limited_company' ? 'Limited company' : 'Individual'} · primary applicant</p>
            </div>
          </div>
          <Button variant="ghost" size="sm" onClick={() => onChange(null)}>
            Change applicant
          </Button>
        </div>
        {!typeAllowed && (
          <Banner tone="warning" title="This product does not accept this applicant type">
            {product.name} accepts {product.applicant_types.join(', ').replace(/_/g, ' ')}. Choose another applicant or product.
          </Banner>
        )}
        {party.type === 'limited_company' && <DirectorsEditor companyId={party.id} canManage={canCreate} />}
        <div className="flex justify-between">
          <Button variant="secondary" leadingIcon={<ArrowLeft aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={onBack}>
            Back
          </Button>
          <Button trailingIcon={<ArrowRight aria-hidden="true" className="h-icon-sm w-icon-sm" />} disabled={!typeAllowed} onClick={onNext}>
            Continue
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div role="group" aria-label="Find or create the applicant" className="inline-flex rounded-pill bg-muted p-1">
        {(
          [
            ['search', 'Find existing'],
            ['create', 'Create new'],
          ] as const
        ).map(([m, label]) => (
          <button
            key={m}
            type="button"
            aria-pressed={mode === m}
            disabled={m === 'create' && !canCreate}
            title={m === 'create' && !canCreate ? 'Requires the party:manage permission.' : undefined}
            onClick={() => setMode(m)}
            className={cn('min-h-target rounded-pill px-4 text-body-sm font-semibold', mode === m ? 'bg-surface text-emphasis shadow-seg' : 'text-secondary hover:text-primary disabled:text-disabled')}
          >
            {m === 'create' && <UserPlus aria-hidden="true" className="mr-1.5 inline h-icon-sm w-icon-sm" />}
            {label}
          </button>
        ))}
      </div>
      {mode === 'search' ? (
        <PartySearch onSelect={(p) => onChange({ id: p.id, display_name: p.display_name, type: p.type })} selectLabel="Use" />
      ) : (
        <PartyCreateForm
          submitLabel="Create and use"
          onCreated={(p) => onChange({ id: p.id, display_name: p.display_name, type: p.type })}
          onUseExisting={(m) => onChange({ id: m.party_id, display_name: m.display_name, type: m.type })}
          onCancel={() => setMode('search')}
        />
      )}
      <div className="flex justify-start">
        <Button variant="secondary" leadingIcon={<ArrowLeft aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={onBack}>
          Back
        </Button>
      </div>
    </div>
  );
}

function TermsStep({ product, initial, onBack, onNext }: { product: ProductSummary; initial: TermsValues | null; onBack: () => void; onNext: (t: TermsValues) => void }) {
  const entities = useQuery({ queryKey: ['org', 'legal-entities'], queryFn: () => orgApi.legalEntities(), staleTime: 5 * 60_000 });
  const form = useForm<TermsInput, unknown, TermsValues>({
    resolver: zodResolver(termsSchema(product)),
    defaultValues: initial ?? { legal_entity_id: '', org_unit_id: '', requested_amount: '', tenor_months: product.tenor_months.min, purpose: '', repayment_frequency: 'monthly' },
    mode: 'onTouched',
  });
  const { register, handleSubmit, formState, setValue, control } = form;
  const le = useWatch({ control, name: 'legal_entity_id' });
  const { me } = useSession();
  // Originator roles may lack legal_entity:read / org_unit:read: fall back to the user's home assignment.
  const entitiesDenied = isApiProblem(entities.error) && entities.error.status === 403;
  const homeLe = me.home_legal_entity_id ?? null;
  const homeOu = me.home_org_unit_id ?? null;
  const units = useQuery({ queryKey: ['org', 'units', le], queryFn: () => orgApi.orgUnits(le), enabled: le !== '' && !entitiesDenied, staleTime: 5 * 60_000 });
  const unitsDenied = entitiesDenied || (isApiProblem(units.error) && units.error.status === 403);
  const e = formState.errors;
  const entityOptions = entitiesDenied ? (homeLe ? [{ value: homeLe, label: 'Your home legal entity' }] : []) : (entities.data ?? []).map((x) => ({ value: x.id, label: `${x.name} (${x.code})` }));
  const unitOptions = unitsDenied ? (homeOu ? [{ value: homeOu, label: 'Your home branch' }] : []) : (units.data ?? []).map((u) => ({ value: u.id, label: `${u.name} (${u.code})` }));

  // A single legal entity (or the home one) is preselected; so is the home branch when the list is not readable.
  const onlyEntity = entityOptions.length === 1 ? entityOptions[0]?.value : undefined;
  useEffect(() => {
    if (onlyEntity && !le) setValue('legal_entity_id', onlyEntity, { shouldValidate: false });
  }, [onlyEntity, le, setValue]);
  useEffect(() => {
    if (unitsDenied && homeOu) setValue('org_unit_id', homeOu, { shouldValidate: false });
  }, [unitsDenied, homeOu, setValue]);

  const summary = Object.values(e)
    .map((x) => x.message)
    .filter((m): m is string => typeof m === 'string');

  return (
    <form noValidate onSubmit={(ev) => void handleSubmit(onNext)(ev)} className="space-y-4">
      {formState.submitCount > 0 && summary.length > 0 && <ErrorSummary messages={summary} />}
      {entities.isError && !entitiesDenied && <SectionError error={entities.error} title="Legal entities couldn't load" onRetry={() => void entities.refetch()} />}
      {unitsDenied && (
        <Banner tone={homeLe && homeOu ? 'info' : 'warning'} title={homeLe && homeOu ? 'Booked to your home legal entity and branch' : 'You cannot choose a legal entity or branch'}>
          {homeLe && homeOu
            ? 'Your role cannot list the organisation, so the application uses the legal entity and branch on your user profile.'
            : 'Your role cannot list the organisation and your profile has no home branch. Ask an administrator for org_unit:read or a home branch.'}
        </Banner>
      )}
      <div className="grid gap-4 sm:grid-cols-2">
        <Select
          label="Legal entity"
          required
          options={[{ value: '', label: entities.isPending ? 'Loading…' : 'Choose…' }, ...entityOptions]}
          {...register('legal_entity_id', { onChange: () => setValue('org_unit_id', '') })}
          error={e.legal_entity_id?.message}
        />
        <Select
          label="Branch"
          required
          disabled={!le}
          options={[{ value: '', label: !le ? 'Choose a legal entity first' : units.isPending && !unitsDenied ? 'Loading…' : 'Choose…' }, ...unitOptions]}
          {...register('org_unit_id')}
          error={e.org_unit_id?.message}
        />
        <TextInput
          label={`Requested amount (${product.currency})`}
          required
          inputMode="decimal"
          autoComplete="off"
          helper={
            <>
              Between <MoneyText amount={product.amount.min.amount} currency={product.currency} /> and <MoneyText amount={product.amount.max.amount} currency={product.currency} />
            </>
          }
          {...register('requested_amount')}
          error={e.requested_amount?.message}
        />
        <TextInput
          label="Tenor (months)"
          required
          type="number"
          min={product.tenor_months.min}
          max={product.tenor_months.max}
          helper={`${product.tenor_months.min} to ${product.tenor_months.max} months`}
          {...register('tenor_months')}
          error={e.tenor_months?.message}
        />
        <Select
          label="Repayment frequency"
          required
          options={REPAYMENT_FREQUENCIES.map((f) => ({ value: f, label: f === 'bullet' ? 'Bullet (at maturity)' : f.charAt(0).toUpperCase() + f.slice(1) }))}
          {...register('repayment_frequency')}
          error={e.repayment_frequency?.message}
        />
      </div>
      <TextArea label="Purpose" required maxLength={500} {...register('purpose')} error={e.purpose?.message} helper="What the facility will be used for (up to 500 characters)." />
      <div className="flex justify-between">
        <Button variant="secondary" leadingIcon={<ArrowLeft aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={onBack}>
          Back
        </Button>
        <Button type="submit" trailingIcon={<ArrowRight aria-hidden="true" className="h-icon-sm w-icon-sm" />}>
          Review
        </Button>
      </div>
    </form>
  );
}

function ReviewStep({ product, party, terms, onBack, onEdit }: { product: ProductSummary; party: ChosenParty; terms: TermsValues; onBack: () => void; onEdit: (step: number) => void }) {
  const navigate = useNavigate();
  const qc = useQueryClient();
  const toast = useToast();
  // One key per review: a retry of the same create is deduplicated by the server.
  const [key] = useState(idempotencyKey);
  const entities = useQuery({ queryKey: ['org', 'legal-entities'], queryFn: () => orgApi.legalEntities(), staleTime: 5 * 60_000 });
  const units = useQuery({ queryKey: ['org', 'units', terms.legal_entity_id], queryFn: () => orgApi.orgUnits(terms.legal_entity_id), staleTime: 5 * 60_000 });
  const create = useMutation({
    mutationFn: () =>
      applicationsApi.create(
        {
          legal_entity_id: terms.legal_entity_id,
          org_unit_id: terms.org_unit_id,
          product_key: product.key,
          primary_party_id: party.id,
          channel: 'staff',
          requested_amount: terms.requested_amount,
          tenor_months: terms.tenor_months,
          purpose: terms.purpose,
          repayment_frequency: terms.repayment_frequency,
        },
        key,
      ),
    onSuccess: async (res) => {
      qc.setQueryData(appKeys.detail(res.data.id), res);
      await qc.invalidateQueries({ queryKey: appKeys.lists });
      await qc.invalidateQueries({ queryKey: appKeys.stats });
      toast.show(`Draft ${res.data.reference} created`, 'success');
      await navigate(`/applications/${res.data.id}`, { state: { created: res.data.reference } });
    },
  });
  const le = entities.data?.find((x) => x.id === terms.legal_entity_id);
  const ou = units.data?.find((x) => x.id === terms.org_unit_id);
  const problem = isApiProblem(create.error) ? create.error : null;

  return (
    <div className="space-y-5">
      {create.error && <ErrorSummary title="The application was not created" problem={problem} messages={problem ? undefined : [create.error.message]} />}
      <section aria-labelledby="rv-product" className="rounded-card border p-4">
        <CardHeader title="Product" titleId="rv-product" level={3} actions={<Button variant="ghost" size="sm" onClick={() => onEdit(0)}>Edit</Button>} className="mb-2" />
        <KeyValueGrid items={[{ label: 'Product', value: `${product.name} · v${product.version_no}` }, { label: 'Range', value: rangeText(product) }]} />
      </section>
      <section aria-labelledby="rv-applicant" className="rounded-card border p-4">
        <CardHeader title="Primary applicant" titleId="rv-applicant" level={3} actions={<Button variant="ghost" size="sm" onClick={() => onEdit(1)}>Edit</Button>} className="mb-2" />
        <KeyValueGrid items={[{ label: 'Name', value: party.display_name }, { label: 'Type', value: party.type === 'limited_company' ? 'Limited company' : 'Individual' }]} />
      </section>
      <section aria-labelledby="rv-terms" className="rounded-card border p-4">
        <CardHeader title="Facility request" titleId="rv-terms" level={3} actions={<Button variant="ghost" size="sm" onClick={() => onEdit(2)}>Edit</Button>} className="mb-2" />
        <KeyValueGrid
          columns={3}
          items={[
            { label: 'Amount', value: <MoneyText amount={terms.requested_amount} currency={product.currency} /> },
            { label: 'Tenor', value: `${terms.tenor_months} months` },
            { label: 'Repayment', value: <span className="capitalize">{terms.repayment_frequency}</span> },
            { label: 'Legal entity', value: le ? le.name : entities.isError ? 'Your home legal entity' : '…' },
            { label: 'Branch', value: ou ? ou.name : units.isError ? 'Your home branch' : '…' },
            { label: 'Purpose', value: terms.purpose },
          ]}
        />
      </section>
      <p className="text-body-sm text-secondary">Creating saves a draft with a reference number. You can add applicants, record consents and upload documents before you submit it.</p>
      <div className="flex justify-between">
        <Button variant="secondary" leadingIcon={<ArrowLeft aria-hidden="true" className="h-icon-sm w-icon-sm" />} onClick={onBack} disabled={create.isPending}>
          Back
        </Button>
        <Button onClick={() => create.mutate()} loading={create.isPending}>
          Create draft application
        </Button>
      </div>
    </div>
  );
}

export default NewApplicationPage;
