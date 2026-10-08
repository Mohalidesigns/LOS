import { createBrowserRouter, redirect, type LoaderFunctionArgs } from 'react-router';
import { isApiProblem } from '@/api/problem';
import { loadSession } from '@/features/auth/session';
import { queryClient } from './queryClient';
import { BootFallback, RouteError, NotFoundPage } from './RouteError';

/** Guard for every authenticated route: /me + effective-access must load, else → /login. */
export async function protectedLoader({ request }: LoaderFunctionArgs) {
  try {
    await loadSession(queryClient);
    return null;
  } catch (e) {
    if (isApiProblem(e) && e.status === 401) {
      const url = new URL(request.url);
      const next = url.pathname + url.search;
      return redirect(next === '/' ? '/login' : `/login?next=${encodeURIComponent(next)}`);
    }
    throw e;
  }
}

const placeholders = () => import('@/features/placeholders/pages');
const applicationsList = () => import('@/features/applications/ApplicationsListPage');
const parties = () => import('@/features/parties/PartiesPage');

export const router = createBrowserRouter([
  {
    path: '/login',
    HydrateFallback: BootFallback,
    handle: { title: 'Sign in' },
    lazy: async () => ({ Component: (await import('@/features/auth/LoginPage')).default }),
    errorElement: <RouteError />,
  },
  {
    path: '/',
    loader: protectedLoader,
    HydrateFallback: BootFallback,
    // The session is cached in TanStack Query; child navigations need not re-run the guard.
    shouldRevalidate: () => false,
    lazy: async () => ({ Component: (await import('./AuthenticatedLayout')).default }),
    errorElement: <RouteError />,
    children: [
      { index: true, handle: { title: 'Dashboard' }, lazy: async () => ({ Component: (await import('@/features/dashboard/DashboardPage')).default }) },
      { path: 'inbox', handle: { title: 'Inbox' }, lazy: async () => ({ Component: (await placeholders()).InboxPage }) },
      { path: 'pipeline', handle: { title: 'Pipeline' }, lazy: async () => ({ Component: (await applicationsList()).PipelinePage }) },
      { path: 'applications', handle: { title: 'Applications' }, lazy: async () => ({ Component: (await applicationsList()).ApplicationsPage }) },
      { path: 'applications/new', handle: { title: 'New application' }, lazy: async () => ({ Component: (await import('@/features/applications/wizard/NewApplicationPage')).default }) },
      {
        path: 'applications/:id',
        handle: { title: 'Application' },
        lazy: async () => ({ Component: (await import('@/features/applications/case/CasePage')).default }),
        children: [
          { index: true, handle: { title: 'Application · Summary' }, lazy: async () => ({ Component: (await import('@/features/applications/case/SummaryTab')).SummaryTab }) },
          { path: 'kyc', handle: { title: 'Application · Applicant & KYC' }, lazy: async () => ({ Component: (await import('@/features/applications/case/KycTab')).KycTab }) },
          { path: 'documents', handle: { title: 'Application · Documents' }, lazy: async () => ({ Component: (await import('@/features/applications/case/DocumentsTab')).DocumentsTab }) },
          { path: 'timeline', handle: { title: 'Application · Timeline' }, lazy: async () => ({ Component: (await import('@/features/applications/case/TimelineTab')).TimelineTab }) },
        ],
      },
      { path: 'parties', handle: { title: 'Customers' }, lazy: async () => ({ Component: (await parties()).PartiesPage }) },
      { path: 'parties/:id', handle: { title: 'Customer' }, lazy: async () => ({ Component: (await parties()).PartyDetailPage }) },
      { path: 'compliance/alerts', handle: { title: 'Compliance alerts' }, lazy: async () => ({ Component: (await import('@/features/compliance/AlertsPage')).default }) },
      { path: 'config/products', handle: { title: 'Products' }, lazy: async () => ({ Component: (await placeholders()).ProductsPage }) },
      { path: 'admin/users', handle: { title: 'Users & roles' }, lazy: async () => ({ Component: (await placeholders()).UsersPage }) },
      { path: 'audit/events', handle: { title: 'Audit trail' }, lazy: async () => ({ Component: (await placeholders()).AuditPage }) },
      { path: '*', handle: { title: 'Not found' }, element: <NotFoundPage /> },
    ],
  },
]);
