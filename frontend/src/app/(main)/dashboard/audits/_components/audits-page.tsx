"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { ResourceListPage } from "@/components/sima/resource-list-page";

type AuditRow = Record<string, unknown> & {
  user?: { name?: string };
};

const columns: ColumnDef<AuditRow>[] = [
  { accessorKey: "id", header: "ID" },
  { accessorKey: "event", header: "Event" },
  { accessorKey: "auditable_type", header: "Tipe Entitas" },
  {
    id: "user_name",
    header: "Pengguna",
    cell: ({ row }) => row.original.user?.name ?? "-",
  },
  { accessorKey: "created_at", header: "Waktu" },
];

export default function AuditsPage() {
  return (
    <ResourceListPage
      title="Audit Trail"
      description="Riwayat audit perubahan data penting."
      resource="/audits"
      columns={columns}
      emptyMessage="Belum ada data audit."
    />
  );
}
