"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ResourceListPage } from "@/components/sima/resource-list-page";
import { StatusBadge } from "@/components/sima/status-badge";

type ReconciliationRow = Record<string, unknown>;

const columns: ColumnDef<ReconciliationRow>[] = [
  { accessorKey: "id", header: "ID" },
  { accessorKey: "period_end", header: "Akhir Periode" },
  {
    accessorKey: "statement_balance",
    header: "Saldo Rekening Koran",
    cell: ({ row }) => <CurrencyDisplay value={row.original.statement_balance as string | number} />,
  },
  {
    accessorKey: "system_balance",
    header: "Saldo Sistem",
    cell: ({ row }) => <CurrencyDisplay value={row.original.system_balance as string | number} />,
  },
  {
    accessorKey: "status",
    header: "Status",
    cell: ({ row }) => <StatusBadge status={String(row.original.status ?? "")} />,
  },
];

export default function ReconciliationsPage() {
  return (
    <ResourceListPage
      title="Rekonsiliasi Bank"
      description="Rekonsiliasi saldo bank dengan sistem."
      resource="/bank-reconciliations"
      columns={columns}
      emptyMessage="Belum ada data rekonsiliasi."
    />
  );
}
