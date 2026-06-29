import type { ReactNode } from "react";

import { Skeleton } from "@/components/ui/skeleton";

export function TableSkeleton({ rows = 8 }: { rows?: number }) {
  return (
    <div className="space-y-3">
      <Skeleton className="h-10 w-full" />
      {Array.from({ length: rows }).map((_, index) => (
        <Skeleton key={index} className="h-12 w-full" />
      ))}
    </div>
  );
}

export function PageHeaderSkeleton() {
  return (
    <div className="space-y-2">
      <Skeleton className="h-8 w-48" />
      <Skeleton className="h-4 w-72" />
    </div>
  );
}

export function MetricCardsSkeleton() {
  return (
    <div className="grid grid-cols-1 gap-4 xl:grid-cols-4">
      {Array.from({ length: 4 }).map((_, index) => (
        <Skeleton key={index} className="h-32 w-full" />
      ))}
    </div>
  );
}

export function DashboardSkeleton() {
  return (
    <div className="@container/main flex flex-col gap-4 md:gap-6">
      <MetricCardsSkeleton />
      <Skeleton className="h-72 w-full" />
    </div>
  );
}

export function PageShellSkeleton({ children }: { children?: ReactNode }) {
  return (
    <div className="flex flex-col gap-6">
      <PageHeaderSkeleton />
      {children ?? <TableSkeleton />}
    </div>
  );
}
