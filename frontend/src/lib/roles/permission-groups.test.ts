import { describe, expect, it } from "vitest";

import { groupPermissions, permissionLabel } from "./permission-groups";

describe("permission groups", () => {
  it("mengelompokkan dan mengurutkan permission berdasarkan modul", () => {
    expect(groupPermissions(["user.manage", "receipt.view", "receipt.approve"])).toEqual([
      { name: "Penerimaan", permissions: ["receipt.approve", "receipt.view"] },
      { name: "Pengguna", permissions: ["user.manage"] },
    ]);
  });

  it("membuat label aksi yang mudah dibaca", () => {
    expect(permissionLabel("opening.post_balance")).toBe("post balance");
  });
});
