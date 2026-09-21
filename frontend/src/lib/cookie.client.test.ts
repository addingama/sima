// biome-ignore-all lint/suspicious/noDocumentCookie: Test perlu membersihkan state cookie jsdom secara langsung.
import { beforeEach, describe, expect, it, vi } from "vitest";

import { deleteClientCookie, getClientCookie, setClientCookie } from "./cookie.client";

describe("client cookie", () => {
  beforeEach(() => {
    document.cookie = "preferensi=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/";
  });

  it("menyimpan dan membaca cookie", () => {
    vi.spyOn(Date, "now").mockReturnValue(new Date("2026-09-22T00:00:00Z").getTime());

    setClientCookie("preferensi", "compact", 7);

    expect(getClientCookie("preferensi")).toBe("compact");
  });

  it("mengembalikan undefined untuk cookie yang tidak tersedia", () => {
    expect(getClientCookie("tidak-ada")).toBeUndefined();
  });

  it("menghapus cookie", () => {
    setClientCookie("preferensi", "compact");
    deleteClientCookie("preferensi");

    expect(getClientCookie("preferensi")).toBeUndefined();
  });
});
