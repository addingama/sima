import { activeColumn, linkColumn } from "../columns";
import type { ResourceDef } from "../types";

const basePath = "/dashboard/donors";

const donorCodeHelper = "Format DON/2026/000001 — dibuat otomatis saat disimpan (selaras nomor dokumen SIMA).";

export const donorResource: ResourceDef = {
  resource: "/donors",
  basePath,
  label: "Donatur",
  labelPlural: "Donatur",
  permissions: {
    view: "donor.view",
    manage: "donor.manage",
    create: "donor.manage",
    delete: "donor.manage",
  },
  titleField: (row) => String(row.name ?? row.code ?? "Donatur"),
  listColumns: [
    linkColumn("code", "Kode", basePath),
    linkColumn("name", "Nama", basePath, (row) => String(row.name ?? "-")),
    { accessorKey: "type", header: "Tipe" },
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
      helperText: donorCodeHelper,
    },
    { name: "name", label: "Nama", type: "text", required: true },
    {
      name: "type",
      label: "Tipe",
      type: "select",
      required: true,
      options: [
        { value: "individu", label: "Individu" },
        { value: "lembaga", label: "Lembaga" },
      ],
    },
    { name: "email", label: "Email", type: "email" },
    { name: "phone", label: "Telepon", type: "text" },
    {
      name: "user_id",
      label: "Akun Login Portal",
      type: "relation",
      helperText:
        "Opsional. Pilih user dengan role donatur agar bisa masuk Portal Donatur. Buat akun dulu di Pengaturan → Users (admin) bila daftar masih kosong.",
      relation: {
        resource: "/donors/portal-login-options",
        labelKey: "label",
        valueKey: "id",
        params: {},
      },
    },
    { name: "identity_number", label: "No. Identitas", type: "text" },
    { name: "address", label: "Alamat", type: "textarea" },
    { name: "notes", label: "Catatan", type: "textarea" },
    { name: "is_active", label: "Aktif", type: "checkbox" },
  ],
  detailFields: [
    { label: "Kode", accessor: "code" },
    { label: "Nama", accessor: "name" },
    { label: "Tipe", accessor: "type" },
    { label: "Email", accessor: "email" },
    { label: "Telepon", accessor: "phone" },
    {
      label: "Akun Login",
      accessor: (row) => {
        const linked = row.user as { name?: string; email?: string } | undefined;

        if (linked?.name) {
          return `${linked.name}${linked.email ? ` (${linked.email})` : ""}`;
        }

        return row.user_id ? `#${row.user_id}` : "-";
      },
    },
    { label: "No. Identitas", accessor: "identity_number" },
    { label: "Alamat", accessor: "address" },
    { label: "Catatan", accessor: "notes" },
    { label: "Status", accessor: "is_active", type: "boolean" },
    { label: "Dibuat", accessor: "created_at", type: "datetime" },
    { label: "Diperbarui", accessor: "updated_at", type: "datetime" },
  ],
  audit: {
    auditableType: "App\\Models\\Donor",
    permission: "audit.view",
  },
  getCreateDefaults: () => ({ type: "individu", is_active: true, code: "", user_id: "" }),
  mapToForm: (row) => ({
    ...row,
    user_id: row.user_id ? String(row.user_id) : "",
  }),
  mapToPayload: (values) => {
    const payload = { ...values };

    if (!payload.code) {
      delete payload.code;
    }

    payload.user_id = values.user_id ? Number(values.user_id) : null;

    return payload;
  },
  canDelete: () => true,
  canEdit: () => true,
};
