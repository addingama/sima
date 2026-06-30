import { activeColumn, currencyColumn, linkColumn } from "../columns";
import type { ResourceDef } from "../types";

const basePath = "/dashboard/funds";

export const fundResource: ResourceDef = {
  resource: "/funds",
  basePath,
  label: "Dana Amanah",
  labelPlural: "Dana Amanah",
  permissions: {
    view: "fund.view",
    manage: "fund.manage",
    create: "fund.manage",
    delete: "fund.manage",
  },
  titleField: (row) => String(row.name ?? row.code ?? "Dana Amanah"),
  listColumns: [
    linkColumn("code", "Kode", basePath),
    linkColumn("name", "Nama", basePath, (row) => String(row.name ?? "-")),
    { accessorKey: "type", header: "Tipe" },
    currencyColumn("balance", "Saldo"),
    activeColumn(),
  ],
  filters: [
    {
      name: "type",
      label: "Tipe",
      type: "select",
      allLabel: "Semua tipe",
      options: [
        { value: "restricted", label: "Restricted" },
        { value: "unrestricted", label: "Unrestricted" },
        { value: "operational", label: "Operational" },
      ],
    },
    {
      name: "is_active",
      label: "Status",
      type: "select",
      allLabel: "Semua status",
      options: [
        { value: "1", label: "Aktif" },
        { value: "0", label: "Nonaktif" },
      ],
    },
  ],
  defaultSort: { field: "name", direction: "asc" },
  formFields: [
    { name: "code", label: "Kode", type: "text", required: true },
    { name: "name", label: "Nama", type: "text", required: true },
    { name: "description", label: "Deskripsi", type: "textarea" },
    {
      name: "type",
      label: "Tipe",
      type: "select",
      required: true,
      options: [
        { value: "restricted", label: "Restricted" },
        { value: "unrestricted", label: "Unrestricted" },
      ],
    },
    { name: "is_active", label: "Aktif", type: "checkbox" },
  ],
  detailFields: [
    { label: "Kode", accessor: "code" },
    { label: "Nama", accessor: "name" },
    { label: "Deskripsi", accessor: "description" },
    { label: "Tipe", accessor: "type" },
    { label: "Saldo", accessor: "balance", type: "currency" },
    { label: "Sistem", accessor: "is_system", type: "boolean" },
    { label: "Status", accessor: "is_active", type: "boolean" },
    { label: "Dibuat", accessor: "created_at", type: "datetime" },
  ],
  audit: {
    auditableType: "App\\Models\\Fund",
    permission: "audit.view",
  },
  getCreateDefaults: () => ({ type: "restricted", is_active: true }),
  canDelete: (row) => !row.is_system,
  canEdit: (row) => !row.is_system,
};
