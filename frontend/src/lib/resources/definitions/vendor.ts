import { activeColumn, linkColumn } from "../columns";
import type { ResourceDef } from "../types";

const basePath = "/dashboard/vendors";

const vendorCodeHelper = "Format VND/2026/000001 — dibuat otomatis saat disimpan (selaras nomor dokumen SIMA).";

export const vendorResource: ResourceDef = {
  resource: "/vendors",
  basePath,
  label: "Vendor",
  labelPlural: "Vendor",
  permissions: {
    view: "vendor.view",
    manage: "vendor.manage",
    create: "vendor.manage",
    delete: "vendor.manage",
  },
  titleField: (row) => String(row.name ?? row.code ?? "Vendor"),
  listColumns: [
    linkColumn("code", "Kode", basePath),
    linkColumn("name", "Nama", basePath, (row) => String(row.name ?? "-")),
    { accessorKey: "contact_name", header: "Kontak" },
    { accessorKey: "email", header: "Email" },
    { accessorKey: "phone", header: "Telepon" },
    activeColumn(),
  ],
  filters: [
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
    {
      name: "code",
      label: "Kode",
      type: "text",
      autoGenerate: true,
      readOnly: true,
      placeholder: "Otomatis saat disimpan",
      helperText: vendorCodeHelper,
    },
    { name: "name", label: "Nama", type: "text", required: true },
    { name: "contact_name", label: "Nama Kontak", type: "text" },
    { name: "email", label: "Email", type: "email" },
    { name: "phone", label: "Telepon", type: "text" },
    { name: "tax_id", label: "NPWP", type: "text" },
    { name: "address", label: "Alamat", type: "textarea" },
    { name: "notes", label: "Catatan", type: "textarea" },
    { name: "is_active", label: "Aktif", type: "checkbox" },
  ],
  detailFields: [
    { label: "Kode", accessor: "code" },
    { label: "Nama", accessor: "name" },
    { label: "Nama Kontak", accessor: "contact_name" },
    { label: "Email", accessor: "email" },
    { label: "Telepon", accessor: "phone" },
    { label: "NPWP", accessor: "tax_id" },
    { label: "Alamat", accessor: "address" },
    { label: "Catatan", accessor: "notes" },
    { label: "Status", accessor: "is_active", type: "boolean" },
    { label: "Dibuat", accessor: "created_at", type: "datetime" },
    { label: "Diperbarui", accessor: "updated_at", type: "datetime" },
  ],
  audit: {
    auditableType: "App\\Models\\Vendor",
    permission: "audit.view",
  },
  getCreateDefaults: () => ({ is_active: true, code: "" }),
  mapToPayload: (values) => {
    const payload = { ...values };

    if (!payload.code) {
      delete payload.code;
    }

    return payload;
  },
  canDelete: () => true,
  canEdit: () => true,
};
