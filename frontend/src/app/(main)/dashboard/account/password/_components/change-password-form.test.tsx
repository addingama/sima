import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { ApiError, apiPut } from "@/lib/api/client";

import { ChangePasswordForm } from "./change-password-form";

vi.mock("sonner", () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

vi.mock("@/lib/api/client", async (importOriginal) => {
  const original = await importOriginal<typeof import("@/lib/api/client")>();

  return { ...original, apiPut: vi.fn() };
});

async function fillPasswords(current: string, next: string, confirmation = next) {
  const user = userEvent.setup();

  await user.type(screen.getByLabelText("Password saat ini"), current);
  await user.type(screen.getByLabelText("Password baru"), next);
  await user.type(screen.getByLabelText("Ulangi password baru"), confirmation);
  await user.click(screen.getByRole("button", { name: "Simpan password" }));
}

describe("ChangePasswordForm", () => {
  beforeEach(() => {
    vi.mocked(apiPut).mockReset();
  });

  it("mengirim password saat ini dan password baru", async () => {
    vi.mocked(apiPut).mockResolvedValue({ success: true, message: "Password berhasil diubah.", data: null });

    render(<ChangePasswordForm />);
    await fillPasswords("password-lama", "password-baru");

    await waitFor(() => {
      expect(apiPut).toHaveBeenCalledWith("/me/password", {
        current_password: "password-lama",
        password: "password-baru",
        password_confirmation: "password-baru",
      });
    });
  });

  it("menahan konfirmasi yang tidak sama", async () => {
    render(<ChangePasswordForm />);
    await fillPasswords("password-lama", "password-baru", "tidak-sama");

    expect(await screen.findByText("Konfirmasi password tidak sama.")).toBeInTheDocument();
    expect(apiPut).not.toHaveBeenCalled();
  });

  it("menampilkan kesalahan password saat ini dari server", async () => {
    vi.mocked(apiPut).mockRejectedValue(
      new ApiError("Data tidak valid.", 422, "validation_error", {
        current_password: ["Password saat ini tidak sesuai."],
      }),
    );

    render(<ChangePasswordForm />);
    await fillPasswords("salah", "password-baru");

    expect(await screen.findByText("Password saat ini tidak sesuai.")).toBeInTheDocument();
  });
});
