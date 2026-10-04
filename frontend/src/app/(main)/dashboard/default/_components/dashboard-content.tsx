"use client";

import { Suspense } from "react";

import { PageHeader } from "@/components/sima/page-header";
import { DashboardSkeleton } from "@/components/sima/skeletons";
import type { SimaUser } from "@/lib/api/types";
import { useAuth } from "@/providers/auth-provider";

import { AccountBalanceChart } from "./account-balance-chart";
import { CashFlowChart } from "./cash-flow-chart";
import { FundBalanceChart } from "./fund-balance-chart";
import { GrantOfficerDashboard } from "./grant-officer-dashboard";
import { RecentActivity } from "./recent-activity";
import { ReconciliationSummaryCard } from "./reconciliation-summary-card";
import { SimaMetricCards } from "./sima-metric-cards";

export function usesGrantOfficerDashboard(user: SimaUser | null): boolean {
  return user?.roles.length === 1 && user.roles.includes("petugas_bantuan");
}

export function DashboardContent() {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return <DashboardSkeleton />;
  }

  if (usesGrantOfficerDashboard(user)) {
    return <GrantOfficerDashboard />;
  }

  return (
    <div className="@container/main flex flex-col gap-4 md:gap-6">
      <PageHeader
        title="Dashboard"
        description="Ringkasan Amanah Ledger: saldo fisik (kas/bank), pembatas penggunaan (Dana Amanah), arus kas, dan antrian persetujuan. Data diambil dari buku besar — bukan angka statis."
      />

      <Suspense fallback={<DashboardSkeleton />}>
        <SimaMetricCards />
      </Suspense>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <CashFlowChart />
        <ReconciliationSummaryCard />
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <FundBalanceChart />
        <AccountBalanceChart />
      </div>

      <div className="grid grid-cols-1 gap-4">
        <RecentActivity />
      </div>
    </div>
  );
}
