import type { ButtonHTMLAttributes, ReactNode, Ref } from 'react';
import { cn } from '@/lib/cn';

export type IconButtonProps = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'aria-label' | 'children'> & {
  /** Required accessible name (icon-only control). */
  label: string;
  icon: ReactNode;
  variant?: 'ghost' | 'soft' | 'outline' | 'on-brand';
  size?: 'sm' | 'md' | 'lg';
  /** Small notification dot (decorative; announce the count in `label`). */
  dot?: boolean;
  ref?: Ref<HTMLButtonElement>;
};

const variants = {
  ghost: 'text-secondary hover:bg-hover-nav hover:text-emphasis',
  soft: 'bg-surface text-emphasis border border hover:bg-hover',
  outline: 'bg-surface text-emphasis border border-strong hover:bg-hover',
  'on-brand': 'text-on-brand hover:bg-brand-raised',
} as const;

const sizes = { sm: 'h-8 w-8', md: 'h-10 w-10', lg: 'h-12 w-12' } as const;

export function IconButton({ label, icon, variant = 'ghost', size = 'md', dot = false, className, type = 'button', ref, ...rest }: IconButtonProps) {
  return (
    <button
      ref={ref}
      type={type}
      aria-label={label}
      title={label}
      className={cn(
        'relative inline-flex shrink-0 items-center justify-center rounded-full transition-colors duration-fast',
        'min-h-target min-w-target disabled:cursor-not-allowed disabled:text-disabled',
        variants[variant],
        sizes[size],
        className,
      )}
      {...rest}
    >
      <span aria-hidden="true" className="inline-flex">{icon}</span>
      {dot && <span aria-hidden="true" className="absolute right-2.5 top-2.5 h-dot w-dot rounded-full border-2 border-surface bg-tone-danger box-content" />}
    </button>
  );
}
