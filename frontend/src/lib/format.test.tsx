import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { formatDateTime, formatMoney } from './format';
import { MoneyText } from '@/components/MoneyText';

const norm = (s: string) => s.replace(/\u00a0/g, ' ');

describe('formatMoney (en-NG)', () => {
  it('formats NGN decimal strings exactly with ₦', () => {
    expect(formatMoney('25000000.1234', 'NGN')).toBe('₦25,000,000.12');
    expect(formatMoney('0.0000')).toBe('₦0.00');
    expect(formatMoney('-1500.5000')).toBe('-₦1,500.50');
  });

  it('keeps precision beyond float range', () => {
    expect(formatMoney('9007199254740993.0000')).toBe('₦9,007,199,254,740,993.00');
  });

  it('compact form shows one decimal', () => {
    expect(formatMoney('25000000.0000', 'NGN', { compact: true })).toBe('₦25.0M');
    expect(formatMoney('1265000000.0000', 'NGN', { compact: true })).toBe('₦1.3B');
    expect(formatMoney('950.0000', 'NGN', { compact: true })).toBe('₦950');
  });

  it('shows the ISO code for non-NGN currencies', () => {
    expect(norm(formatMoney('1200.5000', 'USD'))).toBe('USD 1,200.50');
    expect(norm(formatMoney('250000.0000', 'usd', { compact: true }))).toBe('USD 250.0K');
  });

  it('rejects non-decimal input', () => {
    expect(() => formatMoney('12,00')).toThrow(RangeError);
  });
});

describe('MoneyText', () => {
  it('renders compact text with the full amount for assistive tech', () => {
    render(<MoneyText amount="25000000.0000" compact />);
    expect(screen.getByText('₦25.0M')).toHaveAttribute('aria-hidden', 'true');
    expect(screen.getByText('₦25,000,000.00')).toHaveClass('sr-only');
  });
});

describe('formatDateTime (Africa/Lagos)', () => {
  it('renders in WAT (UTC+1)', () => {
    expect(formatDateTime('2026-10-08T12:00:00Z', 'time')).toBe('13:00');
    expect(formatDateTime('2026-10-08T23:30:00Z', 'date')).toBe('09 Oct 2026');
  });

  it('labels Today / Yesterday by Lagos calendar day', () => {
    const now = new Date('2026-10-08T10:00:00Z');
    expect(formatDateTime('2026-10-08T00:30:00Z', 'relative-day', now)).toBe('Today');
    expect(formatDateTime('2026-10-07T12:00:00Z', 'relative-day', now)).toBe('Yesterday');
    expect(formatDateTime('2026-10-01T12:00:00Z', 'relative-day', now)).toBe('01 Oct 2026');
  });
});
