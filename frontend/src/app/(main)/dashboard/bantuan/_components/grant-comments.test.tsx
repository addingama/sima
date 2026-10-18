import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { apiGet, apiPost } from "@/lib/api/client";

import { GrantComments } from "./grant-comments";

vi.mock("@/lib/api/client", async (importOriginal) => {
  const original = await importOriginal<typeof import("@/lib/api/client")>();
  return { ...original, apiGet: vi.fn(), apiPost: vi.fn(), apiPut: vi.fn(), apiDelete: vi.fn() };
});

vi.mock("sonner", () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const comment = {
  id: 8,
  body: "Mohon dicek @[user:4]",
  edited_at: "2026-10-18T02:00:00Z",
  created_at: "2026-10-18T01:00:00Z",
  user: { id: 2, name: "Verifikator" },
  mentions: [{ id: 4, name: "Siti Aminah" }],
  can_edit: true,
  can_delete: true,
};

function renderComments() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <GrantComments grantId={12} mentionAccess={[{ id: 4, name: "Siti Aminah" }]} onChanged={vi.fn()} />
    </QueryClientProvider>,
  );
}

describe("GrantComments", () => {
  beforeEach(() => {
    vi.mocked(apiPost).mockResolvedValue({ success: true, message: null, data: {}, meta: null, errors: null });
    vi.mocked(apiGet).mockImplementation(async (path: string) => {
      if (path.startsWith("/grant-applications/mentionable-users")) {
        return {
          success: true,
          message: null,
          data: [{ id: 4, name: "Siti Aminah" }],
          meta: null,
          errors: null,
        };
      }

      return {
        success: true,
        message: null,
        data: [comment],
        meta: { pagination: { current_page: 1, last_page: 1, per_page: 100, total: 1, from: 1, to: 1 } },
        errors: null,
      };
    });
  });

  it("menampilkan nama yang disebut dan menandai komentar yang diedit", async () => {
    renderComments();

    expect(await screen.findByText("@Siti Aminah")).toBeInTheDocument();
    expect(screen.queryByText(/@\[user:4\]/)).not.toBeInTheDocument();
    expect(screen.getByText(/diedit/)).toBeInTheDocument();
    expect(screen.getByText(/Bisa melihat lewat mention: Siti Aminah/)).toBeInTheDocument();
    expect(apiPost).toHaveBeenCalledWith("/notifications/read-all", { grant_application_id: 12 });
  });

  it("menyisipkan mention lalu mengirim token pengguna", async () => {
    const user = userEvent.setup();
    renderComments();

    const composer = await screen.findByRole("textbox", { name: "Tulis komentar" });
    await user.type(composer, "Halo @Si");
    await user.click(await screen.findByRole("option", { name: "Siti Aminah" }));
    expect(screen.queryByRole("listbox", { name: "Pengguna yang bisa disebut" })).not.toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Kirim" }));

    expect(apiPost).toHaveBeenCalledWith("/grant-applications/12/comments", {
      body: "Halo @[user:4]",
    });
  });
});
