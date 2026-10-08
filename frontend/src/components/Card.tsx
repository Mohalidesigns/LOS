import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';

export type CardProps = HTMLAttributes<HTMLElement> & {
  as?: 'section' | 'div' | 'article' | 'aside';
  tone?: 'default' | 'brand' | 'muted';
  padded?: boolean;
};

/** White card with hairline border and large radius (loan-ui). `tone="brand"` = dark forest hero surface. */
export function Card({ as: Tag = 'section', tone = 'default', padded = true, className, children, ...rest }: CardProps) {
  return (
    <Tag
      data-surface={tone === 'brand' ? 'brand' : undefined}
      className={cn(
        'rounded-card',
        tone === 'default' && 'border bg-surface shadow-card',
        tone === 'muted' && 'bg-muted',
        tone === 'brand' && 'bg-brand text-on-brand',
        padded && 'px-card-x py-card-y',
        className,
      )}
      {...rest}
    >
      {children}
    </Tag>
  );
}

export type CardHeaderProps = {
  title: ReactNode;
  titleId?: string;
  /** Heading level for the title (default h2). */
  level?: 2 | 3;
  subtitle?: ReactNode;
  /** Right-aligned actions (period select, filter button …). */
  actions?: ReactNode;
  /** Kebab menu slot, rendered last. */
  menu?: ReactNode;
  className?: string;
};

export function CardHeader({ title, titleId, level = 2, subtitle, actions, menu, className }: CardHeaderProps) {
  const H = level === 2 ? 'h2' : 'h3';
  return (
    <div className={cn('mb-4 flex items-start justify-between gap-3', className)}>
      <div className="min-w-0">
        <H id={titleId} className="text-title text-emphasis">
          {title}
        </H>
        {subtitle && <p className="mt-0.5 text-body-sm text-tertiary">{subtitle}</p>}
      </div>
      {(actions ?? menu) && (
        <div className="flex shrink-0 items-center gap-2">
          {actions}
          {menu}
        </div>
      )}
    </div>
  );
}

/** Panel = Card with a header (AuditPro naming). */
export function Panel({ title, subtitle, actions, menu, children, className, ...rest }: Omit<CardProps, 'title'> & CardHeaderProps & { children?: ReactNode }) {
  return (
    <Card className={className} {...rest}>
      <CardHeader title={title} subtitle={subtitle} actions={actions} menu={menu} />
      {children}
    </Card>
  );
}
