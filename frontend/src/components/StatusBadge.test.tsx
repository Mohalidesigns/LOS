import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { APPLICATION_STATUSES, STATUS_META, StatusBadge, statusMeta } from './StatusBadge';

describe('StatusBadge', () => {
  it('maps every canonical status to a label and tone', () => {
    for (const s of APPLICATION_STATUSES) {
      expect(STATUS_META[s].label.length).toBeGreaterThan(0);
    }
    expect(statusMeta('booked')).toEqual({ label: 'Booked', tone: 'success' });
    expect(statusMeta('declined')).toEqual({ label: 'Declined', tone: 'danger' });
    expect(statusMeta('approval')).toEqual({ label: 'In approval', tone: 'warning' });
    expect(statusMeta('kyc_screening')).toEqual({ label: 'KYC & screening', tone: 'info' });
    expect(statusMeta('withdrawn').tone).toBe('neutral');
  });

  it('falls back to a humanised neutral label for unknown statuses', () => {
    expect(statusMeta('some_new_state')).toEqual({ label: 'Some new state', tone: 'neutral' });
  });

  it('renders the label text with the tone classes (colour never alone)', () => {
    render(<StatusBadge status="conditions_precedent" />);
    const el = screen.getByText('Conditions precedent');
    expect(el).toHaveClass('text-warning', 'border-warning');
  });
});
