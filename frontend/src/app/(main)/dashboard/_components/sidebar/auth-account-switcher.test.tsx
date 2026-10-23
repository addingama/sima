import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { AuthAccountSwitcher } from "./auth-account-switcher";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock("@/providers/auth-provider", () => ({
  useAuth: () => ({
    user: { name: "Bendahara Utama", email: "bendahara@sima.test", roles: ["bendahara"] },
    logout: vi.fn(),
  }),
}));

describe("AuthAccountSwitcher", () => {
  it("membuka halaman ubah password dari menu akun", async () => {
    const user = userEvent.setup();

    render(<AuthAccountSwitcher />);
    await user.click(screen.getByLabelText("Menu akun"));

    expect(await screen.findByRole("menuitem", { name: "Ubah password" })).toHaveAttribute(
      "href",
      "/dashboard/account/password",
    );
  });
});
