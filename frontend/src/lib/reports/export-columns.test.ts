import type { ColumnDef } from "@tanstack/react-table";
import { describe, expect, it } from "vitest";

import { columnsToExportColumns } from "./export-columns";

describe("columnsToExportColumns", () => {
  it("hanya mengekspor accessorKey dan memakai header string", () => {
    const columns: Array<ColumnDef<Record<string, unknown>, unknown>> = [
      { accessorKey: "name", header: "Nama" },
      { accessorKey: "amount", header: () => "Nominal" },
      { id: "action", header: "Aksi" },
    ];

    const result = columnsToExportColumns(columns);

    expect(result.map((column) => column.header)).toEqual(["Nama", "amount"]);
    expect(result.map((column) => column.value({ name: "Dana", amount: 1000 }))).toEqual(["Dana", "1000"]);
  });

  it("mengubah null menjadi string kosong", () => {
    const [column] = columnsToExportColumns([{ accessorKey: "note", header: "Catatan" }]);

    expect(column.value({ note: null })).toBe("");
    expect(column.value({ note: undefined })).toBe("");
  });

  it("memprioritaskan exportValue dari metadata", () => {
    const [column] = columnsToExportColumns([
      {
        accessorKey: "amount",
        header: "Nominal",
        meta: { exportValue: (row: Record<string, unknown>) => `Rp ${row.amount}` },
      },
    ]);

    expect(column.value({ amount: 2500 })).toBe("Rp 2500");
  });
});
