import { describe, expect, it } from "vitest";

import { openingBalanceWizardSchema } from "./schema";

const validOpeningBalance = {
  opening_date: "2026-09-22",
  reference: "CUTOVER-001",
  lines: [{ account_id: "1", fund_id: "2", amount: "1.000.000" }],
};

describe("openingBalanceWizardSchema", () => {
  it("menerima saldo awal yang lengkap", () => {
    expect(openingBalanceWizardSchema.safeParse(validOpeningBalance).success).toBe(true);
  });

  it.each([
    ["tanggal kosong", { ...validOpeningBalance, opening_date: "" }],
    ["tanpa baris", { ...validOpeningBalance, lines: [] }],
    ["rekening kosong", { ...validOpeningBalance, lines: [{ account_id: "", fund_id: "2", amount: "1000" }] }],
    ["dana kosong", { ...validOpeningBalance, lines: [{ account_id: "1", fund_id: "", amount: "1000" }] }],
    ["nominal nol", { ...validOpeningBalance, lines: [{ account_id: "1", fund_id: "2", amount: "0" }] }],
    ["nominal negatif", { ...validOpeningBalance, lines: [{ account_id: "1", fund_id: "2", amount: "-100" }] }],
  ])("menolak %s", (_, payload) => {
    expect(openingBalanceWizardSchema.safeParse(payload).success).toBe(false);
  });
});
