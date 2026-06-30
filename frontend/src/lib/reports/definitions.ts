import {
  currencyColumn,
  dateColumn,
  datetimeColumn,
  nestedColumn,
  statusColumn,
  textColumn,
} from "@/lib/reports/columns";
import {
  fetchApprovalReport,
  fetchFundBalances,
  fetchLedgerReport,
  fetchReportRows,
} from "@/lib/reports/fetch-helpers";
import type { ReportDef } from "@/lib/reports/types";

export const fundBalancesReport: ReportDef = {
  id: "fund-balances",
  title: "Saldo Dana Amanah",
  description: "Saldo seluruh Dana Amanah dari buku besar Amanah Ledger.",
  path: "/dashboard/reports/fund-balances",
  paginated: false,
  columns: [
    textColumn("code", "Kode"),
    textColumn("name", "Nama"),
    textColumn("type", "Tipe"),
    currencyColumn("balance", "Saldo"),
  ],
  filters: [
    {
      name: "type",
      label: "Tipe Dana",
      type: "select",
      allLabel: "Semua tipe",
      options: [
        { value: "restricted", label: "Restricted" },
        { value: "unrestricted", label: "Unrestricted" },
        { value: "operational", label: "Operational" },
      ],
    },
  ],
  groupByOptions: [{ value: "type", label: "Tipe Dana" }],
  fetchData: async (params) => {
    const result = await fetchFundBalances();
    const type = params.type ? String(params.type) : "";

    return {
      ...result,
      rows: type ? result.rows.filter((row) => row.type === type) : result.rows,
    };
  },
};

export const fundMutationReport: ReportDef = {
  id: "fund-mutation",
  title: "Mutasi Dana Amanah",
  description: "Mutasi entri ledger per Dana Amanah.",
  path: "/dashboard/reports/fund-mutation",
  paginated: true,
  columns: [
    datetimeColumn("created_at", "Waktu"),
    textColumn("transaction_type", "Tipe Transaksi"),
    currencyColumn("debit", "Debit"),
    currencyColumn("credit", "Kredit"),
    textColumn("reference", "Referensi"),
  ],
  filters: [
    {
      name: "fund_id",
      label: "Dana Amanah",
      type: "relation",
      required: true,
      relation: { resource: "/funds", labelKey: "name", params: { per_page: 100 } },
    },
    { name: "from", label: "Dari Tanggal", type: "date" },
    { name: "to", label: "Sampai Tanggal", type: "date" },
  ],
  groupByOptions: [{ value: "transaction_type", label: "Tipe Transaksi" }],
  fetchData: async (params) => {
    if (!params.fund_id) {
      return { rows: [] };
    }

    return fetchLedgerReport({
      ...params,
      fund_id: params.fund_id,
      sort: "created_at",
      direction: "desc",
    });
  },
};

const ledgerColumns = [
  datetimeColumn("created_at", "Waktu"),
  textColumn("transaction_type", "Tipe Transaksi"),
  textColumn("ledger_account_type", "Dimensi"),
  currencyColumn("debit", "Debit"),
  currencyColumn("credit", "Kredit"),
  textColumn("reference", "Referensi"),
];

function accountBookReport(type: "cash" | "bank", id: string, title: string, path: string): ReportDef {
  return {
    id,
    title,
    description: `Mutasi ledger untuk rekening ${type === "cash" ? "kas" : "bank"}.`,
    path,
    paginated: true,
    columns: ledgerColumns,
    filters: [
      {
        name: "account_id",
        label: type === "cash" ? "Rekening Kas" : "Rekening Bank",
        type: "relation",
        required: true,
        relation: {
          resource: "/accounts",
          labelKey: "name",
          params: { type, per_page: 100, is_active: 1 },
        },
      },
      { name: "from", label: "Dari Tanggal", type: "date" },
      { name: "to", label: "Sampai Tanggal", type: "date" },
    ],
    groupByOptions: [{ value: "transaction_type", label: "Tipe Transaksi" }],
    fetchData: async (params) => {
      if (!params.account_id) {
        return { rows: [] };
      }

      return fetchLedgerReport({
        ...params,
        account_id: params.account_id,
        sort: "created_at",
        direction: "desc",
      });
    },
  };
}

