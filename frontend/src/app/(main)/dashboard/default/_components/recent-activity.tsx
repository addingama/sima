"use client";

import Link from "next/link";

import { ArrowDownLeft, ArrowUpRight } from "lucide-react";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { useRecentLedgerQuery } from "@/hooks/use-resource-query";
import { parseAmount } from "@/lib/format/amount";
import { cn } from "@/lib/utils";

function formatActivityTime(value: string): string {
  return new Date(value).toLocaleString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function formatTransactionType(value: string): string {
  return value.replaceAll("_", " ");
}

export function RecentActivity() {
  const { data, isLoading, isError, refetch } = useRecentLedgerQuery(8);

  return (
    <Card className="xl:col-span-2">
      <CardHeader>
        <CardTitle>Aktivitas</CardTitle>
        <CardDescription>Entri buku besar terbaru</CardDescription>
      </CardHeader>
      <CardContent>
        {isError ? (
          <ErrorState onRetry={() => refetch()} />
        ) : isLoading ? (
          <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, index) => (
              <Skeleton key={index} className="h-14 w-full" />
            ))}
          </div>
        ) : !data?.length ? (
          <p className="text-muted-foreground text-sm">Belum ada aktivitas ledger.</p>
        ) : (
          <ul className="space-y-2">
            {data.map((entry) => {
              const debit = parseAmount(entry.debit);
              const credit = parseAmount(entry.credit);
              const isInflow = credit > debit;
              const amount = isInflow ? credit : debit;

              return (
                <li key={entry.id} className="flex items-start justify-between gap-3 rounded-lg border px-3 py-2.5">
                  <div className="min-w-0 space-y-1">
                    <div className="flex items-center gap-2">
                      <span
                        className={cn(
                          "flex size-6 shrink-0 items-center justify-center rounded-md",
                          isInflow
                            ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                            : "bg-destructive/10 text-destructive",
                        )}
                      >
                        {isInflow ? <ArrowDownLeft className="size-3.5" /> : <ArrowUpRight className="size-3.5" />}
                      </span>
                      <span className="truncate font-medium text-sm capitalize">
                        {formatTransactionType(entry.transaction_type)}
                      </span>
                    </div>
                    <p className="truncate text-muted-foreground text-xs">
                      {entry.reference ?? `Transaksi #${entry.transaction_id}`}
                    </p>
                    <p className="text-muted-foreground text-xs">{formatActivityTime(entry.created_at)}</p>
                  </div>
                  <CurrencyDisplay
                    value={amount}
                    className={cn(
                      "shrink-0 font-medium text-sm",
                      isInflow ? "text-emerald-600 dark:text-emerald-400" : "",
                    )}
                  />
                </li>
              );
            })}
          </ul>
        )}
        <Link
          href="/dashboard/reports/ledger"
          className="mt-4 inline-block text-primary text-sm underline-offset-4 hover:underline"
        >
          Lihat buku besar
        </Link>
      </CardContent>
    </Card>
  );
}
