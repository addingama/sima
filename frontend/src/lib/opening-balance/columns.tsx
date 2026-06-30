"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { currencyColumn, dateColumn, linkColumn } from "@/lib/resources/columns";

const basePath = "/dashboard/opening-balances";

export const openingBalanceListColumns: ColumnDef<Record<string, unknown>>[] = [
  linkColumn("batch_number", "No. Batch", basePath, (row) => String(row.batch_number ?? `#${row.id}`)),
  dateColumn("opening_date", "Tanggal Cutover"),
  currencyColumn("total_amount", "Total"),
  {
    accessorKey: "reference",
    header: "Referensi",
    cell: ({ row }) => String(row.original.reference ?? "-"),
  },
  {
    accessorKey: "posted_at",
    header: "Diposting",
    cell: ({ row }) => {
      const value = row.original.posted_at;

      if (!value) {
        return "-";
      }

      return new Intl.DateTimeFormat("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      }).format(new Date(String(value)));
    },
  },
];
