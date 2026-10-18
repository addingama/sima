import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { apiDelete, apiGet, apiPost } from "@/lib/api/client";
import type { AppNotification } from "@/lib/notifications";

import { NotificationsInbox } from "./notifications-inbox";

const push = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push }),
}));

vi.mock("@/lib/api/client", async (importOriginal) => {
  const original = await importOriginal<typeof import("@/lib/api/client")>();
  return { ...original, apiGet: vi.fn(), apiPost: vi.fn(), apiDelete: vi.fn() };
});

const unread: AppNotification = {
  id: "note-unread",
  kind: "grant_mention",
  grant_application_id: 12,
  application_number: "BNT/2026/000012",
  comment_id: 3,
  actor_name: "Siti",
  excerpt: "Mohon dicek",
  read_at: null,
  created_at: "2026-10-18T00:00:00Z",
};

const read: AppNotification = {
  id: "note-read",
  kind: "grant_comment",
  grant_application_id: 9,
  application_number: "BNT/2026/000009",
  comment_id: 1,
  actor_name: "Budi",
  excerpt: "Sudah saya lihat",
  read_at: "2026-10-18T01:00:00Z",
  created_at: "2026-10-17T00:00:00Z",
};

function renderInbox() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <NotificationsInbox />
    </QueryClientProvider>,
  );
}

describe("NotificationsInbox", () => {
  beforeEach(() => {
    push.mockReset();
    vi.mocked(apiGet).mockImplementation(async (_path, params) => {
      const items = params?.status === "unread" ? [unread] : [unread, read];

      return {
        success: true,
        message: null,
        data: items,
        meta: {
          unread_count: 1,
          pagination: {
            current_page: Number(params?.page ?? 1),
            last_page: 1,
            per_page: 20,
            total: items.length,
            from: 1,
            to: items.length,
          },
        },
        errors: null,
      };
    });
    vi.mocked(apiPost).mockResolvedValue({ success: true, message: null, data: {}, meta: null, errors: null });
    vi.mocked(apiDelete).mockResolvedValue({
      success: true,
      message: null,
      data: { deleted: 1 },
      meta: null,
      errors: null,
    });
  });

  it("menampilkan notifikasi dibaca dan belum dibaca, lalu menyaring yang belum", async () => {
    const user = userEvent.setup();
    renderInbox();

    expect(await screen.findByRole("button", { name: /^Siti menyebut Anda/ })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /^Budi berkomentar/ })).toBeInTheDocument();

    await user.click(screen.getByRole("tab", { name: "Belum dibaca" }));

    await waitFor(() => {
      expect(screen.queryByRole("button", { name: /^Budi berkomentar/ })).not.toBeInTheDocument();
    });
    expect(screen.getByRole("button", { name: /^Siti menyebut Anda/ })).toBeInTheDocument();
    expect(apiGet).toHaveBeenCalledWith("/notifications", { status: "unread", page: 1, per_page: 20 });
  });

  it("menandai belum dibaca, membuka kartu, dan menghapus satu notifikasi", async () => {
    const user = userEvent.setup();
    renderInbox();

    await screen.findByRole("button", { name: /^Budi berkomentar/ });
    await user.click(screen.getByRole("button", { name: "Tandai belum dibaca: Budi berkomentar di BNT/2026/000009" }));
    expect(apiPost).toHaveBeenCalledWith("/notifications/note-read/unread");

    await user.click(screen.getByRole("button", { name: /^Siti menyebut Anda/ }));
    expect(apiPost).toHaveBeenCalledWith("/notifications/note-unread/read");
    expect(push).toHaveBeenCalledWith("/dashboard/bantuan/12#komentar");

    await user.click(screen.getByRole("button", { name: "Hapus notifikasi: Budi berkomentar di BNT/2026/000009" }));
    await user.click(await screen.findByRole("button", { name: "Hapus" }));
    expect(apiDelete).toHaveBeenCalledWith("/notifications/note-read");
  });
});
