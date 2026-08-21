"use client";

import Link from "next/link";

import { CheckCircle2, Scale, TriangleAlert } from "lucide-react";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { useReconciliationSummaryQuery } from "@/hooks/use-resource-query";
import { cn } from "@/lib/utils";

export function ReconciliationSummaryCard() {
  const { data, isLoading, isError, refetch } = useReconciliationSummaryQuery();

  let body: React.ReactNode;
  if (isError) {
    body = <ErrorState onRetry={() => refetch()} />;
  } else if (isLoading || !data) {
    body = <Skeleton className="h-40 w-full" />;
  } else {
    body = (
      <div className="space-y-4">
        <div className="flex items-center justify-between gap-3">
          <span className="text-muted-foreground text-sm">Status buku</span>
          <Badge
            variant={data.seimbang ? "secondary" : "destructive"}
            className={cn("gap-1", data.seimbang && "bg-emerald-500/15 text-emerald-700 dark:text-emerald-400")}
          >
            {data.seimbang ? <CheckCircle2 className="size-3.5" /> : <TriangleAlert className="size-3.5" />}
            {data.seimbang ? "Seimbang" : "Tidak seimbang"}
          </Badge>
        </div>

        <dl className="grid gap-3 text-sm">
          <div className="flex items-center justify-between gap-3 border-b pb-2">
            <dt className="text-muted-foreground">Total Kas/Bank</dt>
            <dd>
              <CurrencyDisplay value={data.total_kas_bank} className="font-medium" />
            </dd>
          </div>
          <div className="flex items-center justify-between gap-3 border-b pb-2">
            <dt className="text-muted-foreground">Total Dana Amanah</dt>
            <dd>
              <CurrencyDisplay value={data.total_dana_amanah} className="font-medium" />
            </dd>
          </div>
          <div className="flex items-center justify-between gap-3 border-b pb-2">
            <dt className="text-muted-foreground">Selisih kas ↔ dana</dt>
            <dd className={cn("font-medium", data.selisih_kas_dana !== "0.00" && "text-destructive")}>
              <CurrencyDisplay value={data.selisih_kas_dana} />
            </dd>
          </div>
          <div className="flex items-center justify-between gap-3">
            <dt className="text-muted-foreground">Selisih debit ↔ credit</dt>
            <dd className={cn("font-medium", data.selisih_debit_credit !== "0.00" && "text-destructive")}>
              <CurrencyDisplay value={data.selisih_debit_credit} />
            </dd>
          </div>
        </dl>

        <Link
          href="/dashboard/reports"
          className="inline-block text-primary text-sm underline-offset-4 hover:underline"
        >
          Buka laporan
        </Link>
      </div>
    );
  }

  return (
    <Card className="@container/card">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Scale className="size-4 text-muted-foreground" />
          Rekonsiliasi Global
        </CardTitle>
        <CardDescription>Kas/Bank vs Dana Amanah harus selisih 0 (invariant Amanah Ledger)</CardDescription>
      </CardHeader>
      <CardContent>{body}</CardContent>
    </Card>
  );
}
