"use client";

import Link from "next/link";

import { ClipboardCheck, DollarSign, HandCoins, Wallet } from "lucide-react";

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { MetricCardsSkeleton } from "@/components/sima/skeletons";
import { useDashboardQuery } from "@/hooks/use-resource-query";

export function SimaMetricCards() {
  const { data, isLoading, isError, refetch } = useDashboardQuery();

  if (isError) {
    return <ErrorState onRetry={() => refetch()} />;
  }

  if (isLoading || !data) {
    return <MetricCardsSkeleton />;
  }

  const cards = [
    {
      title: "Saldo Kas",
      value: data.total_kas_bank,
      description: "Saldo fisik seluruh rekening kas/bank",
      icon: DollarSign,
      href: "/dashboard/reports/account-balances",
      isCurrency: true,
    },
    {
      title: "Saldo Dana Amanah",
      value: data.total_dana_amanah,
      description: "Total pembatas penggunaan dana",
      icon: HandCoins,
      href: "/dashboard/reports/fund-balances",
      isCurrency: true,
    },
    {
      title: "Approval Pending",
      value: String(data.receipts_pending),
      description: "Penerimaan draft atau menunggu persetujuan",
      icon: ClipboardCheck,
      href: "/dashboard/receipts",
      isCurrency: false,
    },
    {
      title: "Pengeluaran Pending",
      value: String(data.disbursements_pending),
      description: "Pengeluaran submitted atau verified",
      icon: Wallet,
      href: "/dashboard/disbursements",
      isCurrency: false,
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 *:data-[slot=card]:bg-linear-to-t *:data-[slot=card]:from-primary/5 *:data-[slot=card]:to-card *:data-[slot=card]:shadow-xs xl:grid-cols-4 dark:*:data-[slot=card]:bg-card">
      {cards.map((card) => (
        <Link key={card.title} href={card.href} className="transition-opacity hover:opacity-90">
          <Card className="h-full">
            <CardHeader>
              <CardTitle>
                <div className="flex size-7 items-center justify-center rounded-lg border bg-muted text-muted-foreground">
                  <card.icon className="size-4" />
                </div>
              </CardTitle>
              <CardDescription>{card.title}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-1">
              <div className="font-medium text-3xl leading-none tracking-tight">
                {card.isCurrency ? (
                  <CurrencyDisplay value={card.value} />
                ) : (
                  <span className="tabular-nums">{card.value}</span>
                )}
              </div>
              <p className="text-muted-foreground text-sm">{card.description}</p>
            </CardContent>
          </Card>
        </Link>
      ))}
    </div>
  );
}
