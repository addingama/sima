import { describe, expect, it } from "vitest";

import { buildFormSchema, normalizeFormValues, nullifyEmptyOptionalFields } from "./form-schema";
import type { FormFieldDef } from "./types";

const fields: FormFieldDef[] = [
  { name: "name", label: "Nama", type: "text", required: true },
  { name: "email", label: "Email", type: "email" },
  { name: "amount", label: "Nominal", type: "currency", required: true },
  { name: "active", label: "Aktif", type: "checkbox" },
  { name: "password", label: "Password", type: "password", required: true, showOnCreateOnly: true },
  { name: "balance", label: "Saldo", type: "currency", showOnEditOnly: true },
  { name: "code", label: "Kode", type: "text", autoGenerate: true },
];

describe("buildFormSchema", () => {
  it("menerima nilai create yang valid dan mengubah checkbox kosong menjadi false", () => {
    const result = buildFormSchema(fields).safeParse({
      name: "Dana Pendidikan",
      email: "",
      amount: "1.000.000",
      active: null,
      password: "rahasia123",
      code: null,
    });

    expect(result.success).toBe(true);
    if (result.success) {
      expect(result.data.active).toBe(false);
      expect(result.data.code).toBe("");
    }
  });

  it.each([
    ["nama wajib", { name: "", email: "", amount: "1000", active: true, password: "rahasia123", code: "" }],
    [
      "email invalid",
      { name: "Nama", email: "bukan-email", amount: "1000", active: true, password: "rahasia123", code: "" },
    ],
    ["nominal positif", { name: "Nama", email: "", amount: "0", active: true, password: "rahasia123", code: "" }],
    ["password minimum", { name: "Nama", email: "", amount: "1000", active: true, password: "pendek", code: "" }],
  ])("menolak input create ketika melanggar %s", (_, payload) => {
    expect(buildFormSchema(fields).safeParse(payload).success).toBe(false);
  });

  it("mengabaikan field khusus create saat edit dan menyertakan field khusus edit", () => {
    const result = buildFormSchema(fields, undefined, true).safeParse({
      name: "Dana Pendidikan",
      email: "admin@sima.test",
      amount: "1000",
      active: true,
      balance: null,
      code: "FND-001",
    });

    expect(result.success).toBe(true);
    if (result.success) {
      expect(result.data).not.toHaveProperty("password");
      expect(result.data.balance).toBe("");
    }
  });

  it("memvalidasi minimal satu line item yang lengkap", () => {
    const schema = buildFormSchema([], "allocations");

    expect(schema.safeParse({ allocations: [] }).success).toBe(false);
    expect(schema.safeParse({ allocations: [{ fund_id: "", amount: "1000" }] }).success).toBe(false);
    expect(schema.safeParse({ allocations: [{ fund_id: "1", amount: "0" }] }).success).toBe(false);
    expect(
      schema.safeParse({ allocations: [{ fund_id: "1", program_id: "", amount: "1000", note: "" }] }).success,
    ).toBe(true);
  });
});

describe("normalizeFormValues", () => {
  it("menormalkan null, undefined, dan checkbox tanpa mengubah field computed", () => {
    const result = normalizeFormValues({ name: null, email: undefined, active: 1, balance: null }, fields);

    expect(result).toMatchObject({ name: "", email: "", active: true, balance: null });
  });
});

describe("nullifyEmptyOptionalFields", () => {
  it("mengubah field opsional kosong menjadi null", () => {
    const result = nullifyEmptyOptionalFields(
      { name: "Dana", email: "", active: false, code: "", balance: "" },
      fields,
    );

    expect(result).toMatchObject({ name: "Dana", email: null, active: false, code: "", balance: "" });
  });
});
