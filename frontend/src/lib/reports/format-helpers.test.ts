import { describe, expect, it } from "vitest";

import { reportExportHelpers } from "./format-helpers";

describe("reportExportHelpers", () => {
  it("memformat currency, tanggal, dan datetime untuk export", () => {
    expect(reportExportHelpers.currency("1250000.00")).toMatch(/^Rp\s?1\.250\.000$/);
    expect(reportExportHelpers.date("2026-09-22T12:00:00")).toBe("22 Sep 2026");
    expect(reportExportHelpers.datetime("2026-09-22T12:34:00")).toContain("12.34");
  });

  it("menghasilkan fallback yang aman untuk nilai kosong", () => {
    expect(reportExportHelpers.currency(null)).toMatch(/^Rp\s?0$/);
    expect(reportExportHelpers.date(null)).toBe("-");
    expect(reportExportHelpers.datetime(undefined)).toBe("-");
  });
});
