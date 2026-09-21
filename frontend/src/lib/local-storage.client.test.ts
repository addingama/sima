import { beforeEach, describe, expect, it, vi } from "vitest";

import { getLocalStorageValue, setLocalStorageValue } from "./local-storage.client";

describe("local storage client", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    const values = new Map<string, string>();
    Object.defineProperty(window, "localStorage", {
      configurable: true,
      value: {
        getItem: vi.fn((key: string) => values.get(key) ?? null),
        setItem: vi.fn((key: string, value: string) => values.set(key, value)),
        removeItem: vi.fn((key: string) => values.delete(key)),
        clear: vi.fn(() => values.clear()),
      },
    });
  });

  it("menyimpan dan membaca nilai", () => {
    setLocalStorageValue("layout", "compact");

    expect(getLocalStorageValue("layout")).toBe("compact");
  });

  it("mengembalikan null ketika pembacaan gagal", () => {
    vi.spyOn(window.localStorage, "getItem").mockImplementation(() => {
      throw new Error("storage diblokir");
    });

    expect(getLocalStorageValue("layout")).toBeNull();
  });

  it("tidak melempar ketika penulisan gagal", () => {
    vi.spyOn(window.localStorage, "setItem").mockImplementation(() => {
      throw new Error("quota penuh");
    });
    vi.spyOn(console, "error").mockImplementation(() => undefined);

    expect(() => setLocalStorageValue("layout", "compact")).not.toThrow();
  });
});
