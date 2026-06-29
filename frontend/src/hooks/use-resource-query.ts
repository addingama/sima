"use client";

import { useQuery } from "@tanstack/react-query";

import { apiGet } from "@/lib/api/client";
import type { ApiEnvelope, ListParams } from "@/lib/api/types";

export function useResourceQuery<T>(resource: string, params: ListParams = {}, enabled = true) {
  return useQuery({
    queryKey: [resource, params],
    enabled,
    queryFn: async () => {
      const response = await apiGet<T[]>(resource, params);

      return {
        rows: response.data,
        pagination: response.meta?.pagination,
        message: response.message,
      } satisfies {
        rows: T[];
        pagination: ApiEnvelope["meta"] extends infer M ? M extends { pagination?: infer P } ? P : undefined : undefined;
        message: string | null;
      };
    },
  });
}

export function useDetailQuery<T>(resource: string, id: string | number | null, enabled = true) {
  return useQuery({
    queryKey: [resource, id],
    enabled: enabled && id !== null,
    queryFn: async () => {
      const response = await apiGet<T>(`${resource}/${id}`);

      return response.data;
    },
  });
}

export function useDashboardQuery() {
  return useQuery({
    queryKey: ["dashboard"],
    queryFn: async () => {
      const response = await apiGet<import("@/lib/api/types").DashboardSummary>("/dashboard");

      return response.data;
    },
  });
}

export function useFundBalancesQuery() {
  return useQuery({
    queryKey: ["/reports/fund-balances"],
    queryFn: async () => {
      const response = await apiGet<import("@/lib/api/types").FundBalancesReport>("/reports/fund-balances");

      return response.data;
    },
  });
}

export function useReconciliationSummaryQuery() {
  return useQuery({
    queryKey: ["/reports/reconciliation-summary"],
    queryFn: async () => {
      const response = await apiGet<import("@/lib/api/types").ReconciliationSummary>(
        "/reports/reconciliation-summary",
      );

      return response.data;
    },
  });
}

export function useRecentLedgerQuery(limit = 8) {
  return useQuery({
    queryKey: ["/reports/ledger", "recent", limit],
    queryFn: async () => {
      const response = await apiGet<import("@/lib/api/types").LedgerEntryRow[]>("/reports/ledger", {
        page: 1,
        per_page: limit,
        sort: "created_at",
        direction: "desc",
      });

      return response.data;
    },
  });
}
