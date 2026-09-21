import { describe, expect, it } from "vitest";

import { formatDate, formatDateTime } from "./datetime";

describe("formatDate", () => {
  it.each([null, undefined, ""])("menampilkan placeholder untuk %s", (value) => {
    expect(formatDate(value)).toBe("-");
  });

  it("mempertahankan input tanggal yang tidak valid", () => {
    expect(formatDate("tanggal-rusak")).toBe("tanggal-rusak");
  });

  it("memformat tanggal dengan locale Indonesia", () => {
    expect(formatDate("2026-09-22T12:00:00")).toBe("22 Sep 2026");
  });
});

describe("formatDateTime", () => {
  it.each([null, undefined, ""])("menampilkan placeholder untuk %s", (value) => {
    expect(formatDateTime(value)).toBe("-");
  });

  it("mempertahankan input waktu yang tidak valid", () => {
    expect(formatDateTime("waktu-rusak")).toBe("waktu-rusak");
  });

  it("memformat tanggal dan waktu dengan locale Indonesia", () => {
    const formatted = formatDateTime("2026-09-22T12:34:00");

    expect(formatted).toContain("22 Sep 2026");
    expect(formatted).toContain("12.34");
  });
});
