"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { StatusBadge } from "@/components/sima/status-badge";
import { formatDate, formatDateTime } from "@/lib/format/datetime";
import { reportExportHelpers } from "@/lib/reports/format-helpers";

export function textColumn(
  accessor: string,
  header: string,
  options?: { enableGrouping?: boolean },
): ColumnDef<Record<string, unknown>> {
  return {
    accessorKey: accessor,
    header,
    enableGrouping: options?.enableGrouping ?? true,
  };
}

export function currencyColumn(accessor: string, header: string): ColumnDef<Record<string, unknown>> {
  return {
    accessorKey: accessor,
    header,
    enableGrouping: false,
    cell: ({ row }) => <CurrencyDisplay value={row.original[accessor] as string | number} />,
    meta: {
      exportValue: (row: Record<string, unknown>) => reportExportHelpers.currency(row[accessor]),
    },
  };
}

export function dateColumn(accessor: string, header: string): ColumnDef<Record<string, unknown>> {
  return {
    accessorKey: accessor,
    header,
    enableGrouping: true,
    cell: ({ row }) => formatDate(String(row.original[accessor] ?? "")),
    meta: {
      exportValue: (row: Record<string, unknown>) => reportExportHelpers.date(row[accessor]),
    },
  };
}

export function datetimeColumn(accessor: string, header: string): ColumnDef<Record<string, unknown>> {
  return {
    accessorKey: accessor,
    header,
    enableGrouping: true,
    cell: ({ row }) => formatDateTime(String(row.original[accessor] ?? "")),
    meta: {
      exportValue: (row: Record<string, unknown>) => reportExportHelpers.datetime(row[accessor]),
    },
  };
}

export function statusColumn(accessor = "status"): ColumnDef<Record<string, unknown>> {
  return {
    accessorKey: accessor,
    header: "Status",
    enableGrouping: true,
    cell: ({ row }) => <StatusBadge status={String(row.original[accessor] ?? "")} />,
    meta: {
      exportValue: (row: Record<string, unknown>) => String(row[accessor] ?? ""),
    },
  };
}

export function nestedColumn(
  id: string,
  header: string,
  accessor: (row: Record<string, unknown>) => string,
): ColumnDef<Record<string, unknown>> {
  return {
    id,
    accessorKey: id,
    header,
    enableGrouping: true,
    accessorFn: accessor,
    cell: ({ row }) => accessor(row.original),
    meta: {
      exportValue: (row: Record<string, unknown>) => accessor(row),
    },
  };
}
