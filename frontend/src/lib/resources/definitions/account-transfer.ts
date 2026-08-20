import { normalizeAmountString } from "@/lib/format/amount";

import { currencyColumn, dateColumn, linkColumn, nestedNameColumn, statusColumn } from "../columns";
import type { ResourceDef } from "../types";

const basePath = "/dashboard/transfers";

export const accountTransferResource: ResourceDef = {
  resource: "/account-transfers",
  basePath,
  label: "Transfer",
  labelPlural: "Transfer",
  permissions: {
    view: "transfer.view",
    manage: "transfer.manage",
    create: "transfer.manage",
  },
  titleField: (row) => String(row.transfer_number ?? `Transfer #${row.id}`),
  listColumns: [
    linkColumn("transfer_number", "No. Transfer", basePath, (row) => String(row.transfer_number ?? `#${row.id}`)),
    dateColumn("transfer_date", "Tanggal"),
    currencyColumn("amount", "Nominal"),
    statusColumn(),
    nestedNameColumn("from_account", "Dari"),
    nestedNameColumn("to_account", "Ke"),
  ],
  filters: [
    {
      name: "status",
      label: "Status",
      type: "select",
      allLabel: "Semua status",
      options: [
        { value: "draft", label: "Draft" },
        { value: "posted", label: "Posted" },
        { value: "reversed", label: "Reversed" },
      ],
    },
  ],
  defaultSort: { field: "transfer_date", direction: "desc" },
  formFields: [
    { name: "transfer_date", label: "Tanggal Transfer", type: "date", required: true },
    {
      name: "from_account_id",
      label: "Dari Rekening",
      type: "relation",
      required: true,
      relation: { resource: "/accounts", labelKey: "name", params: { is_active: 1, per_page: 100 } },
    },
    {
      name: "to_account_id",
      label: "Ke Rekening",
      type: "relation",
      required: true,
      relation: { resource: "/accounts", labelKey: "name", params: { is_active: 1, per_page: 100 } },
    },
    { name: "amount", label: "Nominal", type: "currency", required: true },
    { name: "reference_number", label: "No. Referensi", type: "text" },
    {
      name: "description",
      label: "Keterangan",
      type: "textarea",
      helperText: "Transfer hanya memindahkan kas antar rekening — saldo Dana Amanah tidak berubah.",
    },
  ],
  detailFields: [
    { label: "No. Transfer", accessor: "transfer_number" },
    { label: "Tanggal", accessor: "transfer_date", type: "date" },
    { label: "Status", accessor: "status", type: "status" },
    { label: "Nominal", accessor: "amount", type: "currency" },
    {
      label: "Dari Rekening",
      accessor: (row) => (row.from_account as { name?: string } | undefined)?.name ?? "-",
    },
    {
      label: "Ke Rekening",
      accessor: (row) => (row.to_account as { name?: string } | undefined)?.name ?? "-",
    },
    { label: "No. Referensi", accessor: "reference_number" },
    { label: "Keterangan", accessor: "description" },
    { label: "Diposting", accessor: "posted_at", type: "datetime" },
    { label: "Alasan Reversal", accessor: "reversal_reason" },
    { label: "Dibuat", accessor: "created_at", type: "datetime" },
  ],
  workflow: [
    {
      action: "post",
      label: "Post ke Ledger",
      permission: "transfer.post",
      statuses: ["draft"],
      confirmTitle: "Post transfer?",
      confirmDescription: "Saldo akan dipindahkan antar rekening kas/bank. Saldo Dana Amanah tidak berubah.",
    },
    {
      action: "reverse",
      label: "Reverse",
      permission: "transfer.reverse",
      statuses: ["posted"],
      requiresReason: true,
      reasonLabel: "Alasan reversal",
      reasonRequired: true,
      variant: "destructive",
      confirmTitle: "Reverse transfer?",
      confirmDescription: "Entri ledger akan dibatalkan melalui reversal.",
    },
  ],
  audit: {
    auditableType: "App\\Models\\AccountTransfer",
    permission: "audit.view",
  },
  getCreateDefaults: () => ({
    transfer_date: new Date().toISOString().slice(0, 10),
    amount: "",
  }),
  mapToPayload: (values) => ({
    transfer_date: values.transfer_date,
    from_account_id: Number(values.from_account_id),
    to_account_id: Number(values.to_account_id),
    amount: normalizeAmountString(values.amount as string),
    reference_number: values.reference_number || null,
    description: values.description || null,
  }),
  canEdit: () => false,
  canDelete: () => false,
};
