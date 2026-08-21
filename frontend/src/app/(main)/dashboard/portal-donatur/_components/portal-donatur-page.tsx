"use client";

import { useMemo, useState } from "react";

import { useQuery } from "@tanstack/react-query";
import type { ColumnDef, PaginationState } from "@tanstack/react-table";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { PaginatedDataTable } from "@/components/sima/paginated-data-table";
import { PageShellSkeleton } from "@/components/sima/skeletons";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { useResourceQuery } from "@/hooks/use-resource-query";
import { ApiError, apiGet } from "@/lib/api/client";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDate } from "@/lib/format/datetime";
import { useAuth } from "@/providers/auth-provider";

type PortalDonor = {
  id: number;
  code: string;
  name: string;
  type?: string;
  email?: string | null;
  phone?: string | null;
};

type PortalSummary = {
  donor: PortalDonor;
  total_donasi: string;
  jumlah_transaksi: number;
};

type PortalDonation = {
  id: number;
  receipt_number: string;
  receipt_date: string;
  channel?: string;
  amount: string;
  description?: string | null;
  account?: { name?: string };
  allocations?: Array<{
    amount?: string;
    fund?: { name?: string };
    program?: { name?: string };
  }>;
};

const donationColumns: ColumnDef<PortalDonation>[] = [
  {
    accessorKey: "receipt_number",
    header: "No. Penerimaan",
  },
  {
    accessorKey: "receipt_date",
    header: "Tanggal",
    cell: ({ row }) => formatDate(row.original.receipt_date),
  },
  {
    accessorKey: "channel",
    header: "Kanal",
    cell: ({ row }) => row.original.channel ?? "-",
  },
  {
    id: "account",
    header: "Rekening",
    cell: ({ row }) => row.original.account?.name ?? "-",
  },
  {
    accessorKey: "amount",
    header: "Nominal",
    cell: ({ row }) => <CurrencyDisplay value={row.original.amount} />,
  },
  {
    id: "allocations",
    header: "Alokasi",
    cell: ({ row }) => {
      const lines = row.original.allocations ?? [];

      if (!lines.length) {
        return "-";
      }

      return lines
        .map((line) => {
          const fund = line.fund?.name ?? "Dana";
          const program = line.program?.name ? ` / ${line.program.name}` : "";

          return `${fund}${program}`;
        })
        .join(", ");
    },
  },
];

export default function PortalDonaturPage() {
  const { user } = useAuth();
  const canView = hasPermission(user, "portal.view");
  const [pagination, setPagination] = useState<PaginationState>({ pageIndex: 0, pageSize: 10 });

  const summaryQuery = useQuery({
    queryKey: ["/portal/summary"],
    enabled: canView,
    queryFn: async () => {
      const response = await apiGet<PortalSummary>("/portal/summary");

      return response.data;
    },
    retry: false,
  });

  const donationsQuery = useResourceQuery<PortalDonation>(
    "/portal/donations",
    {
      page: pagination.pageIndex + 1,
      per_page: pagination.pageSize,
    },
    canView && summaryQuery.isSuccess,
  );

  const donor = summaryQuery.data?.donor;
  const summaryError = summaryQuery.error;

  const unlinked = useMemo(() => summaryError instanceof ApiError && summaryError.status === 404, [summaryError]);

  if (!canView) {
    return (
      <ErrorState
        title="Akses ditolak"
        description="Halaman ini hanya untuk akun dengan permission portal.view (role donatur)."
      />
    );
  }

  if (summaryQuery.isLoading) {
    return <PageShellSkeleton />;
  }

  if (unlinked) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="Portal Donatur" description="Lihat ringkasan dan riwayat donasi yang sudah disetujui." />
        <ErrorState
          title="Akun belum tertaut"
          description="Akun login ini belum ditautkan ke data donatur. Hubungi bendahara/admin untuk menautkan lewat master Donatur atau Pengaturan pengguna."
        />
      </div>
    );
  }

  if (summaryQuery.isError || !summaryQuery.data || !donor) {
    return <ErrorState onRetry={() => summaryQuery.refetch()} />;
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Portal Donatur"
        description={`Halo, ${donor.name}. Berikut ringkasan donasi yang sudah disetujui.`}
      />

      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Total Donasi</CardDescription>
            <CardTitle className="text-2xl">
              <CurrencyDisplay value={summaryQuery.data.total_donasi} />
            </CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Jumlah Transaksi</CardDescription>
            <CardTitle className="text-2xl">{summaryQuery.data.jumlah_transaksi}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Kode Donatur</CardDescription>
            <CardTitle className="text-2xl">{donor.code}</CardTitle>
          </CardHeader>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Profil</CardTitle>
          <CardDescription>Data donatur yang tertaut ke akun login Anda.</CardDescription>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <dt className="text-muted-foreground text-sm">Nama</dt>
              <dd className="font-medium">{donor.name}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Tipe</dt>
              <dd className="font-medium">{donor.type ?? "-"}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Email</dt>
              <dd className="font-medium">{donor.email ?? "-"}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Telepon</dt>
              <dd className="font-medium">{donor.phone ?? "-"}</dd>
            </div>
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Riwayat Donasi</CardTitle>
          <CardDescription>Hanya menampilkan penerimaan berstatus approved milik Anda.</CardDescription>
        </CardHeader>
        <CardContent>
          <PaginatedDataTable
            columns={donationColumns}
            data={donationsQuery.data?.rows ?? []}
            pagination={donationsQuery.data?.pagination}
            pageIndex={pagination.pageIndex}
            pageSize={pagination.pageSize}
            onPaginationChange={setPagination}
            isLoading={donationsQuery.isLoading}
            emptyMessage="Belum ada donasi yang disetujui."
          />
        </CardContent>
      </Card>
    </div>
  );
}
