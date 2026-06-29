import type { ColumnDef } from "@tanstack/react-table";

import type { ExportColumn } from "./types";

function stringifyCell(value: unknown): string {
  if (value === null || value === undefined) {
    return "";
  }

  return String(value);
}

export function columnsToExportColumns(
  columns: Array<ColumnDef<Record<string, unknown>, unknown>>,
): ExportColumn[] {
  return columns
    .filter((column) => "accessorKey" in column && column.accessorKey)
    .map((column) => {
      const key = String((column as { accessorKey?: string }).accessorKey);
      const header = typeof column.header === "string" ? column.header : key;
      const meta = column.meta as { exportValue?: (row: Record<string, unknown>) => string } | undefined;

      return {
        header,
        value: (row) => (meta?.exportValue ? meta.exportValue(row) : stringifyCell(row[key])),
      };
    });
}
