import { normalizeAmountString } from "@/lib/format/amount";

import { currencyColumn, dateColumn, linkColumn, nestedNameColumn, statusColumn } from "../columns";
import type { ResourceDef } from "../types";

const basePath = "/dashboard/bank-fees";

const feeTypeLabels: Record<string, string> = {
  admin: "Administrasi",
  transfer: "Transfer",
  tax: "Pajak",
  other: "Lainnya",
};

function feeTypeLabel(value: unknown): string {
  const key = String(value ?? "");

  return (feeTypeLabels[key] ?? key) || "-";
}

export const bankFeeResource: ResourceDef = {
  resource: "/bank-fees",
  basePath,
  label: "Biaya Bank",
  labelPlural: "Biaya Bank",
  permissions: {
    view: "bankfee.view",
    manage: "bankfee.manage",
    create: "bankfee.manage",
  },
  titleField: (row) => String(row.fee_number ?? `Biaya Bank #${row.id}`),
  listColumns: [
    linkColumn("fee_number", "No. Biaya", basePath, (row) => String(row.fee_number ?? `#${row.id}`)),
    dateColumn("fee_date", "Tanggal"),
    currencyColumn("amount", "Nominal"),
    statusColumn(),
    {
      accessorKey: "fee_type",
      header: "Jenis",
      cell: ({ row }) => feeTypeLabel(row.original.fee_type),
    },
    nestedNameColumn("account", "Akun Bank"),
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
        { value: "deferred", label: "Deferred" },
        { value: "reversed", label: "Reversed" },
      ],
    },
  ],
  defaultSort: { field: "fee_date", direction: "desc" },
  formFields: [
    { name: "fee_date", label: "Tanggal Biaya", type: "date", required: true },
    {
      name: "account_id",
      label: "Rekening Bank",
      type: "relation",
      required: true,
      relation: {
        resource: "/accounts",
        labelKey: "name",
        params: { type: "bank", is_active: 1, per_page: 100 },
      },
    },
    {
      name: "fund_id",
      label: "Dana Amanah",
      type: "relation",
      helperText: "Kosongkan untuk memakai Dana Operasional (default). Dana restricted tidak diizinkan.",
      relation: {
        resource: "/funds",
        labelKey: "name",
        params: { is_active: 1, per_page: 100 },
      },
    },
    {
      name: "fee_type",
      label: "Jenis Biaya",
      type: "select",
      required: true,
      options: [
        { value: "admin", label: "Administrasi" },
        { value: "transfer", label: "Transfer" },
        { value: "tax", label: "Pajak" },
        { value: "other", label: "Lainnya" },
      ],
    },
    { name: "amount", label: "Nominal", type: "currency", required: true },
    { name: "description", label: "Keterangan", type: "textarea" },
  ],
  detailFields: [
    { label: "No. Biaya", accessor: "fee_number" },
    { label: "Tanggal", accessor: "fee_date", type: "date" },
    { label: "Status", accessor: "status", type: "status" },
    { label: "Jenis Biaya", accessor: (row) => feeTypeLabel(row.fee_type) },
    { label: "Nominal", accessor: "amount", type: "currency" },
    { label: "Rekening Bank", accessor: (row) => (row.account as { name?: string } | undefined)?.name ?? "-" },
    { label: "Dana Amanah", accessor: (row) => (row.fund as { name?: string } | undefined)?.name ?? "-" },
    { label: "Keterangan", accessor: "description" },
    { label: "Diposting", accessor: "posted_at", type: "datetime" },
    {
      label: "Kewajiban Operasional",
      accessor: (row) => {
        const liability = row.operational_liability as { liability_number?: string } | undefined;

        return liability?.liability_number ?? (row.operational_liability_id ? `#${row.operational_liability_id}` : "-");
      },
    },
    { label: "Alasan Reversal", accessor: "reversal_reason" },
    { label: "Dibuat", accessor: "created_at", type: "datetime" },
  ],
  workflow: [
    {
      action: "post",
      label: "Post ke Ledger",
      permission: "bankfee.post",
      statuses: ["draft"],
      confirmTitle: "Post biaya bank?",
      confirmDescription:
        "Biaya akan dipotong dari rekening bank dan Dana Amanah terkait. Jika saldo tidak cukup, status menjadi deferred.",
    },
    {
      action: "reverse",
      label: "Reverse",
      permission: "bankfee.reverse",
      statuses: ["posted"],
      requiresReason: true,
      reasonLabel: "Alasan reversal",
      reasonRequired: true,
      variant: "destructive",
      confirmTitle: "Reverse biaya bank?",
      confirmDescription: "Entri ledger akan dibatalkan melalui reversal.",
    },
  ],
  attachments: {
    attachableType: "bank_fee",
    managePermission: "attachment.manage",
  },
  audit: {
    auditableType: "App\\Models\\BankFee",
    permission: "audit.view",
  },
  getCreateDefaults: () => ({
    fee_date: new Date().toISOString().slice(0, 10),
    amount: "",
    fee_type: "admin",
  }),
  mapToPayload: (values) => ({
    fee_date: values.fee_date,
    account_id: Number(values.account_id),
    fund_id: values.fund_id ? Number(values.fund_id) : null,
    fee_type: values.fee_type,
    amount: normalizeAmountString(values.amount as string),
    description: values.description || null,
  }),
  canEdit: () => false,
  canDelete: () => false,
};
