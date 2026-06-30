"use client";

"use no memo";

import { useMemo, useState } from "react";

import { useQuery } from "@tanstack/react-query";
import type { GroupingState } from "@tanstack/react-table";
import { FileDown, FileSpreadsheet, Printer, RefreshCw } from "lucide-react";
import { toast } from "sonner";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { ReportDataTable } from "@/components/sima/reports/report-data-table";
import { ReportFiltersBar, useReportFilterDefaults } from "@/components/sima/reports/report-filters";
import { TableSkeleton } from "@/components/sima/skeletons";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { NativeSelect, NativeSelectOption } from "@/components/ui/native-select";
import { hasPermission } from "@/lib/auth/permissions";
import { columnsToExportColumns } from "@/lib/reports/export-columns";
import type { ReportDef } from "@/lib/reports/types";
import { useAuth } from "@/providers/auth-provider";

export function ReportPage({ config }: { config: ReportDef }) {
  const { user } = useAuth();
  const defaultFilters = useReportFilterDefaults(config.filters);
  const [filters, setFilters] = useState(defaultFilters);
  const [appliedFilters, setAppliedFilters] = useState(defaultFilters);
  const [grouping, setGrouping] = useState<GroupingState>([]);
  const [pageIndex, setPageIndex] = useState(0);
  const pageSize = config.pageSize ?? 50;
  const printElementId = `report-print-${config.id}`;

  const queryParams = useMemo(() => {
    const filterParams = Object.fromEntries(Object.entries(appliedFilters).filter(([, value]) => value !== ""));

    if (config.paginated === false) {
      return filterParams;
    }

    return {
      page: pageIndex + 1,
      per_page: pageSize,
      ...filterParams,
    };
  }, [appliedFilters, config.paginated, pageIndex, pageSize]);

  const { data, isLoading, isError, refetch, isFetching } = useQuery({
    queryKey: [config.id, queryParams],
    queryFn: () => config.fetchData(queryParams),
  });

  const exportRows = data?.rows ?? [];
  const exportColumns = useMemo(() => columnsToExportColumns(config.columns), [config.columns]);

  const handleExport = async (type: "pdf" | "excel" | "print") => {
    if (!exportRows.length) {
      toast.error("Tidak ada data untuk diekspor.");
      return;
    }

    const filename = config.id;

    try {
      if (type === "print") {
        const { printReportElement } = await import("@/lib/reports/export-utils");
        printReportElement(printElementId);
        return;
      }

      const { exportReportToExcel, exportReportToPdf } = await import("@/lib/reports/export-utils");

      if (type === "pdf") {
        await exportReportToPdf(filename, config.title, exportColumns, exportRows);
      } else {
        await exportReportToExcel(filename, config.title, exportColumns, exportRows);
      }
    } catch {
      toast.error("Gagal mengekspor laporan.");
    }
  };

  const missingRequiredFilter = (config.filters ?? []).some(
    (filter) => filter.required && !appliedFilters[filter.name],
  );

  if (config.permission && !hasPermission(user, config.permission)) {
    return <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk melihat laporan ini." />;
  }

  return (
    <div className="flex flex-col gap-6 print:gap-4">
      <PageHeader
        title={config.title}
        description={config.description}
        actions={
          <div className="flex flex-wrap gap-2 print:hidden">
            <Button variant="outline" size="sm" onClick={() => refetch()} disabled={isFetching}>
              <RefreshCw className="size-4" />
              Refresh
            </Button>
            <Button variant="outline" size="sm" onClick={() => handleExport("print")}>
              <Printer className="size-4" />
              Print
            </Button>
            <Button variant="outline" size="sm" onClick={() => handleExport("excel")}>
              <FileSpreadsheet className="size-4" />
              Excel
            </Button>
            <Button variant="outline" size="sm" onClick={() => handleExport("pdf")}>
              <FileDown className="size-4" />
              PDF
            </Button>
          </div>
        }
      />

      {config.filters?.length ? (
        <Card className="print:hidden">
          <CardHeader>
            <CardTitle>Filter</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <ReportFiltersBar
              filters={config.filters}
              values={filters}
              onChange={(name, value) => setFilters((current) => ({ ...current, [name]: value }))}
            />
            <Button
              onClick={() => {
                setAppliedFilters(filters);
                setPageIndex(0);
              }}
            >
              Terapkan Filter
            </Button>
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
          <CardTitle>Data Laporan</CardTitle>
          {config.groupByOptions?.length ? (
            <div className="flex min-w-48 items-center gap-2">
              <span className="text-muted-foreground text-sm">Grouping</span>
              <NativeSelect
                value={grouping[0] ?? ""}
                onChange={(event) => setGrouping(event.target.value ? [event.target.value] : [])}
              >
                <NativeSelectOption value="">Tanpa grouping</NativeSelectOption>
                {config.groupByOptions.map((option) => (
                  <NativeSelectOption key={option.value} value={option.value}>
                    {option.label}
                  </NativeSelectOption>
                ))}
              </NativeSelect>
            </div>
          ) : null}
        </CardHeader>
        <CardContent className="space-y-4">
          {data?.summary ? (
            <div className="grid grid-cols-1 gap-3 rounded-lg border bg-muted/20 p-4 sm:grid-cols-3 print:hidden">
              {Object.entries(data.summary).map(([key, value]) => (
                <div key={key}>
                  <p className="text-muted-foreground text-xs capitalize">{key.replaceAll("_", " ")}</p>
                  <p className="font-medium text-sm">
                    {typeof value === "string" && /^\d/.test(value) ? <CurrencyDisplay value={value} /> : String(value)}
                  </p>
                </div>
              ))}
            </div>
          ) : null}

          {missingRequiredFilter ? (
            <p className="text-muted-foreground text-sm">Pilih filter wajib lalu klik Terapkan Filter.</p>
          ) : isError ? (
            <ErrorState onRetry={() => refetch()} />
          ) : isLoading ? (
            <TableSkeleton />
          ) : config.paginated === false ? (
            <ReportDataTable
              columns={config.columns}
              data={exportRows}
              grouping={grouping}
              onGroupingChange={setGrouping}
              printElementId={printElementId}
            />
          ) : (
            <div className="space-y-4">
              <ReportDataTable
                columns={config.columns}
                data={exportRows}
                grouping={grouping}
                onGroupingChange={setGrouping}
                printElementId={printElementId}
              />
              {data?.pagination ? (
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between print:hidden">
                  <p className="text-muted-foreground text-sm">
                    Menampilkan {data.pagination.from ?? 0}–{data.pagination.to ?? 0} dari {data.pagination.total} baris
                  </p>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={pageIndex <= 0}
                      onClick={() => setPageIndex((current) => Math.max(current - 1, 0))}
                    >
                      Sebelumnya
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={(data.pagination.current_page ?? 1) >= (data.pagination.last_page ?? 1)}
                      onClick={() => setPageIndex((current) => current + 1)}
                    >
                      Berikutnya
                    </Button>
                  </div>
                </div>
              ) : null}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
