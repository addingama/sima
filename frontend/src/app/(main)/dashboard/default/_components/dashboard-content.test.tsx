import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type { SimaUser } from "@/lib/api/types";
import { useAuth } from "@/providers/auth-provider";

import { DashboardContent, usesGrantOfficerDashboard } from "./dashboard-content";

const { financialRender, grantRender } = vi.hoisted(() => ({
  financialRender: vi.fn(),
  grantRender: vi.fn(),
}));

vi.mock("@/providers/auth-provider", () => ({ useAuth: vi.fn() }));
vi.mock("./grant-officer-dashboard", () => ({
  GrantOfficerDashboard: () => {
    grantRender();
    return <div>Dashboard operasional bantuan</div>;
  },
}));

vi.mock("./account-balance-chart", () => ({
  AccountBalanceChart: () => {
    financialRender();
    return <div>Komponen keuangan</div>;
  },
}));
vi.mock("./cash-flow-chart", () => ({
  CashFlowChart: () => {
    financialRender();
    return <div>Komponen keuangan</div>;
  },
}));
vi.mock("./fund-balance-chart", () => ({
  FundBalanceChart: () => {
    financialRender();
    return <div>Komponen keuangan</div>;
  },
}));
vi.mock("./recent-activity", () => ({
  RecentActivity: () => {
    financialRender();
    return <div>Komponen keuangan</div>;
  },
}));
vi.mock("./reconciliation-summary-card", () => ({
  ReconciliationSummaryCard: () => {
    financialRender();
    return <div>Komponen keuangan</div>;
  },
}));
vi.mock("./sima-metric-cards", () => ({
  SimaMetricCards: () => {
    financialRender();
    return <div>Komponen keuangan</div>;
  },
}));

function user(roles: string[]): SimaUser {
  return {
    id: 10,
    name: "Petugas Lapangan",
    email: "petugas@sima.test",
    phone: null,
    is_active: true,
    roles,
    permissions: [],
  };
}

describe("DashboardContent", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("memilih dashboard bantuan hanya untuk akun khusus petugas bantuan", () => {
    expect(usesGrantOfficerDashboard(user(["petugas_bantuan"]))).toBe(true);
    expect(usesGrantOfficerDashboard(user(["bendahara"]))).toBe(false);
    expect(usesGrantOfficerDashboard(user(["petugas_bantuan", "bendahara"]))).toBe(false);
  });

  it("tidak merender komponen keuangan untuk petugas bantuan", () => {
    vi.mocked(useAuth).mockReturnValue({ user: user(["petugas_bantuan"]), isLoading: false } as ReturnType<
      typeof useAuth
    >);

    render(<DashboardContent />);

    expect(screen.getByText("Dashboard operasional bantuan")).toBeInTheDocument();
    expect(grantRender).toHaveBeenCalledOnce();
    expect(financialRender).not.toHaveBeenCalled();
  });
});
