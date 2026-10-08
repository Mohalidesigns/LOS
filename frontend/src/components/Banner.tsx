import type { ReactNode } from 'react';
import { AlertTriangle, CheckCircle2, Info, XCircle, X } from 'lucide-react';
import { cn } from '@/lib/cn';
import { IconButton } from './IconButton';

export type BannerTone = 'info' | 'success' | 'warning' | 'danger';

const META = {
  info: { icon: Info, box: 'bg-info border-info', iconCls: 'text-info' },
  success: { icon: CheckCircle2, box: 'bg-success border-success', iconCls: 'text-success' },
  warning: { icon: AlertTriangle, box: 'bg-warning border-warning', iconCls: 'text-warning' },
  danger: { icon: XCircle, box: 'bg-danger border-danger', iconCls: 'text-danger' },
} as const;

export function Banner({ tone = 'info', title, children, onDismiss, className }: { tone?: BannerTone; title: ReactNode; children?: ReactNode; onDismiss?: () => void; className?: string }) {
  const m = META[tone];
  const Icon = m.icon;
  return (
    <div role={tone === 'danger' || tone === 'warning' ? 'alert' : 'status'} className={cn('flex items-start gap-3 rounded-card border p-4', m.box, className)}>
      <Icon aria-hidden="true" className={cn('mt-0.5 h-icon w-icon shrink-0', m.iconCls)} />
      <div className="min-w-0 flex-1">
        <p className="text-body font-semibold text-primary">{title}</p>
        {children && <div className="mt-0.5 text-body-sm text-primary">{children}</div>}
      </div>
      {onDismiss && <IconButton label="Dismiss" size="sm" icon={<X className="h-icon-sm w-icon-sm" />} onClick={onDismiss} />}
    </div>
  );
}
