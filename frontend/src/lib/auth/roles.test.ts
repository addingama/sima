import { describe, expect, it } from "vitest";

import { roleLabel, SIMA_ROLE_OPTIONS } from "./roles";

describe("roleLabel", () => {
  it.each(SIMA_ROLE_OPTIONS)("memetakan role $value ke label $label", ({ value, label }) => {
    expect(roleLabel(value)).toBe(label);
  });

  it("menyertakan Petugas Bantuan pada pilihan role", () => {
    expect(SIMA_ROLE_OPTIONS).toContainEqual({ value: "petugas_bantuan", label: "Petugas Bantuan" });
  });

  it("mempertahankan nilai role yang belum dikenal", () => {
    expect(roleLabel("role_baru")).toBe("role_baru");
  });
});
