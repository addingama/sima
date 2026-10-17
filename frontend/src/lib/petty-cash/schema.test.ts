import { describe, expect, it } from "vitest";

import {
  pettyCashCloseSchema,
  pettyCashDisbursementSchema,
  pettyCashFloatSchema,
  pettyCashLineSchema,
  pettyCashTopupRequestSchema,
} from "./schema";

describe("pettyCashFloatSchema", () => {
  const valid = {
    title: "Operasional September",
    purpose: "operational" as const,
    custodian_user_id: "5",
    program_id: "",
    period_start: "2026-09-01",
    period_end: "2026-09-30",
    notes: "",
  };

  it("accepts valid float data", () => {
    expect(pettyCashFloatSchema.safeParse(valid).success).toBe(true);
  });

  it("rejects missing title", () => {
    const result = pettyCashFloatSchema.safeParse({ ...valid, title: "" });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.flatten().fieldErrors.title).toBeDefined();
    }
  });

  it("rejects invalid purpose", () => {
    const result = pettyCashFloatSchema.safeParse({ ...valid, purpose: "unknown" });
    expect(result.success).toBe(false);
  });

  it("rejects missing custodian", () => {
    const result = pettyCashFloatSchema.safeParse({ ...valid, custodian_user_id: "" });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.flatten().fieldErrors.custodian_user_id).toBeDefined();
    }
  });
});

describe("pettyCashLineSchema", () => {
  const valid = {
    category_id: "3",
    spent_on: "2026-09-15",
    amount: "150000",
    description: "Beli kertas A4",
  };

  it("accepts valid line data", () => {
    expect(pettyCashLineSchema.safeParse(valid).success).toBe(true);
  });

  it("rejects zero amount", () => {
    const result = pettyCashLineSchema.safeParse({ ...valid, amount: "0" });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.flatten().fieldErrors.amount).toBeDefined();
    }
  });

  it("rejects empty description", () => {
    const result = pettyCashLineSchema.safeParse({ ...valid, description: "" });
    expect(result.success).toBe(false);
  });

  it("rejects empty category", () => {
    const result = pettyCashLineSchema.safeParse({ ...valid, category_id: "" });
    expect(result.success).toBe(false);
  });
});

describe("pettyCashTopupRequestSchema", () => {
  it("accepts valid topup request", () => {
    expect(pettyCashTopupRequestSchema.safeParse({ amount: "500000", reason: "Kurang untuk kegiatan" }).success).toBe(
      true,
    );
  });

  it("rejects zero amount", () => {
    const result = pettyCashTopupRequestSchema.safeParse({ amount: "0", reason: "x" });
    expect(result.success).toBe(false);
  });

  it("rejects missing reason", () => {
    const result = pettyCashTopupRequestSchema.safeParse({ amount: "100000", reason: "" });
    expect(result.success).toBe(false);
  });
});

describe("pettyCashCloseSchema", () => {
  it("accepts empty returned amount", () => {
    expect(pettyCashCloseSchema.safeParse({ returned_amount: "" }).success).toBe(true);
  });

  it("accepts positive returned amount", () => {
    expect(pettyCashCloseSchema.safeParse({ returned_amount: "50000" }).success).toBe(true);
  });
});

describe("pettyCashDisbursementSchema", () => {
  const valid = {
    amount: "2000000",
    disbursement_date: "2026-09-01",
    account_id: "1",
    fund_id: "2",
    description: "Dana operasional September",
  };

  it("accepts valid disbursement", () => {
    expect(pettyCashDisbursementSchema.safeParse(valid).success).toBe(true);
  });

  it("rejects missing account", () => {
    const result = pettyCashDisbursementSchema.safeParse({ ...valid, account_id: "" });
    expect(result.success).toBe(false);
  });

  it("rejects missing fund", () => {
    const result = pettyCashDisbursementSchema.safeParse({ ...valid, fund_id: "" });
    expect(result.success).toBe(false);
  });
});
