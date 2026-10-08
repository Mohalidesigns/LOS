/**
 * Dashboard data adapter.
 *
 * TODO(P1-RPT-01): replace with /api/v1/reports/operational
 * The P1 application/reporting endpoints do not exist yet. Everything in this
 * file returns typed MOCK data so the dashboard can be built and reviewed now.
 * Swap the bodies of `fetchDashboard` / `fetchNavCounts` for generated-client
 * calls (api.GET('/api/v1/reports/operational', …)) once the contract lands;
 * the types below are the shape the UI needs and should map 1:1.
 */
import { queryOptions } from '@tanstack/react-query';
import type { ApplicationStatus } from '@/components/StatusBadge';
import type { SlaState } from '@/components/SlaChip';

export type Money = { amount: string; currency: string };

export type RecentApplication = {
  id: string;
  reference: string;
  applicant: string;
  applicantType: 'Individual' | 'SME' | 'Corporate';
  product: string;
  amount: Money;
  status: ApplicationStatus;
  sla: { state: SlaState; remaining: string };
  submittedAt: string;
};

export type DashboardData = {
  /** MOCK marker: the UI shows a "sample data" note while true. */
  isSample: boolean;
  asOf: string;
  pipeline: { value: Money; applications: number; awaitingMe: number; breached: number };
  stats: {
    applicationsThisMonth: { value: number; change: number };
    approvalRate: { value: number; change: number };
    disbursedValue: { value: Money; change: number };
  };
  flow: { label: string; received: number; booked: number }[];
  byStage: { label: string; count: number; value: Money }[];
  recent: RecentApplication[];
  activity: { id: string; actor: string; action: string; at: string }[];
  sla: { onTrack: number; atRisk: number; breached: number; withinSlaRate: number; target: number };
};

export type NavCounts = { inbox: number; alerts: number };

const NGN = (amount: string): Money => ({ amount, currency: 'NGN' });

function hoursAgo(h: number, from = Date.now()): string {
  return new Date(from - h * 3_600_000).toISOString();
}

// TODO(P1-RPT-01): replace with /api/v1/reports/operational
export async function fetchDashboard(): Promise<DashboardData> {
  const now = Date.now();
  return Promise.resolve({
    isSample: true,
    asOf: new Date(now).toISOString(),
    pipeline: { value: NGN('4862500000.0000'), applications: 312, awaitingMe: 14, breached: 6 },
    stats: {
      applicationsThisMonth: { value: 148, change: 0.0178 },
      approvalRate: { value: 0.684, change: -0.0124 },
      disbursedValue: { value: NGN('1265000000.0000'), change: 0.0312 },
    },
    flow: [
      { label: 'Jan', received: 96, booked: 58 },
      { label: 'Feb', received: 88, booked: 61 },
      { label: 'Mar', received: 104, booked: 66 },
      { label: 'Apr', received: 121, booked: 70 },
      { label: 'May', received: 112, booked: 74 },
      { label: 'Jun', received: 131, booked: 82 },
      { label: 'Jul', received: 98, booked: 69 },
      { label: 'Aug', received: 117, booked: 77 },
      { label: 'Sep', received: 139, booked: 88 },
      { label: 'Oct', received: 148, booked: 64 },
    ],
    byStage: [
      { label: 'Assessment', count: 118, value: NGN('1945000000.0000') },
      { label: 'Approval', count: 62, value: NGN('1210000000.0000') },
      { label: 'Documentation', count: 54, value: NGN('802000000.0000') },
      { label: 'Offer & CPs', count: 46, value: NGN('598500000.0000') },
      { label: 'Disbursement', count: 32, value: NGN('307000000.0000') },
    ],
    recent: [
      {
        id: '01J9A1',
        reference: 'APP-2026-004812',
        applicant: 'Adaeze Okafor',
        applicantType: 'Individual',
        product: 'Salary advance',
        amount: NGN('1500000.0000'),
        status: 'assessment',
        sla: { state: 'on_track', remaining: '1d 4h left' },
        submittedAt: hoursAgo(3, now),
      },
      {
        id: '01J9A2',
        reference: 'APP-2026-004809',
        applicant: 'Kano Agro Processors Ltd',
        applicantType: 'SME',
        product: 'Working capital',
        amount: NGN('85000000.0000'),
        status: 'approval',
        sla: { state: 'at_risk', remaining: '3h left' },
        submittedAt: hoursAgo(20, now),
      },
      {
        id: '01J9A3',
        reference: 'APP-2026-004797',
        applicant: 'Lekki Logistics Plc',
        applicantType: 'Corporate',
        product: 'Asset finance',
        amount: NGN('420000000.0000'),
        status: 'conditions_precedent',
        sla: { state: 'breached', remaining: '6h over' },
        submittedAt: hoursAgo(54, now),
      },
      {
        id: '01J9A4',
        reference: 'APP-2026-004790',
        applicant: 'Chinedu Eze',
        applicantType: 'Individual',
        product: 'Personal loan',
        amount: NGN('3200000.0000'),
        status: 'approved',
        sla: { state: 'on_track', remaining: '2d left' },
        submittedAt: hoursAgo(70, now),
      },
      {
        id: '01J9A5',
        reference: 'APP-2026-004781',
        applicant: 'Abuja Fresh Foods Ltd',
        applicantType: 'SME',
        product: 'Invoice discounting',
        amount: { amount: '250000.0000', currency: 'USD' },
        status: 'declined',
        sla: { state: 'paused', remaining: 'closed' },
        submittedAt: hoursAgo(96, now),
      },
    ],
    activity: [
      { id: 'a1', actor: 'Funmi Adebayo', action: 'approved APP-2026-004790 at level 2', at: hoursAgo(0.5, now) },
      { id: 'a2', actor: 'Ibrahim Musa', action: 'recommended APP-2026-004809 for approval', at: hoursAgo(2, now) },
      { id: 'a3', actor: 'Ngozi Nwosu', action: 'cleared a screening alert for Lekki Logistics Plc', at: hoursAgo(4, now) },
      { id: 'a4', actor: 'Tunde Bakare', action: 'released ₦12.5M disbursement (checker)', at: hoursAgo(26, now) },
      { id: 'a5', actor: 'Amaka Obi', action: 'raised a documentation exception on APP-2026-004797', at: hoursAgo(30, now) },
    ],
    sla: { onTrack: 271, atRisk: 35, breached: 6, withinSlaRate: 0.868, target: 0.9 },
  });
}

// TODO(P1-RPT-01): replace with /api/v1/reports/operational (queue counts)
export async function fetchNavCounts(): Promise<NavCounts> {
  return Promise.resolve({ inbox: 14, alerts: 3 });
}

export const dashboardQuery = queryOptions({ queryKey: ['dashboard', 'operational'], queryFn: fetchDashboard, staleTime: 60_000 });
export const navCountsQuery = queryOptions({ queryKey: ['dashboard', 'nav-counts'], queryFn: fetchNavCounts, staleTime: 60_000 });
