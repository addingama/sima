import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { apiGet, apiPut } from "@/lib/api/client";

import RolePermissionsPage from "./role-permissions-page";

vi.mock("@/lib/api/client", async (importOriginal) => {
  const original = await importOriginal<typeof import("@/lib/api/client")>();
  return { ...original, apiGet: vi.fn(), apiPut: vi.fn() };
});

vi.mock("sonner", () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const roleData = {
  roles: [
    {
      id: 1,
      name: "admin",
      label: "Administrator",
      permissions: ["receipt.view", "receipt.approve", "user.manage"],
      users_count: 1,
      is_locked: true,
    },
    {
      id: 2,
      name: "bendahara",
      label: "Bendahara",
      permissions: ["receipt.view"],
      users_count: 2,
      is_locked: false,
    },
  ],
  permissions: ["receipt.view", "receipt.approve", "user.manage"],
};

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <RolePermissionsPage />
    </QueryClientProvider>,
  );
}

describe("RolePermissionsPage", () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockResolvedValue({ success: true, message: null, data: roleData, meta: null, errors: null });
    vi.mocked(apiPut).mockResolvedValue({ success: true, message: null, data: roleData, meta: null, errors: null });
  });

  it("menampilkan role admin sebagai terkunci", async () => {
    renderPage();

    expect((await screen.findAllByText("Administrator")).length).toBeGreaterThan(1);
    expect(screen.getByLabelText("Terkunci")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /simpan perubahan/i })).not.toBeInTheDocument();
  });

  it("mengubah dan menyimpan permission role non-admin", async () => {
    const user = userEvent.setup();
    renderPage();

    await user.click(await screen.findByRole("button", { name: "Bendahara" }));
    await user.click(screen.getByLabelText("approve"));
    await user.click(screen.getByRole("button", { name: /simpan perubahan/i }));

    await waitFor(() => {
      expect(apiPut).toHaveBeenCalledWith("/roles/2/permissions", {
        permissions: ["receipt.view", "receipt.approve"],
      });
    });
  });
});
