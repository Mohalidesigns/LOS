import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { Sidebar } from './Sidebar';
import { visibleNav } from './nav';

const actingAs = { name: 'Ada Admin', roles: ['Tenant Administrator'], scope: 'Whole institution' };

function renderSidebar(perms: string[], path = '/') {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Sidebar permissions={new Set(perms)} actingAs={actingAs} onSignOut={vi.fn()} counts={{ inbox: 14 }} />
    </MemoryRouter>,
  );
}

describe('nav visibility (deny by default)', () => {
  it('shows only the dashboard with no permissions', () => {
    const groups = visibleNav(new Set());
    expect(groups.map((g) => g.id)).toEqual(['work']);
    expect(groups[0]!.items.map((i) => i.id)).toEqual(['dashboard']);
  });

  it('application:view unlocks Inbox, Pipeline and Applications only', () => {
    renderSidebar(['application:view']);
    const nav = screen.getByRole('navigation', { name: 'Main' });
    for (const label of ['Dashboard', 'Inbox', 'Pipeline', 'Applications']) expect(within(nav).getByRole('link', { name: new RegExp(label) })).toBeInTheDocument();
    for (const label of ['Customers', 'Compliance alerts', 'Products', 'Users & roles', 'Audit trail']) expect(within(nav).queryByRole('link', { name: new RegExp(label) })).toBeNull();
  });

  it('maps admin, config, audit, party and screening permissions to their items', () => {
    renderSidebar(['user:read', 'config:read', 'audit:read', 'party:manage', 'screening:review']);
    const nav = screen.getByRole('navigation', { name: 'Main' });
    for (const label of ['Customers', 'Compliance alerts', 'Products', 'Users & roles', 'Audit trail']) expect(within(nav).getByRole('link', { name: new RegExp(label) })).toBeInTheDocument();
    expect(within(nav).queryByRole('link', { name: /Inbox/ })).toBeNull();
  });

  it('product:manage alone also shows Products', () => {
    expect(visibleNav(new Set(['product:manage'])).flatMap((g) => g.items.map((i) => i.id))).toContain('products');
  });

  it('marks the active item with aria-current="page" and shows the count badge', () => {
    renderSidebar(['application:view'], '/inbox');
    const link = screen.getByRole('link', { name: /Inbox/ });
    expect(link).toHaveAttribute('aria-current', 'page');
    expect(within(link).getByText('14')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Dashboard/ })).not.toHaveAttribute('aria-current');
  });

  it('shows who the user is acting as', () => {
    renderSidebar([]);
    const card = screen.getByRole('region', { name: 'Acting as' });
    expect(within(card).getByText('Ada Admin')).toBeInTheDocument();
    expect(within(card).getByText('Tenant Administrator')).toBeInTheDocument();
    expect(within(card).getByRole('button', { name: 'Sign out' })).toBeInTheDocument();
  });
});
