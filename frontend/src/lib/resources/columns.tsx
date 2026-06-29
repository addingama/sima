"use client";

import type { ColumnDef } from "@tanstack/react-table";
import Link from "next/link";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { StatusBadge } from "@/components/sima/status-badge";
import { formatDate } from "@/lib/format/datetime";

export function linkColumn(
  accessor: string,
  header: string,
  basePath: string,
  label?: (row: Record<string, unknown>) => string,
): ColumnDef<Record<string, unknown>> {
  return {
    accessorKey: accessor,
    header,
    cell: ({ row }) => {
      const value = label ? label(row.original) : String(row.original[accessor] ?? "-");

      return (
        <Link href={`${basePath}/${row.original.id}`} className="font-medium hover:underline">
          {value}
        </Link>
      );
    },
  };
}

export function statusColumn(): ColumnDef<Record<string, unknown>> {
  return {
    accessorKey: "status",
    header: "Status",
    cell: ({ row }) => <StatusBadge status={String(row.original.status ?? "")} />,
  };
}

export function currencyColumn(accessor: string, header: string): ColumnDef<Record<string, unknown>> {
  return {
    accessorKey: accessor,
    header,
    cell: ({ row }) => <CurrencyDisplay value={row.original[accessor] as string | number} />,
  };
}

export function dateColumn(accessor: string, header: string): ColumnDef<Record<string, unknown>> {
  return {
    accessorKey: accessor,
    header,
    cell: ({ row }) => formatDate(String(row.original[accessor] ?? "")),
  };
}

export function activeColumn(): ColumnDef<Record<string, unknown>> {
  return {
    accessorKey: "is_active",
    header: "Status",
    cell: ({ row }) => (row.original.is_active ? "Aktif" : "Nonaktif"),
  };
}

export function nestedNameColumn(relationKey: string, header: string): ColumnDef<Record<string, unknown>> {
  return {
    id: `${relationKey}_name`,
    header,
    cell: ({ row }) => {
      const relation = row.original[relationKey] as { name?: string } | undefined;

      return relation?.name ?? "-";
    },
  };
}