export const cashBookReport = accountBookReport("cash", "cash-book", "Buku Kas", "/dashboard/reports/cash-book");

export const bankBookReport = accountBookReport("bank", "bank-book", "Buku Bank", "/dashboard/reports/bank-book");

export const ledgerReport: ReportDef = {
  id: "ledger",
  title: "Ledger",
  description: "Buku besar Amanah Ledger dengan filter lengkap.",
  path: "/dashboard/reports/ledger",
  paginated: true,
  columns: ledgerColumns,
  filters: [
    {
      name: "fund_id",
      label: "Dana Amanah",
      type: "relation",
      relation: { resource: "/funds", labelKey: "name", params: { per_page: 100 } },
    },
    {
      name: "account_id",
      label: "Akun Kas/Bank",
      type: "relation",
      relation: { resource: "/accounts", labelKey: "name", params: { per_page: 100 } },
    },
    {
      name: "transaction_type",
      label: "Tipe Transaksi",
      type: "select",
      allLabel: "Semua",
      options: [
        { value: "receipt", label: "Receipt" },
        { value: "allocation", label: "Allocation" },
        { value: "disbursement", label: "Disbursement" },
        { value: "bank_fee", label: "Bank Fee" },
        { value: "reversal", label: "Reversal" },
      ],
    },
    { name: "from", label: "Dari Tanggal", type: "date" },
    { name: "to", label: "Sampai Tanggal", type: "date" },
    { name: "q", label: "Cari Referensi", type: "text", placeholder: "Referensi..." },
  ],
  groupByOptions: [
    { value: "transaction_type", label: "Tipe Transaksi" },
    { value: "ledger_account_type", label: "Dimensi Ledger" },
  ],
  fetchData: fetchLedgerReport,
};

const transactionColumns = [
  textColumn("document_type", "Jenis Dokumen"),
  textColumn("document_number", "No. Dokumen"),
  dateColumn("document_date", "Tanggal"),
  currencyColumn("amount", "Nominal"),
  statusColumn("status"),
  nestedColumn("account_name", "Akun", (row) => (row.account as { name?: string } | undefined)?.name ?? "-"),
];

export const byProgramReport: ReportDef = {
  id: "by-program",
  title: "Per Event / Program",
  description: "Laporan pengeluaran per program. Filter penerimaan per program membutuhkan endpoint alokasi terpisah.",
  path: "/dashboard/reports/by-program",
  paginated: false,
  columns: transactionColumns,
  filters: [
    {
      name: "program_id",
      label: "Program",
      type: "relation",
      required: true,
      relation: { resource: "/programs", labelKey: "name", params: { per_page: 100 } },
    },
    { name: "from", label: "Dari Tanggal", type: "date" },
    { name: "to", label: "Sampai Tanggal", type: "date" },
  ],
  groupByOptions: [
    { value: "document_type", label: "Jenis Dokumen" },
    { value: "status", label: "Status" },
  ],
  fetchData: async (params) => {
    if (!params.program_id) {
      return { rows: [] };
    }

    const disbursements = await fetchReportRows("/disbursements", {
      ...params,
      per_page: 100,
      page: 1,
      sort: "disbursement_date",
      direction: "desc",
    });

    return {
      rows: disbursements.rows.map((row) => ({
        ...row,
        document_type: "Pengeluaran",
        document_number: row.disbursement_number,
        document_date: row.disbursement_date,
      })),
    };
  },
};

export const byDonorReport: ReportDef = {
  id: "by-donor",
  title: "Per Donatur",
  description: "Laporan penerimaan dana per donatur.",
  path: "/dashboard/reports/by-donor",
  paginated: true,
  columns: [
    textColumn("receipt_number", "No. Penerimaan"),
    dateColumn("receipt_date", "Tanggal"),
    currencyColumn("amount", "Nominal"),
    statusColumn("status"),
    nestedColumn("account_name", "Akun", (row) => (row.account as { name?: string } | undefined)?.name ?? "-"),
  ],
  filters: [
    {
      name: "donor_id",
      label: "Donatur",
      type: "relation",
      required: true,
      relation: { resource: "/donors", labelKey: "name", params: { per_page: 100 } },
    },
    { name: "from", label: "Dari Tanggal", type: "date" },
    { name: "to", label: "Sampai Tanggal", type: "date" },
  ],
  groupByOptions: [{ value: "status", label: "Status" }],
  fetchData: async (params) => {
    if (!params.donor_id) {
      return { rows: [] };
    }

    return fetchReportRows("/receipts", { ...params, sort: "receipt_date", direction: "desc" });
  },
};

