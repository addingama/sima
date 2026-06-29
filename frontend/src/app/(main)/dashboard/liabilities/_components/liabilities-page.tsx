"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ResourceListPage } from "@/components/sima/resource-list-page";
import { StatusBadge } from "@/components/sima/status-badge";

type LiabilityRow = Record<string, unknown>;

const columns: ColumnDef<LiabilityRow>[] = [
  { accessorKey: "liability_number", header: "No. Liabilitas" },
  { accessorKey: "creditor", header: "Kreditur" },
  {
    accessorKey: "amount",
    header: "Nominal",
    cell: ({ row }) => <CurrencyDisplay value={row.original.amount as string | number} />,
  },
  {
    accessorKey: "status",
    header: "Status",
    cell: ({ row }) => <StatusBadge status={String(row.original.status ?? "")} />,
  },
];

export default function LiabilitiesPage() {
  return (
    <ResourceListPage
      title="Liabilitas Operasional"
      description="Kewajiban operasional yang belum diselesaikan."
      resource="/liabilities"
      columns={columns}
      emptyMessage="Belum ada data liabilitas."
    />
  );
}
