import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

vi.stubGlobal(
  "ResizeObserver",
  class {
    observe() {}
    unobserve() {}
    disconnect() {}
  },
);

import { LoginForm } from "./login-form";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn() }),
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("@/providers/auth-provider", () => ({
  useAuth: () => ({ login: vi.fn() }),
}));

describe("LoginForm", () => {
  it("tidak mengisi akun demo di luar development", () => {
    render(<LoginForm />);

    expect(screen.getByLabelText("Email atau nomor HP")).toHaveValue("");
    expect(screen.getByLabelText("Password")).toHaveValue("");
  });
});
