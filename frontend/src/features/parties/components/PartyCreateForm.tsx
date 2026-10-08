import { useEffect, useId, useState } from 'react';
import { useForm, useWatch, type FieldValues, type Path, type UseFormSetError } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Building2, User } from 'lucide-react';
import { idempotencyKey } from '@/api/client';
import { partiesApi, type CreatedParty } from '@/api/lending';
import { isApiProblem, type ApiProblem } from '@/api/problem';
import { Button, ErrorSummary, Select, TextInput } from '@/components';
import { cn } from '@/lib/cn';
import {
  companySchema,
  companyToCreate,
  individualSchema,
  individualToCreate,
  matchQuery,
  todayLagos,
  type CompanyValues,
  type IndividualValues,
} from '@/features/applications/wizard/schemas';
import { DuplicateCard } from './DuplicateCard';

export type PartyKind = 'individual' | 'limited_company';

/** Map a 422 problem's field errors onto form fields (server stays authoritative). */
export function applyServerErrors<T extends FieldValues>(problem: ApiProblem, setError: UseFormSetError<T>, fields: readonly string[], map: Record<string, Path<T>> = {}): string[] {
  const unmatched: string[] = [];
  for (const [key, messages] of Object.entries(problem.errors)) {
    const field = map[key] ?? (fields.includes(key) ? (key as Path<T>) : undefined);
    const message = messages[0] ?? 'Invalid value.';
    if (field) setError(field, { type: 'server', message });
    else unmatched.push(message);
  }
  return unmatched;
}

function useDebounced<T>(value: T, ms: number): T {
  const [v, setV] = useState(value);
  useEffect(() => {
    const t = window.setTimeout(() => setV(value), ms);
    return () => window.clearTimeout(t);
  }, [value, ms]);
  return v;
}

export type PartyCreateFormProps = {
  /** Lock the type (e.g. a director is always an individual). */
  kind?: PartyKind;
  orgUnitId?: string | null;
  submitLabel?: string;
  onCreated: (party: CreatedParty) => void;
  onUseExisting: (match: { party_id: string; display_name: string; type: string }) => void;
  onCancel?: () => void;
};

/** Create an individual or a limited company, with a live dedupe check before creating (FR-CHN-007). */
export function PartyCreateForm({ kind: fixedKind, orgUnitId = null, submitLabel = 'Create customer', onCreated, onUseExisting, onCancel }: PartyCreateFormProps) {
  const [kind, setKind] = useState<PartyKind>(fixedKind ?? 'individual');
  const effective = fixedKind ?? kind;
  return (
    <div className="space-y-4">
      {!fixedKind && (
        <fieldset>
          <legend className="mb-2 text-body-sm font-semibold text-primary">Customer type</legend>
          <div className="flex flex-wrap gap-2">
            {(
              [
                ['individual', 'Individual', User],
                ['limited_company', 'Limited company', Building2],
              ] as const
            ).map(([value, label, Icon]) => (
              <label
                key={value}
                className={cn(
                  'inline-flex min-h-target cursor-pointer items-center gap-2 rounded-pill border px-4 py-2 text-body-sm font-medium has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-focus',
                  kind === value ? 'border-accent bg-accent text-on-accent' : 'border-strong bg-surface text-primary hover:bg-hover',
                )}
              >
                <input type="radio" name="party-kind" value={value} checked={kind === value} onChange={() => setKind(value)} className="sr-only" />
                <Icon aria-hidden="true" className="h-icon-sm w-icon-sm" />
                {label}
              </label>
            ))}
          </div>
        </fieldset>
      )}
      {effective === 'individual' ? (
        <IndividualForm key="individual" orgUnitId={orgUnitId} submitLabel={submitLabel} onCreated={onCreated} onUseExisting={onUseExisting} onCancel={onCancel} />
      ) : (
        <CompanyForm key="company" orgUnitId={orgUnitId} submitLabel={submitLabel} onCreated={onCreated} onUseExisting={onUseExisting} onCancel={onCancel} />
      )}
    </div>
  );
}

type InnerProps = Omit<PartyCreateFormProps, 'kind' | 'orgUnitId'> & { orgUnitId: string | null };

function useLiveMatches(kind: PartyKind, values: Partial<IndividualValues & CompanyValues>) {
  const query = useDebounced(matchQuery(kind, values), 600);
  const key = JSON.stringify(query);
  return useQuery({
    queryKey: ['parties', 'match', key],
    queryFn: () => partiesApi.match(query ?? {}),
    enabled: query !== null,
    staleTime: 60_000,
  });
}

