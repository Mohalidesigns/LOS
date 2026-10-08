import { cn } from '@/lib/cn';

export function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  const first = parts[0]?.[0] ?? '?';
  const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : '';
  return (first + last).toUpperCase();
}

/** Initials avatar (no remote images: CSP default-src 'self'). Decorative unless `label` is given. */
export function Avatar({ name, size = 'md', label, className }: { name: string; size?: 'sm' | 'md'; label?: string; className?: string }) {
  return (
    <span
      role={label ? 'img' : undefined}
      aria-label={label}
      aria-hidden={label ? undefined : true}
      className={cn(
        'inline-flex shrink-0 items-center justify-center rounded-full bg-accent-fill font-bold text-on-accent-fill',
        size === 'md' ? 'h-avatar w-avatar text-body-sm' : 'h-8 w-8 text-micro',
        className,
      )}
    >
      {initials(name)}
    </span>
  );
}
