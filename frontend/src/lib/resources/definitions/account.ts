import type { ResourceDef } from "../types";
import { activeColumn, currencyColumn, linkColumn } from "../columns";

const basePath = "/dashboard/accounts";

export const accountResource: ResourceDef = {
  resource: "/accounts",
  basePath,
  label: "Kas/Bank",
  labelPlural: "Kas/Bank",
  permissions: {
    view: "account.view",
    manage: "account.manage",
    create: "account.manage",
    delete: "account.manage",
  },
  titleField: (row) => String(row.name ?? row.code ?? "Akun"),
  listColumns: [
    linkColumn("code", "Kode", basePath),
    linkColumn("name", "Nama", basePath, (row) => String(row.name ?? "-")),
    { accessorKey: "type", header: "Tipe" },
    { accessorKey: "bank_name", header: "Bank" },
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
        { value: "cash", label: "Kas" },
        { value: "bank", label: "Bank" },
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
    {
      name: "type",
      label: "Tipe",
      type: "select",
      required: true,
      options: [
        { value: "cash", label: "Kas" },
        { value: "bank", label: "Bank" },
      ],
    },
    {
      name: "bank_name",
      label: "Nama Bank",
      type: "text",
      visibleWhen: (values) => values.type === "bank",
      helperText: "Wajib diisi untuk rekening bank.",
    },
    {
      name: "account_number",
      label: "No. Rekening",
      type: "text",
      visibleWhen: (values) => values.type === "bank",
    },
    {
      name: "account_holder",
      label: "Pemilik Rekening",
      type: "text",
      visibleWhen: (values) => values.type === "bank",
    },
    {
      name: "balance",
      label: "Saldo",
      type: "currency",
      readOnly: true,
      showOnEditOnly: true,
      helperText: "Saldo dihitung dari buku besar — tidak dapat diedit di sini.",
    },
    { name: "is_active", label: "Aktif", type: "checkbox" },
  ],
  detailFields: [
    { label: "Kode", accessor: "code" },
    { label: "Nama", accessor: "name" },
    {
      label: "Tipe",
      accessor: (row) => {
        const type = String(row.type ?? "");

        return type === "bank" ? "Bank" : type === "cash" ? "Kas" : type;
      },
    },
    { label: "Nama Bank", accessor: "bank_name" },
    { label: "No. Rekening", accessor: "account_number" },
    { label: "Pemilik Rekening", accessor: "account_holder" },
    { label: "Saldo", accessor: "balance", type: "currency" },
    { label: "Status", accessor: "is_active", type: "boolean" },
    { label: "Dibuat", accessor: "created_at", type: "datetime" },
    { label: "Diperbarui", accessor: "updated_at", type: "datetime" },
  ],
  audit: {
    auditableType: "App\\Models\\Account",
    permission: "audit.view",
  },
  getCreateDefaults: () => ({
    type: "bank",
    is_active: true,
    bank_name: "",
    account_number: "",
    account_holder: "",
  }),
  mapToForm: (row) => ({
    ...row,
    balance: row.balance ?? "0",
  }),
  mapToPayload: (values) => {
    const payload = { ...values };

    delete payload.balance;

    if (payload.type === "cash") {
      payload.bank_name = null;
      payload.account_number = null;
      payload.account_holder = null;
    }

    return payload;
  },
  canDelete: () => true,
  canEdit: () => true,
};
