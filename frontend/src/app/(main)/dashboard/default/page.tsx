import { Suspense } from "react";

import { DashboardSkeleton } from "@/components/sima/skeletons";

import { SimaMetricCards } from "./_components/sima-metric-cards";
import { PerformanceOverview } from "./_components/performance-overview";

export default function Page() {
  return (
    <div className="@container/main flex flex-col gap-4 md:gap-6">
      <Suspense fallback={<DashboardSkeleton />}>
        <SimaMetricCards />
      </Suspense>
      <PerformanceOverview />
    </div>
  );
}
