import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { creditApi } from '@/api/credit';
import type { Application } from '@/api/lending';
import { ApiProblem } from '@/api/problem';
import { ToastProvider } from '@/components';
import { BureauSection } from './BureauSection';

const app = {
  id: 'a1',
  status: 'assessment',
  primary_applicant: { party_id: 'p1', display_name: 'Adebayo Foods Limited' },
  applicants: [{ party_id: 'p1', display_name: 'Adebayo Foods Limited', role: 'primary' }],
} as unknown as Application;

function renderSection(permissions: string[]) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <ToastProvider>
        <MemoryRouter>
          <BureauSection app={app} permissions={new Set(permissions)} />
        </MemoryRouter>
      </ToastProvider>
    </QueryClientProvider>,
  );
}

describe('BureauSection', () => {
  it('shows the consent-blocked state with a link to the KYC consents panel', async () => {
    vi.spyOn(creditApi, 'bureauReports').mockResolvedValue([]);
    vi.spyOn(creditApi, 'pullBureau').mockRejectedValue(
      new ApiProblem({ type: 'urn:fundly:problem:bureau-consent-missing', title: 'Consent missing', status: 422, detail: 'No active credit_bureau consent.', code: 'bureau-consent-missing', extensions: { party_id: 'p1' } }),
    );
    renderSection(['bureau:pull']);
    await userEvent.click(await screen.findByRole('button', { name: /Pull bureau report/ }));
    expect(await screen.findByText('Bureau enquiry blocked: no valid bureau consent')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Applicant & KYC consents/ })).toHaveAttribute('href', '/applications/a1/kyc?party=p1#consents');
  });

  it('keeps the pull button inert with the reason when the permission is missing', async () => {
    vi.spyOn(creditApi, 'bureauReports').mockResolvedValue([]);
    const pull = vi.spyOn(creditApi, 'pullBureau');
    renderSection([]);
    const button = await screen.findByRole('button', { name: /Pull bureau report/ });
    expect(button).toHaveAttribute('aria-disabled', 'true');
    expect(button).toHaveAccessibleDescription('Requires the bureau:pull permission.');
    await userEvent.click(button);
    expect(pull).not.toHaveBeenCalled();
  });
});
