import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { applyThemeMode, applyThemePreset, resolveThemeMode, subscribeToSystemTheme } from "./theme-utils";

function mockMatchMedia(matches: boolean) {
  const addEventListener = vi.fn();
  const removeEventListener = vi.fn();
  vi.stubGlobal(
    "matchMedia",
    vi.fn(() => ({ matches, addEventListener, removeEventListener })),
  );
  Object.defineProperty(window, "matchMedia", { configurable: true, value: globalThis.matchMedia });
  return { addEventListener, removeEventListener };
}

describe("theme utilities", () => {
  beforeEach(() => {
    document.documentElement.className = "";
    document.documentElement.removeAttribute("data-theme-mode");
    document.documentElement.removeAttribute("data-theme-preset");
    document.documentElement.style.colorScheme = "";
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it("menyelesaikan mode eksplisit dan preferensi sistem", () => {
    expect(resolveThemeMode("light")).toBe("light");
    expect(resolveThemeMode("dark")).toBe("dark");
    mockMatchMedia(true);
    expect(resolveThemeMode("system")).toBe("dark");
  });

  it("menerapkan mode gelap dan membersihkan class transisi pada frame berikutnya", () => {
    const callbacks: FrameRequestCallback[] = [];
    vi.stubGlobal(
      "requestAnimationFrame",
      vi.fn((callback: FrameRequestCallback) => callbacks.push(callback)),
    );

    expect(applyThemeMode("dark")).toBe("dark");
    expect(document.documentElement).toHaveAttribute("data-theme-mode", "dark");
    expect(document.documentElement).toHaveClass("dark", "disable-transitions");
    expect(document.documentElement.style.colorScheme).toBe("dark");

    callbacks[0](0);
    expect(document.documentElement).not.toHaveClass("disable-transitions");
  });

  it("menerapkan preset tema", () => {
    applyThemePreset("tangerine");
    expect(document.documentElement).toHaveAttribute("data-theme-preset", "tangerine");
  });

  it("meneruskan perubahan sistem dan menyediakan unsubscribe", () => {
    const media = mockMatchMedia(false);
    const onChange = vi.fn();
    const unsubscribe = subscribeToSystemTheme(onChange);
    const listener = media.addEventListener.mock.calls[0][1] as (event: { matches: boolean }) => void;

    listener({ matches: true });
    listener({ matches: false });
    unsubscribe();

    expect(onChange).toHaveBeenNthCalledWith(1, "dark");
    expect(onChange).toHaveBeenNthCalledWith(2, "light");
    expect(media.removeEventListener).toHaveBeenCalledWith("change", listener);
  });
});
