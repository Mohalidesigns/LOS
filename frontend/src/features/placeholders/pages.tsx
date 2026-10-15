import { PlaceholderPage } from './PlaceholderPage';

export function ProductsPage() {
  return <PlaceholderPage title="Products" phase="P1 (product configuration)" anyOf={['product:manage', 'config:read']} description="Versioned loan products with maker-checker activation." />;
}
export function UsersPage() {
  return <PlaceholderPage title="Users & roles" phase="P0 UI follow-up (API ready)" anyOf={['user:read']} description="Users, role assignments, scopes and segregation-of-duties conflicts." />;
}
