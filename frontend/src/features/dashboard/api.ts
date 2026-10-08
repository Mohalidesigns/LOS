/**
 * Dashboard data adapter.
 *
 * TODO(P1-RPT-01): replace with /api/v1/reports/operational
 * Recent applications and "Pipeline by stage" are already live
 * (listApplications / applicationStats in DashboardPage). The remaining
 * reporting endpoints do not exist yet. Everything in this
 * file returns typed MOCK data so the dashboard can be built and reviewed now.
 * Swap the bodies of `fetchDashboard` / `fetchNavCounts` for generated-client
 * calls (api.GET('/api/v1/reports/operational', …)) once the contract lands;
 * the types below are the shape the UI needs and should map 1:1.
 */
import { queryOptions } from '@tanstack/react-query';

export type Money = { amount: string; currency: string };

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
