import { roleLabel, SIMA_ROLE_OPTIONS } from "@/lib/auth/roles";

import { activeColumn, linkColumn } from "../columns";
import type { ResourceDef } from "../types";

const basePath = "/dashboard/settings";

export const userResource: ResourceDef = {
  resource: "/users",
  basePath,
  label: "Pengguna",
  labelPlural: "Pengguna",
  permissions: {
    view: "user.manage",
    manage: "user.manage",
    create: "user.manage",
    delete: "user.manage",
  },
  titleField: (row) => String(row.name ?? row.email ?? "Pengguna"),
  listColumns: [
    linkColumn("name", "Nama", basePath),
    linkColumn("email", "Email", basePath, (row) => String(row.email ?? "-")),
    {
      accessorKey: "role",
      header: "Role",
      cell: ({ row }) => roleLabel(String((row.original.roles as string[] | undefined)?.[0] ?? "")),
    },
    { accessorKey: "phone", header: "Telepon" },
    activeColumn(),
  ],
  filters: [
    {
      name: "role",
      label: "Role",
      type: "select",
      allLabel: "Semua role",
      options: SIMA_ROLE_OPTIONS.map((option) => ({ value: option.value, label: option.label })),
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
    { name: "name", label: "Nama", type: "text", required: true },
    { name: "email", label: "Email", type: "email", required: true },
    { name: "phone", label: "Telepon", type: "text" },
    {
      name: "role",
      label: "Role",
      type: "select",
      required: true,
      options: SIMA_ROLE_OPTIONS.map((option) => ({ value: option.value, label: option.label })),
    },
    {
      name: "donor_id",
      label: "Tautkan Donatur",
      type: "relation",
      helperText: "Wajib untuk portal: pilih master donatur yang akan login dengan akun ini.",
      visibleWhen: (values) => values.role === "donatur",
      relation: {
        resource: "/donors",
        labelKey: "name",
        params: { is_active: 1, per_page: 100 },
      },
    },
    {
      name: "password",
      label: "Password",
      type: "password",
      required: true,
      showOnCreateOnly: true,
      placeholder: "Min. 8 karakter",
    },
    { name: "is_active", label: "Aktif", type: "checkbox" },
  ],
  detailFields: [
    { label: "Nama", accessor: "name" },
    { label: "Email", accessor: "email" },
    { label: "Telepon", accessor: "phone" },
    {
      label: "Role",
      accessor: (row) => roleLabel(String((row.roles as string[] | undefined)?.[0] ?? "")),
    },
    {
      label: "Donatur Tertaut",
      accessor: (row) => {
        const donor = row.donor as { code?: string; name?: string } | undefined;

        if (!donor) {
          return "-";
        }

        return `${donor.code ?? ""} ${donor.name ?? ""}`.trim() || "-";
      },
    },
    { label: "Status", accessor: "is_active", type: "boolean" },
    { label: "Dibuat", accessor: "created_at", type: "datetime" },
    { label: "Diperbarui", accessor: "updated_at", type: "datetime" },
  ],
  getCreateDefaults: () => ({ is_active: true, role: "bendahara" }),
  mapToForm: (row) => ({
    ...row,
    role: (row.roles as string[] | undefined)?.[0] ?? "",
    donor_id: row.donor_id ? String(row.donor_id) : "",
  }),
  mapToPayload: (values) => {
    const payload: Record<string, unknown> = { ...values };

    if (!payload.password) {
      delete payload.password;
    }

    if (payload.role === "donatur") {
      payload.donor_id = values.donor_id ? Number(values.donor_id) : null;
    } else {
      delete payload.donor_id;
    }

    return payload;
  },
  canDelete: (row) => Boolean(row.is_active),
  canEdit: () => true,
};
