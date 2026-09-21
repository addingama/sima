import { describe, expect, it } from "vitest";

import type { SimaUser } from "@/lib/api/types";

import { hasAnyPermission, hasPermission } from "./permissions";

function userWithPermissions(permissions: string[]): SimaUser {
  return {
    id: 1,
    name: "Pengguna Test",
    email: "test@sima.test",
    phone: null,
    is_active: true,
    roles: ["bendahara"],
    permissions,
  };
}

describe("hasPermission", () => {
  it("menolak pengguna yang belum terautentikasi", () => {
    expect(hasPermission(null, "receipt.view")).toBe(false);
    expect(hasPermission(undefined, "receipt.view")).toBe(false);
  });

  it("mengizinkan permission yang dimiliki secara eksplisit", () => {
    expect(hasPermission(userWithPermissions(["receipt.view"]), "receipt.view")).toBe(true);
  });

  it("menolak permission yang tidak dimiliki", () => {
    expect(hasPermission(userWithPermissions(["receipt.view"]), "receipt.approve")).toBe(false);
  });

  it("mengizinkan seluruh permission bagi wildcard", () => {
    expect(hasPermission(userWithPermissions(["*"]), "receipt.approve")).toBe(true);
  });
});

describe("hasAnyPermission", () => {
  it("mengembalikan true jika minimal satu permission dimiliki", () => {
    expect(hasAnyPermission(userWithPermissions(["fund.view"]), ["receipt.view", "fund.view"])).toBe(true);
  });

  it("mengembalikan false untuk daftar kosong atau tanpa kecocokan", () => {
    const user = userWithPermissions(["fund.view"]);

    expect(hasAnyPermission(user, [])).toBe(false);
    expect(hasAnyPermission(user, ["receipt.view", "receipt.approve"])).toBe(false);
  });
});
