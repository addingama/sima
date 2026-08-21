"use client";

import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { type ChartConfig, ChartContainer, ChartTooltip, ChartTooltipContent } from "@/components/ui/chart";
import { Skeleton } from "@/components/ui/skeleton";
import { useAccountBalancesQuery } from "@/hooks/use-resource-query";
import { parseAmount } from "@/lib/format/amount";
import { formatIdr } from "@/lib/format/currency";

const chartConfig = {
  balance: {
    label: "Saldo",
    color: "var(--chart-4)",
  },
} satisfies ChartConfig;

export function AccountBalanceChart() {
  const { data, isLoading, isError, refetch } = useAccountBalancesQuery();

  const chartData = (data?.rows ?? [])
    .map((row) => ({
      label: row.code,
      name: row.name,
      type: row.type,
      balance: parseAmount(row.balance),
    }))
    .filter((row) => row.balance > 0)
    .sort((a, b) => b.balance - a.balance)
    .slice(0, 8);

  let body: React.ReactNode;
  if (isError) {
    body = <ErrorState onRetry={() => refetch()} />;
  } else if (isLoading) {
    body = <Skeleton className="h-72 w-full" />;
  } else if (chartData.length === 0) {
    body = <p className="text-muted-foreground text-sm">Belum ada saldo kas/bank.</p>;
  } else {
    body = (
      <>
        <ChartContainer config={chartConfig} className="aspect-auto h-72 w-full">
          <BarChart data={chartData} layout="vertical" margin={{ left: 8, right: 8, top: 0, bottom: 0 }}>
            <CartesianGrid horizontal={false} strokeDasharray="0" />
            <XAxis type="number" hide />
            <YAxis type="category" dataKey="label" tickLine={false} axisLine={false} width={88} tickMargin={8} />
            <ChartTooltip
              content={
                <ChartTooltipContent
                  hideIndicator
                  labelFormatter={(_, payload) => {
                    const item = payload?.[0]?.payload as { name?: string; label?: string } | undefined;

                    return item?.name ?? item?.label ?? "";
                  }}
                  formatter={(value) => formatIdr(value as number)}
                />
              }
            />
            <Bar dataKey="balance" fill="var(--color-balance)" radius={[0, 6, 6, 0]} barSize={22} />
          </BarChart>
        </ChartContainer>
        {data?.total ? (
          <p className="mt-4 text-right text-muted-foreground text-sm">
            Total: <CurrencyDisplay value={data.total} className="font-medium text-foreground" />
          </p>
        ) : null}
      </>
    );
  }

  return (
    <Card className="@container/card">
      <CardHeader>
        <CardTitle>Saldo Kas/Bank</CardTitle>
        <CardDescription>Lokasi fisik uang per rekening (top 8)</CardDescription>
      </CardHeader>
      <CardContent>{body}</CardContent>
    </Card>
  );
}
