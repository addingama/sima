import { beforeEach, describe, expect, it, vi } from "vitest";

import { apiGet } from "@/lib/api/client";

import {
  fetchApprovalReport,
  fetchBankAccounts,
  fetchCashAccounts,
  fetchCombinedTransactions,
  fetchFundBalances,
  fetchGrantApplicationReport,
  fetchLedgerReport,
  fetchOpeningBalanceReport,
  fetchReportRows,
} from "./fetch-helpers";

vi.mock("@/lib/api/client", () => ({ apiGet: vi.fn() }));

function envelope(data: unknown, meta?: Record<string, unknown>) {
  return { success: true, message: "OK", data, meta };
}

describe("report fetch helpers", () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset();
  });

  it("memetakan rows dan pagination generik", async () => {
    vi.mocked(apiGet).mockResolvedValue(envelope([{ id: 1 }], { pagination: { current_page: 1 } }) as never);

    await expect(fetchReportRows("/reports/test", { page: 1 })).resolves.toEqual({
      rows: [{ id: 1 }],
      pagination: { current_page: 1 },
    });
  });

  it("meneruskan parameter laporan ledger", async () => {
    vi.mocked(apiGet).mockResolvedValue(envelope([{ id: 1 }]) as never);

    await fetchLedgerReport({ page: 2, direction: "asc" });

    expect(apiGet).toHaveBeenCalledWith("/reports/ledger", { page: 2, direction: "asc" });
  });

  it("memetakan ringkasan saldo dana dan saldo awal", async () => {
    vi.mocked(apiGet)
      .mockResolvedValueOnce(envelope({ rows: [{ code: "FND" }], total: "1000.00" }) as never)
      .mockResolvedValueOnce(
        envelope([{ id: 1 }], { pagination: { current_page: 1 }, total_amount: "2500.00", batch_count: 2 }) as never,
      );

    await expect(fetchFundBalances()).resolves.toEqual({ rows: [{ code: "FND" }], summary: { total: "1000.00" } });
    await expect(fetchOpeningBalanceReport({ page: 1 })).resolves.toEqual({
      rows: [{ id: 1 }],
      pagination: { current_page: 1 },
      summary: { total: "2500.00", batch_count: 2 },
    });
  });

  it("meratakan relasi dan default sorting laporan bantuan", async () => {
    vi.mocked(apiGet).mockResolvedValue(
      envelope(
        [
          {
            id: 1,
            program: { name: "Pendidikan" },
            assigned_verifier: { name: "Verifikator" },
            payment_method: "cash",
          },
          { id: 2, payment_method: "transfer" },
          { id: 3, payment_method: "unknown" },
        ],
        { pagination: { current_page: 1 }, summary: { total: 3 } },
      ) as never,
    );

    const result = await fetchGrantApplicationReport({ status: "approved" });

    expect(apiGet).toHaveBeenCalledWith("/reports/grant-applications", {
      status: "approved",
      sort: "created_at",
      direction: "desc",
    });
    expect(result.rows).toMatchObject([
      { program_name: "Pendidikan", verifier_name: "Verifikator", payment_label: "Tunai" },
      { program_name: "-", verifier_name: "-", payment_label: "Transfer" },
      { payment_label: "-" },
    ]);
  });

  it("mengambil akun kas dan bank dengan filter aktif", async () => {
    vi.mocked(apiGet)
      .mockResolvedValueOnce(envelope([{ id: 1, type: "cash" }]) as never)
      .mockResolvedValueOnce(envelope([{ id: 2, type: "bank" }]) as never);

    await expect(fetchCashAccounts()).resolves.toEqual([{ id: 1, type: "cash" }]);
    await expect(fetchBankAccounts()).resolves.toEqual([{ id: 2, type: "bank" }]);
    expect(apiGet).toHaveBeenNthCalledWith(1, "/accounts", { type: "cash", per_page: 100, is_active: 1 });
    expect(apiGet).toHaveBeenNthCalledWith(2, "/accounts", { type: "bank", per_page: 100, is_active: 1 });
  });

  it("menggabungkan transaksi, menambah metadata dokumen, dan mengurutkan terbaru", async () => {
    vi.mocked(apiGet)
      .mockResolvedValueOnce(envelope([{ receipt_number: "RCP-1", receipt_date: "2026-09-20" }]) as never)
      .mockResolvedValueOnce(envelope([{ disbursement_number: "DSB-1", disbursement_date: "2026-09-22" }]) as never);

    const result = await fetchCombinedTransactions({ from: "2026-09-01" }, [
      { path: "/receipts", type: "Penerimaan", numberKey: "receipt_number", dateKey: "receipt_date" },
      { path: "/disbursements", type: "Pengeluaran", numberKey: "disbursement_number", dateKey: "disbursement_date" },
    ]);

    expect(result.rows.map((row) => row.document_number)).toEqual(["DSB-1", "RCP-1"]);
    expect(apiGet).toHaveBeenCalledWith("/receipts", { from: "2026-09-01", per_page: 100, page: 1 });
  });

  it("mengambil approval default dari penerimaan submitted dan pengeluaran submitted/verified", async () => {
    vi.mocked(apiGet)
      .mockResolvedValueOnce(envelope([{ receipt_number: "RCP-1", receipt_date: "2026-09-20" }]) as never)
      .mockResolvedValueOnce(envelope([{ disbursement_number: "DSB-1", disbursement_date: "2026-09-22" }]) as never)
      .mockResolvedValueOnce(envelope([{ disbursement_number: "DSB-2", disbursement_date: "2026-09-21" }]) as never);

    const result = await fetchApprovalReport({ from: "2026-09-01", q: "diabaikan" });

    expect(result.rows.map((row) => row.document_number)).toEqual(["DSB-1", "DSB-2", "RCP-1"]);
    expect(apiGet).toHaveBeenCalledTimes(3);
    expect(apiGet).toHaveBeenCalledWith("/receipts", {
      from: "2026-09-01",
      per_page: 100,
      page: 1,
      status: "submitted",
    });
  });

  it("hanya mengambil pengeluaran verified ketika filter status verified", async () => {
    vi.mocked(apiGet).mockResolvedValue(
      envelope([{ disbursement_number: "DSB-1", disbursement_date: "2026-09-22" }]) as never,
    );

    const result = await fetchApprovalReport({ status: "verified", to: "2026-09-30" });

    expect(result.rows).toHaveLength(1);
    expect(apiGet).toHaveBeenCalledOnce();
    expect(apiGet).toHaveBeenCalledWith("/disbursements", {
      to: "2026-09-30",
      per_page: 100,
      page: 1,
      status: "verified",
    });
  });

  it("mengambil kedua jenis dokumen untuk status umum", async () => {
    vi.mocked(apiGet)
      .mockResolvedValueOnce(envelope([{ receipt_number: "RCP-1", receipt_date: "2026-09-20" }]) as never)
      .mockResolvedValueOnce(envelope([{ disbursement_number: "DSB-1", disbursement_date: "2026-09-22" }]) as never);

    const result = await fetchApprovalReport({ status: "approved" });

    expect(result.rows).toHaveLength(2);
    expect(apiGet).toHaveBeenCalledWith("/receipts", expect.objectContaining({ status: "approved" }));
    expect(apiGet).toHaveBeenCalledWith("/disbursements", expect.objectContaining({ status: "approved" }));
  });
});
