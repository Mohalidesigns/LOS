import { Suspense, useEffect, useRef, useState } from 'react';
import { Outlet, useMatches, useNavigate, useNavigation } from 'react-router';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { X } from 'lucide-react';
import { authApi } from '@/api/auth';
import { IconButton } from '@/components/IconButton';
import { Skeleton } from '@/components/Skeleton';
import { useToast } from '@/components/Toast';
import { StepUpProvider } from '@/features/auth/StepUpProvider';
import { describeScope, roleLabel, useSession } from '@/features/auth/session';
import { navCountsQuery } from '@/features/dashboard/api';
import { Sidebar } from '@/features/navigation/Sidebar';
import { TitleBar } from './TitleBar';

type Handle = { title?: string };

function usePageTitle(): string {
  const matches = useMatches();
  for (let i = matches.length - 1; i >= 0; i--) {
    const h = matches[i]?.handle as Handle | undefined;
    if (h?.title) return h.title;
  }
  return 'Fundly LOS';
}

export function PageFallback() {
  return (
    <div className="space-y-4" aria-busy="true">
      <span className="sr-only" role="status">
        Loading page
      </span>
      <Skeleton className="h-[10rem] w-full rounded-card" />
      <Skeleton className="h-[16rem] w-full rounded-card" />
    </div>
  );
}

/** Shell: sidebar (full ≥1024, icon rail 768–1023, drawer <768), title bar, rounded content panel. */
export function AuthenticatedLayout() {
  const { me, access, permissions } = useSession();
  const title = usePageTitle();
  const navigate = useNavigate();
  const navigation = useNavigation();
  const qc = useQueryClient();
  const toast = useToast();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const drawerRef = useRef<HTMLDialogElement>(null);
  const counts = useQuery(navCountsQuery).data;
  const mainRef = useRef<HTMLElement>(null);

  useEffect(() => {
    document.title = `${title} · Fundly LOS`;
  }, [title]);

  useEffect(() => {
    const d = drawerRef.current;
    if (!d) return;
    if (drawerOpen && !d.open) d.showModal();
    if (!drawerOpen && d.open) d.close();
  }, [drawerOpen]);

  const signOut = async () => {
    try {
      await authApi.logout();
    } catch {
      toast.show('Sign-out could not reach the server; your local session was cleared.', 'danger');
    }
    qc.clear();
    await navigate('/login?reason=signed-out', { replace: true });
  };

  const actingAs = {
    name: me.name,
    roles: me.roles.map(roleLabel),
    scope: describeScope(access),
  };
  const sidebarProps = { permissions, counts, actingAs, onSignOut: () => void signOut() };

  return (
    <StepUpProvider>
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-tooltip focus:rounded-pill focus:bg-accent focus:px-4 focus:py-2 focus:text-on-accent"
        onClick={(e) => {
          e.preventDefault();
          mainRef.current?.focus();
        }}
      >
        Skip to main content
      </a>
      <div className="flex min-h-screen bg-subtle">
        <aside className="sticky top-0 hidden h-screen shrink-0 lg:block">
          <Sidebar {...sidebarProps} />
        </aside>
        <aside className="sticky top-0 hidden h-screen shrink-0 md:block lg:hidden">
          <Sidebar {...sidebarProps} variant="rail" />
        </aside>

        {/* Backdrop click closes; keyboard users press Escape (native <dialog>). */}
        {/* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-noninteractive-element-interactions */}
        <dialog
          ref={drawerRef}
          aria-label="Navigation"
          onClose={() => setDrawerOpen(false)}
          onClick={(e) => {
            if (e.target === e.currentTarget) setDrawerOpen(false);
          }}
          className="m-0 h-screen max-h-screen w-drawer max-w-[85vw] border-0 bg-subtle p-0 shadow-drawer backdrop:bg-overlay"
        >
          {drawerOpen && (
            <div className="relative h-full">
              <div className="absolute right-3 top-5 z-sticky">
                <IconButton label="Close navigation" icon={<X className="h-icon w-icon" />} onClick={() => setDrawerOpen(false)} />
              </div>
              <Sidebar {...sidebarProps} onNavigate={() => setDrawerOpen(false)} />
            </div>
          )}
        </dialog>

        <div className="flex min-w-0 flex-1 flex-col bg-panel md:rounded-tl-panel">
          <TitleBar
            title={title}
            userName={me.name}
            userMeta={actingAs.roles[0]}
            onOpenNav={() => setDrawerOpen(true)}
            hasNotifications={permissions.has('application:view') && (counts?.inbox ?? 0) > 0}
          />
          <main id="main" ref={mainRef} tabIndex={-1} data-scroll-container className="flex-1 px-4 pb-10 md:px-gutter" aria-busy={navigation.state === 'loading' || undefined}>
            <Suspense fallback={<PageFallback />}>
              <Outlet />
            </Suspense>
          </main>
        </div>
      </div>
    </StepUpProvider>
  );
}

export default AuthenticatedLayout;
