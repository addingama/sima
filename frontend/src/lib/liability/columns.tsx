"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { currencyColumn, dateColumn, linkColumn, nestedNameColumn, statusColumn } from "@/lib/resources/columns";

const basePath = "/dashboard/liabilities";

export const liabilityListColumns: ColumnDef<Record<string, unknown>>[] = [
  linkColumn("liability_number", "No. Liabilitas", basePath, (row) => String(row.liability_number ?? `#${row.id}`)),
  dateColumn("liability_date", "Tanggal"),
  { accessorKey: "creditor", header: "Kreditur" },
  nestedNameColumn("fund", "Dana"),
  currencyColumn("amount", "Nominal"),
  currencyColumn("amount_settled", "Terbayar"),
  statusColumn(),
];