export const byVendorReport: ReportDef = {
  id: "by-vendor",
  title: "Per Vendor",
  description: "Laporan pengeluaran berdasarkan penerima/vendor (field payee). Modul vendor master belum tersedia.",
  path: "/dashboard/reports/by-vendor",
  paginated: true,
  columns: [
    textColumn("disbursement_number", "No. Pengeluaran"),
    dateColumn("disbursement_date", "Tanggal"),
    textColumn("payee", "Penerima/Vendor"),
    textColumn("category", "Kategori"),
    currencyColumn("amount", "Nominal"),
    statusColumn("status"),
  ],
  filters: [
    { name: "q", label: "Nama Vendor/Penerima", type: "text", placeholder: "Cari payee..." },
    { name: "from", label: "Dari Tanggal", type: "date" },
    { name: "to", label: "Sampai Tanggal", type: "date" },
  ],
  groupByOptions: [
    { value: "payee", label: "Penerima/Vendor" },
    { value: "category", label: "Kategori" },
  ],
  fetchData: (params) => fetchReportRows("/disbursements", { ...params, sort: "disbursement_date", direction: "desc" }),
};

export const approvalReport: ReportDef = {
  id: "approval-report",
  title: "Approval",
  description: "Antrian dan riwayat dokumen penerimaan/pengeluaran yang membutuhkan tindakan approval.",
  path: "/dashboard/reports/approval",
  paginated: false,
  columns: [
    textColumn("document_type", "Jenis"),
    textColumn("document_number", "No. Dokumen"),
    dateColumn("document_date", "Tanggal"),
    currencyColumn("amount", "Nominal"),
    statusColumn("status"),
    nestedColumn("account_name", "Akun", (row) => (row.account as { name?: string } | undefined)?.name ?? "-"),
  ],
  filters: [
    {
      name: "status",
      label: "Status",
      type: "select",
      allLabel: "Antrian default",
      options: [
        { value: "submitted", label: "Submitted" },
        { value: "verified", label: "Verified" },
        { value: "approved", label: "Approved" },
        { value: "rejected", label: "Rejected" },
      ],
    },
    { name: "from", label: "Dari Tanggal", type: "date" },
    { name: "to", label: "Sampai Tanggal", type: "date" },
  ],
  groupByOptions: [
    { value: "document_type", label: "Jenis Dokumen" },
    { value: "status", label: "Status" },
  ],
  fetchData: fetchApprovalReport,
};

export const auditReport: ReportDef = {
  id: "audit-report",
  title: "Audit",
  description: "Laporan audit trail perubahan data.",
  path: "/dashboard/reports/audit",
  paginated: true,
  permission: "audit.view",
  columns: [
    textColumn("event", "Event"),
    textColumn("auditable_type", "Tipe Entitas"),
    textColumn("auditable_id", "ID Entitas"),
    nestedColumn("user_name", "Pengguna", (row) => (row.user as { name?: string } | undefined)?.name ?? "-"),
    datetimeColumn("created_at", "Waktu"),
  ],
  filters: [
    { name: "event", label: "Event", type: "text", placeholder: "created, updated..." },
    { name: "auditable_type", label: "Tipe Entitas", type: "text", placeholder: "App\\Models\\Receipt" },
    { name: "from", label: "Dari Tanggal", type: "date" },
    { name: "to", label: "Sampai Tanggal", type: "date" },
  ],
  groupByOptions: [
    { value: "event", label: "Event" },
    { value: "auditable_type", label: "Tipe Entitas" },
  ],
  fetchData: (params) => fetchReportRows("/audits", { ...params, sort: "created_at", direction: "desc" }),
};

export const allReports = [
  fundBalancesReport,
  fundMutationReport,
  cashBookReport,
  bankBookReport,
  ledgerReport,
  byProgramReport,
  byDonorReport,
  byVendorReport,
  approvalReport,
  auditReport,
];
