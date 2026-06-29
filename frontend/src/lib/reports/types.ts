import type { ColumnDef } from "@tanstack/react-table";

import type { ListParams, PaginationMeta } from "@/lib/api/types";

export interface ReportFilterDef {
  name: string;
  label: string;
  type: "select" | "text" | "date" | "relation";
  placeholder?: string;
  allLabel?: string;
  options?: Array<{ value: string; label: string }>;
  relation?: {
    resource: string;
    labelKey: string;
    params?: Record<string, string | number | boolean>;
  };
  required?: boolean;
}

export interface ReportGroupOption {
  value: string;
  label: string;
}

export interface ReportFetchResult {
  rows: Array<Record<string, unknown>>;
  pagination?: PaginationMeta;
  summary?: Record<string, unknown>;
}

export interface ReportDef {
  id: string;
  title: string;
  description: string;
  path: string;
  columns: ColumnDef<Record<string, unknown>, unknown>[];
  filters?: ReportFilterDef[];
  groupByOptions?: ReportGroupOption[];
  defaultGroupBy?: string;
  paginated?: boolean;
  pageSize?: number;
  permission?: string;
  fetchData: (params: ListParams) => Promise<ReportFetchResult>;
}

export type ExportColumn = {
  header: string;
  value: (row: Record<string, unknown>) => string;
};
