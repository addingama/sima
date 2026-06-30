"use client";

import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts";

import { ErrorState } from "@/components/sima/error-state";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  type ChartConfig,
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from "@/components/ui/chart";
import { Skeleton } from "@/components/ui/skeleton";
import { useDashboardQuery } from "@/hooks/use-resource-query";
import { parseAmount } from "@/lib/format/amount";
import { formatIdr } from "@/lib/format/currency";

const chartConfig = {
  penerimaan: {
    label: "Penerimaan",
    color: "var(--chart-2)",
  },
  pengeluaran: {
    label: "Pengeluaran",
    color: "var(--chart-3)",
  },
} satisfies ChartConfig;

export function CashFlowChart() {
  const { data, isLoading, isError, refetch } = useDashboardQuery();

  const chartData = data
    ? [
        {
          category: "Bulan Ini",
          penerimaan: parseAmount(data.penerimaan_bulan_ini),
          pengeluaran: parseAmount(data.pengeluaran_bulan_ini),
        },
      ]
    : [];

  return (
    <Card className="@container/card">
      <CardHeader>
        <CardTitle>Cash Flow</CardTitle>
        <CardDescription>Penerimaan vs pengeluaran approved bulan berjalan</CardDescription>
      </CardHeader>
      <CardContent>
        {isError ? (
          <ErrorState onRetry={() => refetch()} />
        ) : isLoading || !data ? (
          <Skeleton className="h-72 w-full" />
        ) : (
          <ChartContainer config={chartConfig} className="aspect-auto h-72 w-full">
            <BarChart data={chartData} margin={{ left: 0, right: 0, top: 0, bottom: 0 }} barSize={48}>
              <CartesianGrid vertical={false} strokeDasharray="0" />
              <XAxis dataKey="category" tickLine={false} axisLine={false} tickMargin={10} />
              <YAxis hide />
              <ChartTooltip
                content={<ChartTooltipContent formatter={(value) => formatIdr(value as number)} hideIndicator />}
              />
              <ChartLegend content={<ChartLegendContent />} />
              <Bar dataKey="penerimaan" fill="var(--color-penerimaan)" radius={[6, 6, 0, 0]} />
              <Bar dataKey="pengeluaran" fill="var(--color-pengeluaran)" radius={[6, 6, 0, 0]} />
            </BarChart>
          </ChartContainer>
        )}
      </CardContent>
    </Card>
  );
}
