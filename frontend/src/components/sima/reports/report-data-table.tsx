"use client";

"use no memo";

import {
  flexRender,
  getCoreRowModel,
  getExpandedRowModel,
  getGroupedRowModel,
  getSortedRowModel,
  type GroupingState,
  type Updater,
  useReactTable,
} from "@tanstack/react-table";
import { useCallback, useMemo } from "react";

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { ReportDef } from "@/lib/reports/types";
import { cn } from "@/lib/utils";

export function ReportDataTable({
  columns,
  data,
  grouping,
  onGroupingChange,
  printElementId,
}: {
  columns: ReportDef["columns"];
  data: Array<Record<string, unknown>>;
  grouping: GroupingState;
  onGroupingChange: (updater: Updater<GroupingState>) => void;
  printElementId: string;
}) {
  const isGrouped = grouping.length > 0;

  const coreRowModel = useMemo(() => getCoreRowModel(), []);
  const sortedRowModel = useMemo(() => getSortedRowModel(), []);
  const groupedRowModel = useMemo(() => getGroupedRowModel(), []);
  const expandedRowModel = useMemo(() => getExpandedRowModel(), []);

  const handleGroupingChange = useCallback(
    (updater: Updater<GroupingState>) => {
      onGroupingChange((current) => {
        const next = typeof updater === "function" ? updater(current) : updater;

        if (next.length === current.length && next.every((value, index) => value === current[index])) {
          return current;
        }

        return next;
      });
    },
    [onGroupingChange],
  );

  const table = useReactTable({
    data,
    columns,
    state: { grouping },
    onGroupingChange: handleGroupingChange,
    getCoreRowModel: coreRowModel,
    getSortedRowModel: sortedRowModel,
    ...(isGrouped
      ? {
          getGroupedRowModel: groupedRowModel,
          getExpandedRowModel: expandedRowModel,
        }
      : {}),
  });

  return (
    <div id={printElementId} className="overflow-hidden rounded-lg border bg-card">
      <div className="hidden print:block border-b px-4 py-3">
        <h2 className="font-semibold text-lg">Laporan SIMA</h2>
      </div>
      <Table>
        <TableHeader>
          {table.getHeaderGroups().map((headerGroup) => (
            <TableRow key={headerGroup.id}>
              {headerGroup.headers.map((header) => (
                <TableHead key={header.id}>
                  {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                </TableHead>
              ))}
            </TableRow>
          ))}
        </TableHeader>
        <TableBody>
          {table.getRowModel().rows.length ? (
            table.getRowModel().rows.map((row) => (
              <TableRow
                key={row.id}
                className={cn(row.getIsGrouped() && "bg-muted/40 font-medium")}
              >
                {row.getVisibleCells().map((cell) => (
                  <TableCell key={cell.id}>
                    {cell.getIsGrouped() ? (
                      <div className="flex items-center gap-2">
                        <button
                          type="button"
                          className="print:hidden"
                          onClick={row.getToggleExpandedHandler()}
                        >
                          {row.getIsExpanded() ? "▼" : "▶"}
                        </button>
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                        <span className="text-muted-foreground text-xs">({row.subRows.length})</span>
                      </div>
                    ) : cell.getIsAggregated() ? (
                      flexRender(cell.column.columnDef.aggregatedCell ?? cell.column.columnDef.cell, cell.getContext())
                    ) : cell.getIsPlaceholder() ? null : (
                      flexRender(cell.column.columnDef.cell, cell.getContext())
                    )}
                  </TableCell>
                ))}
              </TableRow>
            ))
          ) : (
            <TableRow>
              <TableCell colSpan={columns.length} className="h-24 text-center text-muted-foreground">
                Tidak ada data laporan.
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>
    </div>
  );
}
