"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { currencyColumn, dateColumn, linkColumn, nestedNameColumn, statusColumn } from "@/lib/resources/columns";

const basePath = "/dashboard/reconciliations";

export const reconciliationListColumns: ColumnDef<Record<string, unknown>>[] = [
  linkColumn("id", "ID", basePath, (row) => `#${row.id}`),
  nestedNameColumn("account", "Rekening"),
  dateColumn("period_start", "Awal Periode"),
  dateColumn("period_end", "Akhir Periode"),
  currencyColumn("statement_balance", "Saldo Rekening Koran"),
  currencyColumn("system_balance", "Saldo Sistem"),
  currencyColumn("difference", "Selisih"),
  statusColumn(),
];
