import { useId, useState } from 'react';
import { NavLink } from 'react-router';
import { ChevronDown, LogOut } from 'lucide-react';
import { cn } from '@/lib/cn';
import { FundlyLogo } from '@/components/FundlyLogo';
import { Avatar } from '@/components/Avatar';
import { visibleNav, type NavGroup } from './nav';

export type ActingAs = { name: string; roles: readonly string[]; scope: string };

export type SidebarProps = {
  permissions: ReadonlySet<string>;
  counts?: Readonly<Record<string, number>>;
  actingAs: ActingAs;
  onSignOut: () => void;
  variant?: 'full' | 'rail';
  /** Called after a nav link is followed (closes the mobile drawer). */
  onNavigate?: () => void;
  groups?: readonly NavGroup[];
};

/**
 * Primary navigation. Items render only for permissions the server granted
 * (deny by default). Active item = lime pill + aria-current="page" (NavLink).
 */
export function Sidebar({ permissions, counts = {}, actingAs, onSignOut, variant = 'full', onNavigate, groups }: SidebarProps) {
  const nav = visibleNav(permissions, groups);
  const rail = variant === 'rail';
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});
  const baseId = useId();

  return (
    <div className={cn('flex h-full flex-col bg-subtle', rail ? 'w-rail items-center px-2 py-6' : 'w-sidebar px-4 py-6')}>
      <div className={cn('mb-8 flex items-center', rail ? 'justify-center' : 'px-3')}>
        <FundlyLogo compact={rail} />
      </div>

      <nav aria-label="Main" className="flex-1 overflow-y-auto">
        {nav.map((group) => {
          const isCollapsed = !rail && collapsed[group.id] === true;
          const listId = `${baseId}-${group.id}`;
          return (
            <div key={group.id} className="mb-4">
              {rail ? (
                <h2 className="sr-only">{group.label}</h2>
              ) : group.id === 'work' ? (
                <h2 className="sr-only">{group.label}</h2>
              ) : (
                <h2>
                  <button
                    type="button"
                    aria-expanded={!isCollapsed}
                    aria-controls={listId}
                    onClick={() => setCollapsed((c) => ({ ...c, [group.id]: !isCollapsed }))}
                    className="flex min-h-target w-full items-center justify-between rounded-pill px-3 py-1 text-meta font-semibold uppercase tracking-eyebrow text-tertiary hover:text-primary"
                  >
                    {group.label}
                    <ChevronDown aria-hidden="true" className={cn('h-icon-sm w-icon-sm transition-transform duration-fast', isCollapsed && '-rotate-90')} />
                  </button>
                </h2>
              )}
              <ul id={listId} hidden={isCollapsed} className="mt-1 space-y-1">
                {group.items.map((item) => {
                  const Icon = item.icon;
                  const count = item.countKey ? counts[item.countKey] : undefined;
                  return (
                    <li key={item.id}>
                      <NavLink
                        to={item.to}
                        end={item.to === '/'}
                        onClick={onNavigate}
                        title={rail ? item.label : undefined}
                        className={({ isActive }) =>
                          cn(
                            'relative flex min-h-touch items-center gap-3 rounded-pill text-nav transition-colors duration-fast',
                            rail ? 'h-12 w-12 justify-center' : 'px-4',
                            isActive ? 'bg-selected font-semibold text-on-accent-fill' : 'text-secondary hover:bg-hover-nav hover:text-primary',
                          )
                        }
                      >
                        <Icon aria-hidden="true" className="h-icon-lg w-icon-lg shrink-0" />
                        <span className={cn(rail ? 'sr-only' : 'flex-1 truncate')}>{item.label}</span>
                        {count !== undefined && count > 0 && (
                          <span
                            className={cn(
                              'inline-flex min-w-[22px] items-center justify-center rounded-pill bg-danger-fill px-1.5 py-0.5 text-micro font-bold text-on-danger tabular',
                              rail && 'absolute -right-0.5 -top-0.5',
                            )}
                          >
                            {count > 99 ? '99+' : count}
                            <span className="sr-only"> items</span>
                          </span>
                        )}
                      </NavLink>
                    </li>
                  );
                })}
              </ul>
            </div>
          );
        })}
      </nav>

      {/* "Acting as" card (repurposed loan-ui promo card) */}
      {rail ? (
        <div className="mt-4 flex flex-col items-center gap-2">
          <Avatar name={actingAs.name} label={`Acting as ${actingAs.name}`} />
          <button
            type="button"
            onClick={onSignOut}
            aria-label="Sign out"
            title="Sign out"
            className="inline-flex h-10 w-10 items-center justify-center rounded-full text-secondary hover:bg-hover-nav hover:text-primary"
          >
            <LogOut aria-hidden="true" className="h-icon w-icon" />
          </button>
        </div>
      ) : (
        <section data-surface="brand" aria-label="Acting as" className="relative mt-4 overflow-hidden rounded-card bg-brand p-4 text-on-brand">
          <span aria-hidden="true" className="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-brand-raised" />
          <span aria-hidden="true" className="absolute right-6 -top-3 h-10 w-10 rounded-full bg-brand-raised" />
          <p className="relative text-micro font-semibold uppercase tracking-eyebrow text-on-brand-muted">Acting as</p>
          <p className="relative mt-1 truncate text-body font-semibold">{actingAs.name}</p>
          <ul className="relative mt-2 flex flex-wrap gap-1" aria-label="Roles">
            {actingAs.roles.length === 0 && <li className="text-meta text-on-brand-muted">No roles</li>}
            {actingAs.roles.map((r) => (
              <li key={r} className="rounded-pill bg-brand-raised px-2 py-0.5 text-micro font-medium text-on-brand">
                {r}
              </li>
            ))}
          </ul>
          <p className="relative mt-2 text-meta text-on-brand-muted">
            <span className="sr-only">Scope: </span>
            {actingAs.scope}
          </p>
          <button
            type="button"
            onClick={onSignOut}
            className="relative mt-4 inline-flex min-h-target items-center gap-2 rounded-pill bg-brand-contrast px-4 py-2 text-body-sm font-semibold text-on-accent-fill hover:bg-accent-fill-hover"
          >
            <LogOut aria-hidden="true" className="h-icon-sm w-icon-sm" />
            Sign out
          </button>
        </section>
      )}
    </div>
  );
}
