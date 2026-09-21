import { describe, expect, it } from "vitest";

import { amountToMoneyInput, formatMoneyInput, formatPercent, normalizeAmountString, parseAmount } from "./amount";

describe("amountToMoneyInput", () => {
  it.each([null, undefined, "", "tidak-valid", 0, -100])("mengembalikan string kosong untuk %s", (value) => {
    expect(amountToMoneyInput(value)).toBe("");
  });

  it("membulatkan nominal positif menjadi digit saja", () => {
    expect(amountToMoneyInput("1250.75")).toBe("1251");
  });
});

describe("parseAmount", () => {
  it.each([
    ["100000.00", 100000],
    ["1.000.000", 1000000],
    ["1.000.000,50", 1000000.5],
    ["Rp 25.000,75", 25000.75],
    [2500.5, 2500.5],
  ])("mem-parsing %s menjadi %s", (value, expected) => {
    expect(parseAmount(value)).toBe(expected);
  });

  it.each([null, undefined, "", "bukan nominal", Number.NaN])("mengembalikan nol untuk %s", (value) => {
    expect(parseAmount(value)).toBe(0);
  });
});

describe("formatter nominal", () => {
  it("memformat input uang dengan pemisah ribuan Indonesia", () => {
    expect(formatMoneyInput("1000000")).toBe("1.000.000");
  });

  it("menormalisasi nominal positif ke dua angka desimal", () => {
    expect(normalizeAmountString("1.250,50")).toBe("1250.50");
  });

  it("menolak nominal nol dan negatif saat normalisasi", () => {
    expect(normalizeAmountString(0)).toBe("");
    expect(normalizeAmountString(-1)).toBe("");
  });

  it("memformat persentase sesuai presisi yang diminta", () => {
    expect(formatPercent(12.345)).toBe("12.3%");
    expect(formatPercent(12.345, 2)).toBe("12.35%");
  });
});
