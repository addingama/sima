import { Suspense } from "react";

import { PageHeader } from "@/components/sima/page-header";
import { DashboardSkeleton } from "@/components/sima/skeletons";

import { AccountBalanceChart } from "./_components/account-balance-chart";
import { CashFlowChart } from "./_components/cash-flow-chart";
import { FundBalanceChart } from "./_components/fund-balance-chart";
import { RecentActivity } from "./_components/recent-activity";
import { ReconciliationSummaryCard } from "./_components/reconciliation-summary-card";
import { SimaMetricCards } from "./_components/sima-metric-cards";

export default function Page() {
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
