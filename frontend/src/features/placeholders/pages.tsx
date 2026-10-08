import { PlaceholderPage } from './PlaceholderPage';

export function InboxPage() {
  return <PlaceholderPage title="Inbox" phase="P1 (workflow & tasks)" anyOf={['application:view']} description="Your tasks, approvals and returned-for-rework items will appear here, ordered by SLA." />;
}
export function ProductsPage() {
  return <PlaceholderPage title="Products" phase="P1 (product configuration)" anyOf={['product:manage', 'config:read']} description="Versioned loan products with maker-checker activation." />;
}
export function UsersPage() {
  return <PlaceholderPage title="Users & roles" phase="P0 UI follow-up (API ready)" anyOf={['user:read']} description="Users, role assignments, scopes and segregation-of-duties conflicts." />;
}
export function AuditPage() {
  return <PlaceholderPage title="Audit trail" phase="P0 UI follow-up (API ready)" anyOf={['audit:read']} description="Search the hash-chained audit trail and verify its integrity." />;
}
