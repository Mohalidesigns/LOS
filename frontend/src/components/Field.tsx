import type { InputHTMLAttributes, LabelHTMLAttributes, ReactNode, Ref, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';
import { AlertCircle, ChevronDown } from 'lucide-react';
import { cn } from '@/lib/cn';

export function InputLabel({ className, children, required, ...rest }: LabelHTMLAttributes<HTMLLabelElement> & { required?: boolean }) {
  return (
    <label className={cn('mb-1.5 block text-body-sm font-semibold text-primary', className)} {...rest}>
      {children}
      {required && (
        <span className="text-danger" aria-hidden="true">
          {' '}*
        </span>
      )}
    </label>
  );
}

export function HelperText({ id, children, className }: { id?: string; children: ReactNode; className?: string }) {
  return (
    <p id={id} className={cn('mt-1.5 text-meta text-tertiary', className)}>
      {children}
    </p>
  );
}

export function InputError({ id, children }: { id?: string; children?: ReactNode }) {
  if (!children) return null;
  return (
    <p id={id} className="mt-1.5 flex items-start gap-1.5 text-meta font-medium text-danger">
      <AlertCircle aria-hidden="true" className="mt-px h-icon-sm w-icon-sm shrink-0" />
      <span>{children}</span>
    </p>
  );
}

const controlBase =
  'block w-full rounded-control border bg-surface px-3.5 text-body text-primary placeholder:text-placeholder ' +
  'transition-colors duration-fast hover:border-control-hover disabled:cursor-not-allowed disabled:bg-muted disabled:text-disabled';

export type TextInputProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  /** Visually hide the label (still announced). */
  hideLabel?: boolean;
  helper?: ReactNode;
  error?: string | undefined;
  leading?: ReactNode;
  size?: never;
  inputSize?: 'md' | 'lg';
  ref?: Ref<HTMLInputElement>;
};

export function TextInput({ id, name, label, hideLabel, helper, error, leading, inputSize = 'md', className, required, ref, ...rest }: TextInputProps) {
  const inputId = id ?? `f-${name ?? label.replace(/\W+/g, '-').toLowerCase()}`;
  const helperId = helper ? `${inputId}-help` : undefined;
  const errorId = error ? `${inputId}-error` : undefined;
  return (
    <div className={className}>
      <InputLabel htmlFor={inputId} required={required} className={hideLabel ? 'sr-only' : undefined}>
        {label}
      </InputLabel>
      <div className="relative">
        {leading && <span aria-hidden="true" className="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-indicator">{leading}</span>}
        <input
          ref={ref}
          id={inputId}
          name={name}
          required={required}
          aria-invalid={error ? true : undefined}
          aria-describedby={[errorId, helperId].filter(Boolean).join(' ') || undefined}
          className={cn(
            controlBase,
            inputSize === 'lg' ? 'h-control-lg' : 'h-control',
            error ? 'border-danger-fill' : 'border-control',
            leading ? 'pl-10' : undefined,
          )}
          {...rest}
        />
      </div>
      <InputError id={errorId}>{error}</InputError>
      {helper && <HelperText id={helperId}>{helper}</HelperText>}
    </div>
  );
}

export type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label: string;
  hideLabel?: boolean;
  options: readonly { value: string; label: string }[];
  error?: string | undefined;
  helper?: ReactNode;
  ref?: Ref<HTMLSelectElement>;
};

export function Select({ id, name, label, hideLabel, options, error, helper, className, required, ref, ...rest }: SelectProps) {
  const selectId = id ?? `s-${name ?? label.replace(/\W+/g, '-').toLowerCase()}`;
  const errorId = error ? `${selectId}-error` : undefined;
  const helperId = helper ? `${selectId}-help` : undefined;
  return (
    <div className={className}>
      <InputLabel htmlFor={selectId} required={required} className={hideLabel ? 'sr-only' : undefined}>
        {label}
      </InputLabel>
      <div className="relative">
        <select
          ref={ref}
          id={selectId}
          name={name}
          required={required}
          aria-invalid={error ? true : undefined}
          aria-describedby={[errorId, helperId].filter(Boolean).join(' ') || undefined}
          className={cn(controlBase, 'h-control appearance-none pr-10', error ? 'border-danger-fill' : 'border-control')}
          {...rest}
        >
          {options.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
        <ChevronDown aria-hidden="true" className="pointer-events-none absolute right-3.5 top-1/2 h-icon w-icon -translate-y-1/2 text-indicator" />
      </div>
      <InputError id={errorId}>{error}</InputError>
      {helper && <HelperText id={helperId}>{helper}</HelperText>}
    </div>
  );
}

export type CheckboxProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
  label: ReactNode;
  description?: ReactNode;
  ref?: Ref<HTMLInputElement>;
};

export function Checkbox({ id, name, label, description, className, ref, ...rest }: CheckboxProps) {
  const boxId = id ?? `c-${name ?? 'checkbox'}`;
  const descId = description ? `${boxId}-desc` : undefined;
  return (
    <div className={cn('flex items-start gap-2.5', className)}>
      <input
        ref={ref}
        id={boxId}
        name={name}
        type="checkbox"
        aria-describedby={descId}
        className="mt-0.5 h-5 w-5 shrink-0 cursor-pointer rounded-mark border border-control accent-accent"
        {...rest}
      />
      <div>
        <label htmlFor={boxId} className="cursor-pointer text-body text-primary">
          {label}
        </label>
        {description && (
          <p id={descId} className="text-meta text-tertiary">
            {description}
          </p>
        )}
      </div>
    </div>
  );
}

export type TextAreaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & {
  label: string;
  helper?: ReactNode;
  error?: string | undefined;
  ref?: Ref<HTMLTextAreaElement>;
};

export function TextArea({ id, name, label, helper, error, className, required, rows = 3, ref, ...rest }: TextAreaProps) {
  const areaId = id ?? `t-${name ?? label.replace(/\W+/g, '-').toLowerCase()}`;
  const helperId = helper ? `${areaId}-help` : undefined;
  const errorId = error ? `${areaId}-error` : undefined;
  return (
    <div className={className}>
      <InputLabel htmlFor={areaId} required={required}>
        {label}
      </InputLabel>
      <textarea
        ref={ref}
        id={areaId}
        name={name}
        rows={rows}
        required={required}
        aria-invalid={error ? true : undefined}
        aria-describedby={[errorId, helperId].filter(Boolean).join(' ') || undefined}
        className={cn(controlBase, 'py-2.5', error ? 'border-danger-fill' : 'border-control')}
        {...rest}
      />
      <InputError id={errorId}>{error}</InputError>
      {helper && <HelperText id={helperId}>{helper}</HelperText>}
    </div>
  );
}
