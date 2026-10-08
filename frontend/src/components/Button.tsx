import type { ButtonHTMLAttributes, ReactNode, Ref } from 'react';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/cn';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'accent';
export type ButtonSize = 'sm' | 'md' | 'lg';

const base =
  'inline-flex items-center justify-center gap-2 rounded-pill font-semibold whitespace-nowrap select-none ' +
  'transition-colors duration-fast ease-standard min-h-target min-w-target ' +
  'disabled:cursor-not-allowed disabled:text-disabled aria-busy:cursor-progress';

const variants: Record<ButtonVariant, string> = {
  primary: 'bg-accent text-on-accent shadow-button hover:bg-accent-hover active:bg-accent-pressed disabled:bg-neutral',
  accent: 'bg-accent-fill text-on-accent-fill hover:bg-accent-fill-hover active:bg-accent-fill-hover disabled:bg-neutral',
  secondary: 'bg-surface text-primary border border-strong shadow-button hover:bg-hover active:bg-pressed disabled:bg-muted',
  ghost: 'bg-transparent text-emphasis hover:bg-hover-nav active:bg-pressed',
  danger: 'bg-danger-fill text-on-danger hover:bg-danger-fill-hover disabled:bg-neutral',
};

const sizes: Record<ButtonSize, string> = {
  sm: 'h-control-sm px-3.5 text-body-sm',
  md: 'h-control px-5 text-body',
  lg: 'h-control-lg px-6 text-body',
};

export type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
  leadingIcon?: ReactNode;
  trailingIcon?: ReactNode;
  fullWidth?: boolean;
  ref?: Ref<HTMLButtonElement>;
};

export function Button({
  variant = 'primary',
  size = 'md',
  loading = false,
  leadingIcon,
  trailingIcon,
  fullWidth = false,
  className,
  children,
  disabled,
  type = 'button',
  ref,
  onClick,
  ...rest
}: ButtonProps) {
  return (
    <button
      ref={ref}
      type={type}
      className={cn(base, variants[variant], sizes[size], fullWidth && 'w-full', className)}
      disabled={disabled}
      aria-disabled={loading || undefined}
      aria-busy={loading || undefined}
      onClick={loading ? (e) => e.preventDefault() : onClick}
      {...rest}
    >
      {loading ? <Loader2 aria-hidden="true" className="h-icon w-icon animate-spin" /> : leadingIcon}
      {children}
      {trailingIcon}
    </button>
  );
}
