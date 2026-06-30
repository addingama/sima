"use client";

import { useMemo, useState } from "react";

import { useQuery } from "@tanstack/react-query";
import type { ColumnDef } from "@tanstack/react-table";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { PaginatedDataTable } from "@/components/sima/paginated-data-table";
import { TableSkeleton } from "@/components/sima/skeletons";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { apiGet } from "@/lib/api/client";

type LedgerRow = Record<string, unknown>;

const columns: ColumnDef<LedgerRow>[] = [
  { accessorKey: "transaction_type", header: "Tipe Transaksi" },
  {
    accessorKey: "debit",
    header: "Debit",
    cell: ({ row }) => <CurrencyDisplay value={row.original.debit as string | number} />,
  },
  {
    accessorKey: "credit",
    header: "Kredit",
    cell: ({ row }) => <CurrencyDisplay value={row.original.credit as string | number} />,
  },
  { accessorKey: "reference", header: "Referensi" },
  { accessorKey: "created_at", header: "Waktu" },
];

export default function LedgerPage() {
  const [pageIndex, setPageIndex] = useState(0);
  const pageSize = 50;

  const params = useMemo(
    () => ({
      page: pageIndex + 1,
      per_page: pageSize,
    }),
    [pageIndex],
  );

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ["/reports/ledger", params],
    queryFn: async () => {
      const response = await apiGet<LedgerRow[]>("/reports/ledger", params);

      return {
        rows: response.data,
        pagination: response.meta?.pagination,
      };
    },
  });

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Buku Besar" description="Laporan buku besar Amanah Ledger." />

      <Card>
        <CardHeader>
          <CardTitle>Daftar Entri Ledger</CardTitle>
        </CardHeader>
        <CardContent>
          {isError ? (
            <ErrorState onRetry={() => refetch()} />
          ) : isLoading ? (
            <TableSkeleton />
          ) : (
            <PaginatedDataTable
              columns={columns}
              data={data?.rows ?? []}
              pagination={data?.pagination}
              pageIndex={pageIndex}
              pageSize={pageSize}
              onPaginationChange={(pagination) => setPageIndex(pagination.pageIndex)}
              emptyMessage="Tidak ada entri ledger."
            />
          )}
        </CardContent>
      </Card>
    </div>
  );
}
