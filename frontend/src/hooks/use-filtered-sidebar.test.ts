import { describe, expect, it } from "vitest";

import type { SimaUser } from "@/lib/api/types";
import { sidebarItems } from "@/navigation/sidebar/sidebar-items";

import { filterSidebarItems } from "./use-filtered-sidebar";

function user(roles: string[], permissions: string[]): SimaUser {
  return {
    id: 10,
    name: "Petugas Lapangan",
    email: "petugas@sima.test",
    phone: null,
    is_active: true,
    roles,
    permissions,
  };
}

function itemIds(groups: ReturnType<typeof filterSidebarItems>): string[] {
  return groups.flatMap((group) =>
    group.items.flatMap((item) => [item.id, ...(item.subItems?.map((subItem) => subItem.id) ?? [])]),
  );
}

describe("filterSidebarItems", () => {
  it("menyembunyikan seluruh master data tetapi tetap menampilkan dashboard untuk petugas bantuan", () => {
    const filtered = filterSidebarItems(
      sidebarItems,
      user(["petugas_bantuan"], ["grant.view", "grant.create", "program.view"]),
    );
    const ids = itemIds(filtered);

    expect(ids).toContain("dashboard");
    expect(ids).toContain("bantuan");
    expect(ids).not.toContain("master-data");
    expect(ids).not.toContain("events");
  });

  it("tetap menampilkan master data sesuai permission untuk role internal lain", () => {
    const filtered = filterSidebarItems(sidebarItems, user(["bendahara"], ["report.view", "program.view"]));
    const ids = itemIds(filtered);

    expect(ids).toContain("dashboard");
    expect(ids).toContain("master-data");
    expect(ids).toContain("events");
  });
});
