import { describe, expect, it } from "vitest";

import { cn, formatCurrency, getInitials } from "./utils";

describe("cn", () => {
  it("menggabungkan class kondisional dan menyelesaikan konflik Tailwind", () => {
    expect(cn("px-2", undefined, "px-4", { block: true })).toBe("px-4 block");
  });
});

describe("getInitials", () => {
  it.each([
    ["Sistem Informasi Manajemen Amanah", "SIMA"],
    ["  dana   amanah  ", "DA"],
    ["Bendahara", "B"],
    ["", "?"],
    ["   ", "?"],
  ])("mengubah %j menjadi %s", (value, expected) => {
    expect(getInitials(value)).toBe(expected);
  });
});

describe("formatCurrency", () => {
  it("menggunakan default USD dan locale en-US", () => {
    expect(formatCurrency(1250.5)).toBe("$1,250.50");
  });

  it("mendukung IDR tanpa desimal", () => {
    expect(formatCurrency(1250000, { currency: "IDR", locale: "id-ID", noDecimals: true })).toMatch(
      /^Rp\s?1\.250\.000$/,
    );
  });

  it("menghormati batas digit pecahan", () => {
    expect(
      formatCurrency(1.2345, {
        currency: "USD",
        locale: "en-US",
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
      }),
    ).toBe("$1.235");
  });
});
