import type { LucideIcon } from 'lucide-react';
import {
  Building2,
  FileStack,
  Inbox,
  LayoutGrid,
  Package,
  ScrollText,
  ShieldAlert,
  UsersRound,
  Workflow,
} from 'lucide-react';

export type NavItem = {
  id: string;
  label: string;
  to: string;
  icon: LucideIcon;
  /** Visible when the user holds ANY of these. Empty = every authenticated user. */
  anyOf: readonly string[];
  /** Key into the nav-count map for a badge. */
  countKey?: string;
};

export type NavGroup = { id: string; label: string; items: readonly NavItem[] };

/** Sidebar information architecture (design brief §Shell). Deny by default. */
export const NAV_GROUPS: readonly NavGroup[] = [
  {
    id: 'work',
    label: 'Work',
    items: [
      { id: 'dashboard', label: 'Dashboard', to: '/', icon: LayoutGrid, anyOf: [] },
      { id: 'inbox', label: 'Inbox', to: '/inbox', icon: Inbox, anyOf: ['application:view'], countKey: 'inbox' },
      { id: 'pipeline', label: 'Pipeline', to: '/pipeline', icon: Workflow, anyOf: ['application:view'] },
      { id: 'applications', label: 'Applications', to: '/applications', icon: FileStack, anyOf: ['application:view'] },
      { id: 'parties', label: 'Customers', to: '/parties', icon: Building2, anyOf: ['party:manage'] },
    ],
  },
  {
    id: 'compliance',
    label: 'Compliance',
    items: [{ id: 'alerts', label: 'Compliance alerts', to: '/compliance/alerts', icon: ShieldAlert, anyOf: ['screening:review'], countKey: 'alerts' }],
  },
  {
    id: 'configuration',
    label: 'Configuration',
    items: [{ id: 'products', label: 'Products', to: '/config/products', icon: Package, anyOf: ['product:manage', 'config:read'] }],
  },
  {
    id: 'administration',
    label: 'Administration',
    items: [{ id: 'users', label: 'Users & roles', to: '/admin/users', icon: UsersRound, anyOf: ['user:read'] }],
  },
  {
    id: 'assurance',
    label: 'Assurance',
    items: [{ id: 'audit', label: 'Audit trail', to: '/audit/events', icon: ScrollText, anyOf: ['audit:read'] }],
  },
];

export function canSee(item: Pick<NavItem, 'anyOf'>, permissions: ReadonlySet<string>): boolean {
  return item.anyOf.length === 0 || item.anyOf.some((p) => permissions.has(p));
}

/** Groups filtered to what the user may see; empty groups are dropped. */
export function visibleNav(permissions: ReadonlySet<string>, groups: readonly NavGroup[] = NAV_GROUPS): NavGroup[] {
  return groups.map((g) => ({ ...g, items: g.items.filter((i) => canSee(i, permissions)) })).filter((g) => g.items.length > 0);
}

/** Lookup for route guards: the permissions a path needs. */
export function requiredFor(path: string): readonly string[] | undefined {
  for (const g of NAV_GROUPS) for (const i of g.items) if (i.to === path) return i.anyOf;
  return undefined;
}

