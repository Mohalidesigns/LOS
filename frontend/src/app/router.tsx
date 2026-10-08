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
      { path: 'pipeline', handle: { title: 'Pipeline' }, lazy: async () => ({ Component: (await placeholders()).PipelinePage }) },
      { path: 'applications', handle: { title: 'Applications' }, lazy: async () => ({ Component: (await placeholders()).ApplicationsPage }) },
      { path: 'applications/:id', handle: { title: 'Application' }, lazy: async () => ({ Component: (await placeholders()).ApplicationDetailPage }) },
      { path: 'parties', handle: { title: 'Customers' }, lazy: async () => ({ Component: (await placeholders()).PartiesPage }) },
      { path: 'compliance/alerts', handle: { title: 'Compliance alerts' }, lazy: async () => ({ Component: (await placeholders()).ComplianceAlertsPage }) },
      { path: 'config/products', handle: { title: 'Products' }, lazy: async () => ({ Component: (await placeholders()).ProductsPage }) },
      { path: 'admin/users', handle: { title: 'Users & roles' }, lazy: async () => ({ Component: (await placeholders()).UsersPage }) },
      { path: 'audit/events', handle: { title: 'Audit trail' }, lazy: async () => ({ Component: (await placeholders()).AuditPage }) },
      { path: '*', handle: { title: 'Not found' }, element: <NotFoundPage /> },
    ],
  },
]);
