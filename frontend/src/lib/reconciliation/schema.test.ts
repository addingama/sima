import { describe, expect, it } from "vitest";

import { addReconciliationLineSchema, createReconciliationSchema } from "./schema";

const validReconciliation = {
  account_id: "1",
  period_start: "2026-09-01",
  period_end: "2026-09-30",
  statement_balance: "1.500.000",
  notes: "Rekonsiliasi September",
};

describe("createReconciliationSchema", () => {
  it("menerima rekonsiliasi yang valid dan saldo nol", () => {
    expect(createReconciliationSchema.safeParse(validReconciliation).success).toBe(true);
    expect(createReconciliationSchema.safeParse({ ...validReconciliation, statement_balance: "0" }).success).toBe(true);
  });

  it.each([
    ["rekening kosong", { ...validReconciliation, account_id: "" }],
    ["tanggal awal kosong", { ...validReconciliation, period_start: "" }],
    ["tanggal akhir kosong", { ...validReconciliation, period_end: "" }],
    ["saldo kosong", { ...validReconciliation, statement_balance: "" }],
    ["saldo negatif", { ...validReconciliation, statement_balance: "-1" }],
    ["periode terbalik", { ...validReconciliation, period_start: "2026-10-01", period_end: "2026-09-30" }],
  ])("menolak %s", (_, payload) => {
    expect(createReconciliationSchema.safeParse(payload).success).toBe(false);
  });
});

describe("addReconciliationLineSchema", () => {
  it("menerima baris minimal dan baris lengkap", () => {
    expect(addReconciliationLineSchema.safeParse({ is_matched: false }).success).toBe(true);
    expect(
      addReconciliationLineSchema.safeParse({
        statement_date: "2026-09-22",
        statement_ref: "BANK-001",
        statement_amount: "250000",
        ledger_entry_id: "10",
        is_matched: true,
        note: "Cocok",
      }).success,
    ).toBe(true);
  });

  it("mewajibkan flag matched berupa boolean", () => {
    expect(addReconciliationLineSchema.safeParse({}).success).toBe(false);
    expect(addReconciliationLineSchema.safeParse({ is_matched: "true" }).success).toBe(false);
  });
});
