import type { ResourceDef } from "../types";
import { activeColumn, currencyColumn, dateColumn, linkColumn, nestedNameColumn, statusColumn } from "../columns";

const basePath = "/dashboard/programs";

export const programResource: ResourceDef = {
  resource: "/programs",
  basePath,
  label: "Event/Program",
  labelPlural: "Event/Program",
  permissions: {
    view: "program.view",
    manage: "program.manage",
    create: "program.manage",
    delete: "program.manage",
  },
  titleField: (row) => String(row.name ?? row.code ?? "Program"),
  listColumns: [
    linkColumn("code", "Kode", basePath),
    linkColumn("name", "Nama", basePath, (row) => String(row.name ?? "-")),
    nestedNameColumn("fund", "Dana Amanah"),
    statusColumn(),
    currencyColumn("budget", "Anggaran"),
    dateColumn("start_date", "Mulai"),
    activeColumn(),
  ],
  filters: [
    {
      name: "status",
      label: "Status Program",
      type: "select",
      allLabel: "Semua status",
      options: [
        { value: "planned", label: "Planned" },
        { value: "active", label: "Active" },
        { value: "closed", label: "Closed" },
      ],
    },
    {
      name: "is_active",
      label: "Aktif",
      type: "select",
      allLabel: "Semua",
      options: [
        { value: "1", label: "Aktif" },
        { value: "0", label: "Nonaktif" },
      ],
    },
  ],
  defaultSort: { field: "name", direction: "asc" },
  formFields: [
    {
      name: "fund_id",
      label: "Dana Amanah",
      type: "relation",
      relation: { resource: "/funds", labelKey: "name", params: { is_active: 1, per_page: 100 } },
    },
    { name: "code", label: "Kode", type: "text", required: true },
    { name: "name", label: "Nama", type: "text", required: true },
    { name: "description", label: "Deskripsi", type: "textarea" },
    { name: "budget", label: "Anggaran", type: "currency" },
    { name: "start_date", label: "Tanggal Mulai", type: "date" },
    { name: "end_date", label: "Tanggal Selesai", type: "date" },
    {
      name: "status",
      label: "Status",
      type: "select",
      options: [
        { value: "planned", label: "Planned" },
        { value: "active", label: "Active" },
        { value: "closed", label: "Closed" },
      ],
    },
    { name: "is_active", label: "Aktif", type: "checkbox" },
  ],
  detailFields: [
    { label: "Kode", accessor: "code" },
    { label: "Nama", accessor: "name" },
    { label: "Dana Amanah", accessor: (row) => (row.fund as { name?: string } | undefined)?.name ?? "-" },
    { label: "Deskripsi", accessor: "description" },
    { label: "Anggaran", accessor: "budget", type: "currency" },
    { label: "Mulai", accessor: "start_date", type: "date" },
    { label: "Selesai", accessor: "end_date", type: "date" },
    { label: "Status Program", accessor: "status", type: "status" },
    { label: "Aktif", accessor: "is_active", type: "boolean" },
    { label: "Dibuat", accessor: "created_at", type: "datetime" },
  ],
  audit: {
    auditableType: "App\\Models\\Program",
    permission: "audit.view",
  },
  getCreateDefaults: () => ({ status: "planned", is_active: true }),
  mapToForm: (row) => ({
    ...row,
    fund_id: row.fund_id ? String(row.fund_id) : "",
    budget: row.budget ?? "",
  }),
  mapToPayload: (values) => ({
    ...values,
    fund_id: values.fund_id ? Number(values.fund_id) : null,
    budget: values.budget === "" ? null : values.budget,
  }),
  canDelete: () => true,
  canEdit: () => true,
};
