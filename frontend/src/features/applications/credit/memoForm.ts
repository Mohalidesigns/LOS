/**
 * Credit memo form (SCR-CRD-04): Zod schema mirroring the backend rules
 * (narrative >= 30 chars; a counter-offer needs amount and tenor), defaults
 * and the mapping to the saveCreditMemo body. Kept apart from domain.ts so
 * zod loads only with the Credit tab.
 */
import { z } from 'zod';
import type { CreditMemo, Decision, MemoRecommendation, SaveMemoBody } from '@/api/credit';

const AMOUNT_RE = /^\d{1,15}(\.\d{1,2})?$/;

export const memoSchema = z
  .object({
    narrative: z.string().trim().min(30, 'Write at least a few sentences of analysis (30 characters or more).').max(20000, 'Keep the narrative under 20,000 characters.'),
    recommendation: z.enum(['approve', 'decline', 'counter_offer'], { errorMap: () => ({ message: 'Choose a recommendation.' }) }),
    recommended_amount: z
      .string()
      .trim()
      .refine((v) => v === '' || AMOUNT_RE.test(v.replace(/,/g, '')), 'Enter an amount in naira, for example 10000000 or 10000000.00.'),
    recommended_tenor_months: z
      .string()
      .trim()
      .refine((v) => v === '' || (/^\d+$/.test(v) && Number(v) >= 1 && Number(v) <= 360), 'Enter a tenor between 1 and 360 months.'),
    conditions: z.array(z.object({ text: z.string().trim().max(500, 'Keep each condition under 500 characters.') })).max(30, 'Up to 30 conditions.'),
  })
  .superRefine((v, ctx) => {
    if (v.recommendation === 'counter_offer') {
      if (v.recommended_amount === '') ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['recommended_amount'], message: 'A counter-offer needs the recommended amount.' });
      if (v.recommended_tenor_months === '') ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['recommended_tenor_months'], message: 'A counter-offer needs the recommended tenor.' });
    }
  });

export type MemoFormValues = z.infer<typeof memoSchema>;

export function memoDefaults(memo: CreditMemo | null, decision: Decision | null): MemoFormValues {
  if (memo && decision && memo.decision_id !== decision.id) {
    // A newer decision exists: keep the analyst's words, take the terms from the new decision.
    return { ...memoDefaults(null, decision), narrative: memo.narrative, conditions: memo.conditions.map((text) => ({ text })) };
  }
  if (memo) {
    return {
      narrative: memo.narrative,
      recommendation: memo.recommendation,
      recommended_amount: memo.recommended_amount ? trimAmount(memo.recommended_amount.amount) : '',
      recommended_tenor_months: memo.recommended_tenor_months ? String(memo.recommended_tenor_months) : '',
      conditions: memo.conditions.map((text) => ({ text })),
    };
  }
  const rec: MemoRecommendation = decision?.outcome === 'counter_offer' ? 'counter_offer' : decision?.outcome === 'decline' ? 'decline' : 'approve';
  return {
    narrative: '',
    recommendation: rec,
    recommended_amount: decision ? trimAmount(decision.recommended_terms.amount.amount) : '',
    recommended_tenor_months: decision ? String(decision.recommended_terms.tenor_months) : '',
    conditions: [],
  };
}

/** "12500000.0000" → "12500000.00" (the form shows 2 decimals; the API stores scale 4). */
export function trimAmount(a: string): string {
  const n = Number(a);
  return Number.isFinite(n) ? n.toFixed(2) : a;
}

export function toSaveBody(v: MemoFormValues): SaveMemoBody {
  return {
    narrative: v.narrative.trim(),
    recommendation: v.recommendation,
    recommended_amount: v.recommended_amount === '' ? null : v.recommended_amount.replace(/,/g, ''),
    recommended_tenor_months: v.recommended_tenor_months === '' ? null : Number(v.recommended_tenor_months),
    conditions: v.conditions.map((c) => c.text.trim()).filter((t) => t.length > 0),
  };
}

export const RECOMMENDATION_LABEL: Record<MemoRecommendation, string> = {
  approve: 'Approve',
  counter_offer: 'Counter-offer',
  decline: 'Decline',
};
