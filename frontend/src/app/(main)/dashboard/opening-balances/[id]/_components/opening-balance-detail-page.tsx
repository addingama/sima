"use client";

import Link from "next/link";
import { useParams } from "next/navigation";

import { ArrowLeft } from "lucide-react";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { PageShellSkeleton } from "@/components/sima/skeletons";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useDetailQuery } from "@/hooks/use-resource-query";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDate, formatDateTime } from "@/lib/format/datetime";
import type { OpeningBalanceBatch } from "@/lib/opening-balance/types";
import { useAuth } from "@/providers/auth-provider";

export default function OpeningBalanceDetailPage() {
  const params = useParams<{ id: string }>();
  const { user } = useAuth();
  const { data, isLoading, isError, refetch } = useDetailQuery<OpeningBalanceBatch>(
    "/opening-balances",
    params.id,
    hasPermission(user, "opening.view"),
  );

  if (!hasPermission(user, "opening.view")) {
    return <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk melihat saldo awal." />;
  }

  if (isLoading) {
    return <PageShellSkeleton />;
  }

  if (isError || !data) {
    return <ErrorState onRetry={() => refetch()} />;
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={data.batch_number}
        description="Detail batch posting saldo awal — read-only setelah diposting."
        actions={
          <Button variant="outline" asChild>
            <Link href="/dashboard/opening-balances">
              <ArrowLeft className="size-4" />
              Kembali
            </Link>
          </Button>
        }
      />

      <Card>
        <CardHeader>
          <CardTitle>Ringkasan Batch</CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <dt className="text-muted-foreground text-sm">Tanggal cutover</dt>
              <dd className="font-medium">{formatDate(data.opening_date)}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Total</dt>
              <dd className="font-medium">
                <CurrencyDisplay value={data.total_amount} />
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Diposting</dt>
              <dd className="font-medium">{formatDateTime(data.posted_at)}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Referensi</dt>
              <dd className="font-medium">{data.reference ?? "-"}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Oleh</dt>
              <dd className="font-medium">{data.posted_by_user?.name ?? `#${data.posted_by}`}</dd>
            </div>
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Baris Saldo Awal</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-left">
                <tr>
                  <th className="px-3 py-2">#</th>
                  <th className="px-3 py-2">Rekening</th>
                  <th className="px-3 py-2">Dana Amanah</th>
                  <th className="px-3 py-2 text-right">Nominal</th>
                </tr>
              </thead>
              <tbody>
                {(data.lines ?? []).map((line) => (
                  <tr key={line.id} className="border-t">
                    <td className="px-3 py-2">{line.line_number}</td>
                    <td className="px-3 py-2">{line.account?.name ?? `#${line.account_id}`}</td>
                    <td className="px-3 py-2">{line.fund?.name ?? `#${line.fund_id}`}</td>
                    <td className="px-3 py-2 text-right">
                      <CurrencyDisplay value={line.amount} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
