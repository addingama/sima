// biome-ignore-all lint/style/noNestedTernary: label ternary singkat lebih mudah dibaca dekat markup
"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { currencyColumn, dateColumn, linkColumn, statusColumn } from "@/lib/resources/columns";

import type { PettyCashFloat } from "./types";

const basePath = "/dashboard/kas-kecil";

export const pettyCashFloatListColumns: ColumnDef<Record<string, unknown>>[] = [
  linkColumn("float_number", "No. Float", basePath, (row) => String(row.float_number ?? `#${row.id}`)),
  { accessorKey: "title", header: "Judul" },
  {
    accessorKey: "purpose",
    header: "Tujuan",
    cell: ({ row }) => {
      const p = row.original.purpose as PettyCashFloat["purpose"] | undefined;
      return p === "operational" ? "Operasional" : p === "event" ? "Event" : "-";
    },
  },
  {
    id: "custodian_name",
    header: "PJ",
    cell: ({ row }) => {
      const c = row.original.custodian as { name?: string } | undefined;
      return c?.name ?? "-";
    },
  },
  dateColumn("period_start", "Periode Mulai"),
  dateColumn("period_end", "Periode Akhir"),
  currencyColumn("funded_total", "Dana Cair"),
  currencyColumn("spent_total", "Terpakai"),
  statusColumn(),
];
