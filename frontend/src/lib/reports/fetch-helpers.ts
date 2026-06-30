import { apiGet } from "@/lib/api/client";
import type { ListParams } from "@/lib/api/types";

import type { ReportFetchResult } from "./types";

export async function fetchReportRows(path: string, params: ListParams = {}): Promise<ReportFetchResult> {
  const response = await apiGet<Array<Record<string, unknown>>>(path, params);

  return {
    rows: response.data,
    pagination: response.meta?.pagination,
  };
}

export async function fetchFundBalances(): Promise<ReportFetchResult> {
  const response = await apiGet<{ rows: Array<Record<string, unknown>>; total: string }>("/reports/fund-balances");

  return {
    rows: response.data.rows,
    summary: { total: response.data.total },
  };
}

export async function fetchLedgerReport(params: ListParams): Promise<ReportFetchResult> {
  return fetchReportRows("/reports/ledger", params);
}

export async function fetchCashAccounts(): Promise<Array<Record<string, unknown>>> {
  const response = await apiGet<Array<Record<string, unknown>>>("/accounts", {
    type: "cash",
    per_page: 100,
    is_active: 1,
  });

  return response.data;
}

export async function fetchBankAccounts(): Promise<Array<Record<string, unknown>>> {
  const response = await apiGet<Array<Record<string, unknown>>>("/accounts", {
    type: "bank",
    per_page: 100,
    is_active: 1,
  });

  return response.data;
}

export async function fetchCombinedTransactions(
  params: ListParams,
  sources: Array<{ path: string; type: string; numberKey: string; dateKey: string }>,
): Promise<ReportFetchResult> {
  const responses = await Promise.all(
    sources.map(async (source) => {
      const result = await fetchReportRows(source.path, { ...params, per_page: 100, page: 1 });

      return result.rows.map((row) => ({
        ...row,
        document_type: source.type,
        document_number: row[source.numberKey],
        document_date: row[source.dateKey],
      }));
    }),
  );

  const rows = responses
    .flat()
    .sort((left, right) => String(right.document_date ?? "").localeCompare(String(left.document_date ?? "")));

  return { rows };
}

function approvalFilterParams(params: ListParams): ListParams {
  const filterParams: ListParams = {};

  if (params.from) {
    filterParams.from = params.from;
  }

  if (params.to) {
    filterParams.to = params.to;
  }

  return filterParams;
}

export async function fetchApprovalReport(params: ListParams): Promise<ReportFetchResult> {
  const status = params.status ? String(params.status) : undefined;
  const filterParams = approvalFilterParams(params);
  const listParams = { ...filterParams, per_page: 100, page: 1 };

  let receiptStatuses: string[] = [];
  let disbursementStatuses: string[] = [];

  if (!status) {
    receiptStatuses = ["submitted"];
    disbursementStatuses = ["submitted", "verified"];
  } else if (status === "verified") {
    disbursementStatuses = ["verified"];
  } else {
    receiptStatuses = [status];
    disbursementStatuses = [status];
  }

  const [receipts, disbursements] = await Promise.all([
    Promise.all(receiptStatuses.map((value) => fetchReportRows("/receipts", { ...listParams, status: value }))),
    Promise.all(
      disbursementStatuses.map((value) => fetchReportRows("/disbursements", { ...listParams, status: value })),
    ),
  ]);

  const rows = [
    ...receipts.flatMap((result) =>
      result.rows.map((row) => ({
        ...row,
        document_type: "Penerimaan",
        document_number: row.receipt_number,
        document_date: row.receipt_date,
      })),
    ),
    ...disbursements.flatMap((result) =>
      result.rows.map((row) => ({
        ...row,
        document_type: "Pengeluaran",
        document_number: row.disbursement_number,
        document_date: row.disbursement_date,
      })),
    ),
  ].sort((left, right) => String(right.document_date ?? "").localeCompare(String(left.document_date ?? "")));

  return { rows };
}