function useCreate(onCreated: (p: CreatedParty) => void) {
  const [key] = useState(idempotencyKey);
  const [created, setCreated] = useState<CreatedParty | null>(null);
  const mutation = useMutation({
    mutationFn: (body: Parameters<typeof partiesApi.create>[0]) => partiesApi.create(body, key),
    onSuccess: (p) => {
      if (p.possible_duplicates.length > 0) setCreated(p);
      else onCreated(p);
    },
  });
  return { mutation, created };
}

function CreatedWithDuplicates({ created, onCreated, onUseExisting }: { created: CreatedParty; onCreated: (p: CreatedParty) => void; onUseExisting: InnerProps['onUseExisting'] }) {
  return (
    <DuplicateCard
      title={`${created.display_name} was created, but looks like an existing customer`}
      matches={created.possible_duplicates}
      onUse={onUseExisting}
      footer={
        <Button variant="secondary" onClick={() => onCreated(created)}>
          Continue with the new record
        </Button>
      }
    />
  );
}

function IndividualForm({ orgUnitId, submitLabel, onCreated, onUseExisting, onCancel }: InnerProps) {
  const id = useId();
  const form = useForm<IndividualValues>({ resolver: zodResolver(individualSchema), defaultValues: { nationality: 'NG', gender: '' }, mode: 'onTouched' });
  const { register, handleSubmit, formState, setError, control } = form;
  const values = useWatch({ control });
  const matches = useLiveMatches('individual', values);
  const { mutation, created } = useCreate(onCreated);
  const [unmatched, setUnmatched] = useState<string[]>([]);
  const e = formState.errors;

  if (created) return <CreatedWithDuplicates created={created} onCreated={onCreated} onUseExisting={onUseExisting} />;

  const submit = handleSubmit((v) => {
    setUnmatched([]);
    mutation.mutate(individualToCreate(v, orgUnitId), {
      onError: (err) => {
        if (isApiProblem(err) && err.status === 422) setUnmatched(applyServerErrors(err, setError, Object.keys(individualSchema.innerType().shape), { 'identities.0.value': 'bvn', 'identities.1.value': 'nin' }));
      },
    });
  });

  const summary = Object.values(e)
    .map((x) => x.message)
    .filter((m): m is string => typeof m === 'string');

  return (
    <form noValidate onSubmit={(ev) => void submit(ev)} className="space-y-4" aria-labelledby={`${id}-h`}>
      <h3 id={`${id}-h`} className="sr-only">
        New individual
      </h3>
      {formState.submitCount > 0 && summary.length > 0 && <ErrorSummary messages={summary} />}
      {mutation.error && !(isApiProblem(mutation.error) && mutation.error.status === 422) && <ErrorSummary problem={isApiProblem(mutation.error) ? mutation.error : null} messages={isApiProblem(mutation.error) ? undefined : [mutation.error.message]} />}
      {unmatched.length > 0 && <ErrorSummary messages={unmatched} />}
      <div className="grid gap-4 sm:grid-cols-3">
        <TextInput id={`${id}-first`} label="First name" required autoComplete="off" {...register('first_name')} error={e.first_name?.message} />
        <TextInput id={`${id}-middle`} label="Middle name" autoComplete="off" {...register('middle_name')} error={e.middle_name?.message} />
        <TextInput id={`${id}-last`} label="Last name" required autoComplete="off" {...register('last_name')} error={e.last_name?.message} />
      </div>
      <div className="grid gap-4 sm:grid-cols-3">
        <TextInput id={`${id}-dob`} type="date" label="Date of birth" required max={todayLagos()} {...register('date_of_birth')} error={e.date_of_birth?.message} />
        <Select
          id={`${id}-gender`}
          label="Gender"
          options={[
            { value: '', label: 'Not stated' },
            { value: 'female', label: 'Female' },
            { value: 'male', label: 'Male' },
            { value: 'other', label: 'Other' },
            { value: 'undisclosed', label: 'Prefer not to say' },
          ]}
          {...register('gender')}
        />
        <TextInput id={`${id}-nat`} label="Nationality" helper="Two-letter code" maxLength={2} {...register('nationality')} error={e.nationality?.message} />
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <TextInput id={`${id}-bvn`} label="BVN" inputMode="numeric" autoComplete="off" helper="11 digits. Shown masked after saving." {...register('bvn')} error={e.bvn?.message} />
        <TextInput id={`${id}-nin`} label="NIN" inputMode="numeric" autoComplete="off" helper="11 digits (optional)" {...register('nin')} error={e.nin?.message} />
        <TextInput id={`${id}-phone`} type="tel" label="Phone" autoComplete="off" helper="For example 08031234567" {...register('phone')} error={e.phone?.message} />
        <TextInput id={`${id}-email`} type="email" label="Email" autoComplete="off" {...register('email')} error={e.email?.message} />
      </div>
      {matches.data && matches.data.length > 0 && <DuplicateCard title="Possible existing customers" live matches={matches.data} onUse={onUseExisting} />}
      <div className="flex flex-wrap justify-end gap-2">
        {onCancel && (
          <Button variant="secondary" onClick={onCancel}>
            Cancel
          </Button>
        )}
        <Button type="submit" loading={mutation.isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}

function CompanyForm({ orgUnitId, submitLabel, onCreated, onUseExisting, onCancel }: InnerProps) {
  const id = useId();
  const form = useForm<CompanyValues>({ resolver: zodResolver(companySchema), mode: 'onTouched' });
  const { register, handleSubmit, formState, setError, control } = form;
  const values = useWatch({ control });
  const matches = useLiveMatches('limited_company', values);
  const { mutation, created } = useCreate(onCreated);
  const [unmatched, setUnmatched] = useState<string[]>([]);
  const e = formState.errors;

  if (created) return <CreatedWithDuplicates created={created} onCreated={onCreated} onUseExisting={onUseExisting} />;

  const submit = handleSubmit((v) => {
    setUnmatched([]);
    mutation.mutate(companyToCreate(v, orgUnitId), {
      onError: (err) => {
        if (isApiProblem(err) && err.status === 422) setUnmatched(applyServerErrors(err, setError, Object.keys(companySchema.innerType().shape), { 'address.line1': 'address_line1', 'address.city': 'city', 'address.state': 'state' }));
      },
    });
  });

  const summary = Object.values(e)
    .map((x) => x.message)
    .filter((m): m is string => typeof m === 'string');

  return (
    <form noValidate onSubmit={(ev) => void submit(ev)} className="space-y-4" aria-labelledby={`${id}-h`}>
      <h3 id={`${id}-h`} className="sr-only">
        New limited company
      </h3>
      {formState.submitCount > 0 && summary.length > 0 && <ErrorSummary messages={summary} />}
      {mutation.error && !(isApiProblem(mutation.error) && mutation.error.status === 422) && <ErrorSummary problem={isApiProblem(mutation.error) ? mutation.error : null} messages={isApiProblem(mutation.error) ? undefined : [mutation.error.message]} />}
      {unmatched.length > 0 && <ErrorSummary messages={unmatched} />}
      <div className="grid gap-4 sm:grid-cols-2">
        <TextInput id={`${id}-name`} label="Registered name" required autoComplete="off" {...register('company_name')} error={e.company_name?.message} />
        <TextInput id={`${id}-rc`} label="CAC registration number" required autoComplete="off" helper="For example RC1234567" {...register('registration_number')} error={e.registration_number?.message} />
        <TextInput id={`${id}-inc`} type="date" label="Incorporation date" max={todayLagos()} {...register('incorporation_date')} error={e.incorporation_date?.message} />
        <TextInput id={`${id}-sector`} label="Sector" autoComplete="off" helper="For example agro_processing" {...register('sector')} error={e.sector?.message} />
        <TextInput id={`${id}-tin`} label="TIN" autoComplete="off" {...register('tin')} error={e.tin?.message} />
        <TextInput id={`${id}-phone`} type="tel" label="Phone" autoComplete="off" {...register('phone')} error={e.phone?.message} />
        <TextInput id={`${id}-email`} type="email" label="Email" autoComplete="off" {...register('email')} error={e.email?.message} />
        <TextInput id={`${id}-line1`} label="Address" autoComplete="off" {...register('address_line1')} error={e.address_line1?.message} />
        <TextInput id={`${id}-city`} label="City" autoComplete="off" {...register('city')} error={e.city?.message} />
        <TextInput id={`${id}-state`} label="State" autoComplete="off" {...register('state')} error={e.state?.message} />
      </div>
      {matches.data && matches.data.length > 0 && <DuplicateCard title="Possible existing customers" live matches={matches.data} onUse={onUseExisting} />}
      <div className="flex flex-wrap justify-end gap-2">
        {onCancel && (
          <Button variant="secondary" onClick={onCancel}>
            Cancel
          </Button>
        )}
        <Button type="submit" loading={mutation.isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
