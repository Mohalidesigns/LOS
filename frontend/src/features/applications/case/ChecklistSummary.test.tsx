import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ChecklistSummary } from './DocumentsTab';

describe('ChecklistSummary', () => {
  it('renders "3 of 5 mandatory items satisfied" with progress and the outstanding list', () => {
    render(<ChecklistSummary summary={{ mandatory_total: 5, mandatory_satisfied: 3, outstanding: ['Audited financial statements', 'Government ID'], complete: false }} />);
    expect(screen.getByText('3 of 5 mandatory items satisfied')).toBeInTheDocument();
    expect(screen.getByText('2 outstanding')).toBeInTheDocument();
    expect(screen.getByText('Outstanding: Audited financial statements, Government ID')).toBeInTheDocument();
    const bar = screen.getByRole('progressbar', { name: 'Mandatory documents satisfied' });
    expect(bar).toHaveAttribute('aria-valuenow', '60');
    expect(bar).toHaveAttribute('aria-valuetext', '3 of 5 mandatory items satisfied');
  });

  it('shows the complete state with singular wording', () => {
    render(<ChecklistSummary summary={{ mandatory_total: 1, mandatory_satisfied: 1, outstanding: [], complete: true }} />);
    expect(screen.getByText('1 of 1 mandatory item satisfied')).toBeInTheDocument();
    expect(screen.getByText('Checklist complete')).toBeInTheDocument();
    expect(screen.queryByText(/Outstanding:/)).not.toBeInTheDocument();
  });
});
