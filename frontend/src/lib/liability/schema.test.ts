import { describe, expect, it } from "vitest";

import { liabilityFormSchema, settleLiabilitySchema } from "./schema";

const validLiability = {
  liability_date: "2026-09-22",
  creditor: "Vendor Amanah",
  description: "Tagihan operasional",
  fund_id: "1",
  program_id: "2",
  amount: "250.000",
  due_date: "2026-10-22",
};

describe("liabilityFormSchema", () => {
  it("menerima liabilitas yang valid", () => {
    expect(liabilityFormSchema.safeParse(validLiability).success).toBe(true);
  });

  it.each([
    ["tanggal kosong", { ...validLiability, liability_date: "" }],
    ["kreditur kosong", { ...validLiability, creditor: "" }],
    ["nominal kosong", { ...validLiability, amount: "" }],
    ["nominal nol", { ...validLiability, amount: "0" }],
  ])("menolak %s", (_, payload) => {
    expect(liabilityFormSchema.safeParse(payload).success).toBe(false);
  });

  it("mengizinkan field opsional kosong", () => {
    const payload = {
      ...validLiability,
      description: undefined,
      fund_id: undefined,
      program_id: undefined,
      due_date: undefined,
    };

    expect(liabilityFormSchema.safeParse(payload).success).toBe(true);
  });
});

describe("settleLiabilitySchema", () => {
  it("mewajibkan pengeluaran approved", () => {
    expect(settleLiabilitySchema.safeParse({ disbursement_id: "10" }).success).toBe(true);
    expect(settleLiabilitySchema.safeParse({ disbursement_id: "" }).success).toBe(false);
  });
});
