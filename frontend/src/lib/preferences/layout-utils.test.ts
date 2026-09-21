import { beforeEach, describe, expect, it } from "vitest";

import {
  applyContentLayout,
  applyFont,
  applyNavbarStyle,
  applySidebarCollapsible,
  applySidebarVariant,
} from "./layout-utils";

describe("layout utilities", () => {
  beforeEach(() => {
    document.documentElement.removeAttribute("data-content-layout");
    document.documentElement.removeAttribute("data-navbar-style");
    document.documentElement.removeAttribute("data-sidebar-variant");
    document.documentElement.removeAttribute("data-sidebar-collapsible");
    document.documentElement.removeAttribute("data-font");
  });

  it("menerapkan seluruh preferensi layout ke root document", () => {
    applyContentLayout("full-width");
    applyNavbarStyle("sticky");
    applySidebarVariant("floating");
    applySidebarCollapsible("icon");
    applyFont("geist");

    const root = document.documentElement;
    expect(root).toHaveAttribute("data-content-layout", "full-width");
    expect(root).toHaveAttribute("data-navbar-style", "sticky");
    expect(root).toHaveAttribute("data-sidebar-variant", "floating");
    expect(root).toHaveAttribute("data-sidebar-collapsible", "icon");
    expect(root).toHaveAttribute("data-font", "geist");
  });
});
