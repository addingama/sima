"use client";

import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { type ChartConfig, ChartContainer, ChartTooltip, ChartTooltipContent } from "@/components/ui/chart";
import { Skeleton } from "@/components/ui/skeleton";
import { useFundBalancesQuery } from "@/hooks/use-resource-query";
import { parseAmount } from "@/lib/format/amount";
import { formatIdr } from "@/lib/format/currency";

const chartConfig = {
  balance: {
    label: "Saldo",
    color: "var(--chart-1)",
  },
} satisfies ChartConfig;

export function FundBalanceChart() {
  const { data, isLoading, isError, refetch } = useFundBalancesQuery();

  const chartData = (data?.rows ?? [])
    .map((row) => ({
      label: row.code,
      name: row.name,
      balance: parseAmount(row.balance),
    }))
    .filter((row) => row.balance > 0)
    .sort((a, b) => b.balance - a.balance)
    .slice(0, 8);

  return (
    <Card className="@container/card">
      <CardHeader>
        <CardTitle>Saldo Dana Amanah</CardTitle>
        <CardDescription>Distribusi saldo per dana (top 8)</CardDescription>
      </CardHeader>
      <CardContent>
        {isError ? (
          <ErrorState onRetry={() => refetch()} />
        ) : isLoading ? (
          <Skeleton className="h-72 w-full" />
        ) : chartData.length === 0 ? (
          <p className="text-muted-foreground text-sm">Belum ada saldo dana amanah.</p>
        ) : (
          <>
            <ChartContainer config={chartConfig} className="aspect-auto h-72 w-full">
              <BarChart data={chartData} margin={{ left: 0, right: 8, top: 0, bottom: 0 }} barSize={32}>
                <CartesianGrid vertical={false} strokeDasharray="0" />
                <XAxis
                  dataKey="label"
                  tickLine={false}
                  axisLine={false}
                  tickMargin={10}
                  interval={0}
                  angle={-20}
                  textAnchor="end"
                  height={56}
                />
                <YAxis hide />
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
                <Bar dataKey="balance" fill="var(--color-balance)" radius={[6, 6, 0, 0]} />
              </BarChart>
            </ChartContainer>
            {data?.total ? (
              <p className="mt-4 text-right text-muted-foreground text-sm">
                Total: <CurrencyDisplay value={data.total} className="font-medium text-foreground" />
              </p>
            ) : null}
          </>
        )}
      </CardContent>
    </Card>
  );
}
