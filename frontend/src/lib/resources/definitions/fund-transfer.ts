import { normalizeAmountString } from "@/lib/format/amount";

import { currencyColumn, dateColumn, linkColumn, nestedNameColumn, statusColumn } from "../columns";
import type { ResourceDef } from "../types";

const basePath = "/dashboard/fund-transfers";

export const fundTransferResource: ResourceDef = {
  resource: "/fund-transfers",
  basePath,
  label: "Transfer Dana Amanah",
  labelPlural: "Transfer Dana Amanah",
  permissions: {
    view: "transfer.view",
    manage: "transfer.manage",
    create: "transfer.manage",
  },
  titleField: (row) => String(row.transfer_number ?? `Transfer Dana #${row.id}`),
  listColumns: [
    linkColumn("transfer_number", "No. Transfer", basePath, (row) => String(row.transfer_number ?? `#${row.id}`)),
    dateColumn("transfer_date", "Tanggal"),
    currencyColumn("amount", "Nominal"),
    statusColumn(),
    nestedNameColumn("from_fund", "Dari Dana"),
    nestedNameColumn("to_fund", "Ke Dana"),
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
      name: "from_fund_id",
      label: "Dari Dana Amanah",
      type: "relation",
      required: true,
      relation: { resource: "/funds", labelKey: "name", params: { is_active: 1, per_page: 100 } },
    },
    {
      name: "to_fund_id",
      label: "Ke Dana Amanah",
      type: "relation",
      required: true,
      relation: { resource: "/funds", labelKey: "name", params: { is_active: 1, per_page: 100 } },
    },
    { name: "amount", label: "Nominal", type: "currency", required: true },
    { name: "reference_number", label: "No. Referensi", type: "text" },
    {
      name: "description",
      label: "Alasan Transfer",
      type: "textarea",
      required: true,
      helperText: "Transfer ini hanya mengubah peruntukan Dana Amanah. Saldo kas/bank tidak berubah.",
    },
  ],
  detailFields: [
    { label: "No. Transfer", accessor: "transfer_number" },
    { label: "Tanggal", accessor: "transfer_date", type: "date" },
    { label: "Status", accessor: "status", type: "status" },
    { label: "Nominal", accessor: "amount", type: "currency" },
    {
      label: "Dari Dana Amanah",
      accessor: (row) => (row.from_fund as { name?: string } | undefined)?.name ?? "-",
    },
    {
      label: "Ke Dana Amanah",
      accessor: (row) => (row.to_fund as { name?: string } | undefined)?.name ?? "-",
    },
    { label: "No. Referensi", accessor: "reference_number" },
    { label: "Alasan Transfer", accessor: "description" },
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
      confirmTitle: "Post transfer Dana Amanah?",
      confirmDescription: "Saldo Dana Amanah akan dipindahkan dari sumber ke tujuan. Saldo kas/bank tidak berubah.",
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
      confirmTitle: "Reverse transfer Dana Amanah?",
      confirmDescription: "Entri ledger akan dibatalkan melalui reversal.",
    },
  ],
  audit: {
    auditableType: "App\\Models\\FundTransfer",
    permission: "audit.view",
  },
  getCreateDefaults: () => ({
    transfer_date: new Date().toISOString().slice(0, 10),
    amount: "",
  }),
  mapToPayload: (values) => ({
    transfer_date: values.transfer_date,
    from_fund_id: Number(values.from_fund_id),
    to_fund_id: Number(values.to_fund_id),
    amount: normalizeAmountString(values.amount as string),
    reference_number: values.reference_number || null,
    description: values.description,
  }),
  canEdit: () => false,
  canDelete: () => false,
};
