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

  const chartData = (data?.cash_flow_monthly ?? []).map((row) => ({
    label: row.label,
    penerimaan: parseAmount(row.penerimaan),
    pengeluaran: parseAmount(row.pengeluaran),
  }));

  const hasActivity = chartData.some((row) => row.penerimaan > 0 || row.pengeluaran > 0);

  let body: React.ReactNode;
  if (isError) {
    body = <ErrorState onRetry={() => refetch()} />;
  } else if (isLoading || !data) {
    body = <Skeleton className="h-72 w-full" />;
  } else if (!hasActivity) {
    body = (
      <p className="text-muted-foreground text-sm">Belum ada penerimaan/pengeluaran approved dalam 6 bulan terakhir.</p>
    );
  } else {
    body = (
      <ChartContainer config={chartConfig} className="aspect-auto h-72 w-full">
        <BarChart data={chartData} margin={{ left: 0, right: 8, top: 8, bottom: 0 }}>
          <CartesianGrid vertical={false} strokeDasharray="0" />
          <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={10} minTickGap={16} />
          <YAxis
            tickLine={false}
            axisLine={false}
            width={72}
            tickFormatter={(value) => formatCompactIdr(Number(value))}
          />
          <ChartTooltip content={<ChartTooltipContent formatter={(value) => formatIdr(value as number)} />} />
          <ChartLegend content={<ChartLegendContent />} />
          <Bar dataKey="penerimaan" fill="var(--color-penerimaan)" radius={[4, 4, 0, 0]} />
          <Bar dataKey="pengeluaran" fill="var(--color-pengeluaran)" radius={[4, 4, 0, 0]} />
        </BarChart>
      </ChartContainer>
    );
  }

  return (
    <Card className="@container/card xl:col-span-2">
      <CardHeader>
        <CardTitle>Arus Kas</CardTitle>
        <CardDescription>
          Penerimaan vs pengeluaran yang sudah approved — 6 bulan terakhir
          {data
            ? ` · bulan ini: ${formatIdr(parseAmount(data.penerimaan_bulan_ini))} masuk / ${formatIdr(parseAmount(data.pengeluaran_bulan_ini))} keluar`
            : null}
        </CardDescription>
      </CardHeader>
      <CardContent>{body}</CardContent>
    </Card>
  );
}

function formatCompactIdr(value: number): string {
  if (value >= 1_000_000_000) {
    return `${(value / 1_000_000_000).toFixed(1)}M`;
  }
  if (value >= 1_000_000) {
    return `${(value / 1_000_000).toFixed(1)}jt`;
  }
  if (value >= 1_000) {
    return `${(value / 1_000).toFixed(0)}rb`;
  }

  return String(value);
}
