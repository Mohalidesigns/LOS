/**
 * Pure handover logic: reading the package content safely (it is JSON the
 * server hashed, typed as an open object), delivery status presentation and
 * the endpoint form rules (mirroring the API: https outside localhost).
 */
import type { WebhookDelivery } from '@/api/handover';
import type { Tone } from '@/components/StatusBadge';

export const HANDOVER_PERM = { acknowledge: 'handover:acknowledge', webhookManage: 'webhook:manage', integrationRead: 'integration:read' } as const;

export const DELIVERY_META: Record<WebhookDelivery['status'], { label: string; tone: Tone }> = {
  pending: { label: 'Pending', tone: 'info' },
  delivered: { label: 'Delivered', tone: 'success' },
  failing: { label: 'Failing · retrying', tone: 'danger' },
  skipped: { label: 'Skipped (endpoint disabled)', tone: 'neutral' },
};

export type PackageDocument = { document_id: string; version_id: string; type: string; title: string; filename: string; sha256: string };
export type PackageCondition = { condition_id: string; description: string; due_date: string | null };
export type PackageInsurance = { fee_code: string; cover: string; premium: { amount: string; currency: string }; cover_until: string };
export type PackageView = {
  loanAccountNo: string | null;
  maturityDate: string | null;
  documents: PackageDocument[];
  conditions: PackageCondition[];
  insurance: PackageInsurance[];
  collateralNote: string | null;
};

function rec(v: unknown): Record<string, unknown> {
  return typeof v === 'object' && v !== null && !Array.isArray(v) ? (v as Record<string, unknown>) : {};
}
function list(v: unknown): Record<string, unknown>[] {
  return Array.isArray(v) ? v.map(rec) : [];
}
function str(v: unknown): string | null {
  return typeof v === 'string' ? v : null;
}

/** The parts of the package content the screen shows, defensively typed. */
export function packageView(content: unknown): PackageView {
  const c = rec(content);
  const facility = rec(c.facility);
  return {
    loanAccountNo: str(facility.loan_account_no),
    maturityDate: str(facility.maturity_date),
    documents: list(c.documents).map((d) => ({
      document_id: str(d.document_id) ?? '',
      version_id: str(d.version_id) ?? '',
      type: str(d.type) ?? '',
      title: str(d.title) ?? '',
      filename: str(d.filename) ?? '',
      sha256: str(d.sha256) ?? '',
    })),
    conditions: list(c.conditions_subsequent).map((x) => ({ condition_id: str(x.condition_id) ?? '', description: str(x.description) ?? '', due_date: str(x.due_date) })),
    insurance: list(c.insurance).map((x) => {
      const p = rec(x.premium);
      return { fee_code: str(x.fee_code) ?? '', cover: str(x.cover) ?? '', premium: { amount: str(p.amount) ?? '0', currency: str(p.currency) ?? 'NGN' }, cover_until: str(x.cover_until) ?? '' };
    }),
    collateralNote: str(rec(c.notes).collateral),
  };
}

const EVENT = /^(\*|[a-z_]+\.[a-z_]+)$/;

export function endpointErrors(f: { name: string; url: string; events: string }): { name?: string; url?: string; events?: string } {
  const e: { name?: string; url?: string; events?: string } = {};
  if (f.name.trim() === '') e.name = 'Give the endpoint a name.';
  let host = '';
  try {
    const u = new URL(f.url.trim());
    host = u.hostname;
    const local = host === 'localhost' || host === '127.0.0.1' || host.endsWith('.test') || host.endsWith('.localhost');
    if (u.protocol !== 'https:' && !(u.protocol === 'http:' && local)) e.url = 'Webhook endpoints must use https.';
  } catch {
    e.url = 'Enter a full URL, e.g. https://lms.bank.example/hooks.';
  }
  const events = parseEvents(f.events);
  if (events.length === 0 || events.some((x) => !EVENT.test(x))) e.events = 'List event types such as facility.created, separated by commas (or * for all).';
  return e;
}

export function parseEvents(raw: string): string[] {
  return [...new Set(raw.split(/[\s,]+/).map((x) => x.trim()).filter(Boolean))];
}
