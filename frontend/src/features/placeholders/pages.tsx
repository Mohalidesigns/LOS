import { useParams } from 'react-router';
import { PlaceholderPage } from './PlaceholderPage';

export function InboxPage() {
  return <PlaceholderPage title="Inbox" phase="P1 (workflow & tasks)" anyOf={['application:view']} description="Your tasks, approvals and returned-for-rework items will appear here, ordered by SLA." />;
}
export function PipelinePage() {
  return <PlaceholderPage title="Pipeline" phase="P1 (applications)" anyOf={['application:view']} description="A board of every live application by canonical stage, with SLA and value per column." />;
}
export function ApplicationsPage() {
  return <PlaceholderPage title="Applications" phase="P1 (applications)" anyOf={['application:view']} description="Search, filter and open applications within your scope." />;
}
export function ApplicationDetailPage() {
  const { id = '' } = useParams();
  return <PlaceholderPage title={`Application ${id}`} phase="P1 (applications)" anyOf={['application:view']} description="The case view (stage tracker, documents, credit assessment and decision) will open here." />;
}
export function PartiesPage() {
  return <PlaceholderPage title="Customers" phase="P1 (parties & KYC)" anyOf={['party:manage']} description="Individuals and businesses, their KYC status and relationships." />;
}
export function ComplianceAlertsPage() {
  return <PlaceholderPage title="Compliance alerts" phase="P2 (screening)" anyOf={['screening:review']} description="Sanctions, PEP and adverse-media hits awaiting review and clearance." />;
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
