import { useEffect, useRef } from 'react';
import { Bell, Menu, Search } from 'lucide-react';
import { Avatar } from '@/components/Avatar';
import { IconButton } from '@/components/IconButton';
import { useToast } from '@/components/Toast';

export type TitleBarProps = {
  title: string;
  userName: string;
  userMeta?: string;
  onOpenNav: () => void;
  hasNotifications?: boolean;
};

/** Page title left; search, notifications and the signed-in user right (loan-ui header). */
export function TitleBar({ title, userName, userMeta, onOpenNav, hasNotifications = false }: TitleBarProps) {
  const searchRef = useRef<HTMLInputElement>(null);
  const toast = useToast();

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        searchRef.current?.focus();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  return (
    <header className="flex min-h-header flex-wrap items-center gap-3 px-4 py-4 md:px-gutter">
      <IconButton label="Open navigation" icon={<Menu className="h-icon-lg w-icon-lg" />} className="md:hidden" onClick={onOpenNav} />
      <h1 className="mr-auto truncate text-heading text-emphasis">{title}</h1>

      <form
        role="search"
        aria-label="Applications and customers"
        className="order-last w-full sm:order-none sm:w-auto"
        onSubmit={(e) => {
          e.preventDefault();
          toast.show('Search arrives with the application module (P1).', 'info');
        }}
      >
        <label htmlFor="global-search" className="sr-only">
          Search applications, customers
        </label>
        <div className="relative">
          <input
            ref={searchRef}
            id="global-search"
            type="search"
            placeholder="Search applications, customers…"
            className="h-12 w-full rounded-pill border border-transparent bg-neutral pl-5 pr-16 text-body text-primary placeholder:text-placeholder hover:border-control sm:w-search"
          />
          <span aria-hidden="true" className="pointer-events-none absolute right-12 top-1/2 hidden -translate-y-1/2 rounded-mark border border-strong px-1.5 text-micro text-tertiary lg:inline">
            ⌘K
          </span>
          <button type="submit" aria-label="Search" className="absolute right-1.5 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full text-secondary hover:bg-hover-nav">
            <Search aria-hidden="true" className="h-icon w-icon" />
          </button>
        </div>
      </form>

      <IconButton
        label={hasNotifications ? 'Notifications (unread)' : 'Notifications'}
        icon={<Bell className="h-icon w-icon" />}
        variant="soft"
        size="lg"
        dot={hasNotifications}
        className="bg-neutral"
        onClick={() => toast.show('Notifications arrive with the workflow module (P1).', 'info')}
      />
      <div className="flex items-center gap-3">
        <div className="hidden text-right sm:block">
          <p className="text-body font-semibold text-emphasis">{userName}</p>
          {userMeta && <p className="text-meta text-tertiary">{userMeta}</p>}
        </div>
        <Avatar name={userName} />
      </div>
    </header>
  );
}
