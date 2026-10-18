import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { apiGet, apiPost } from "@/lib/api/client";

import { NotificationBell } from "./notification-bell";

const push = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push }),
}));

vi.mock("@/lib/api/client", async (importOriginal) => {
  const original = await importOriginal<typeof import("@/lib/api/client")>();
  return { ...original, apiGet: vi.fn(), apiPost: vi.fn() };
});

function renderBell() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <NotificationBell />
    </QueryClientProvider>,
  );
}

describe("NotificationBell", () => {
  beforeEach(() => {
    push.mockReset();
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: null,
      data: [
        {
          id: "note-1",
          kind: "grant_mention",
          grant_application_id: 12,
          application_number: "BNT/2026/000012",
          comment_id: 3,
          actor_name: "Siti",
          excerpt: "Mohon dicek",
          read_at: null,
          created_at: "2026-10-18T00:00:00Z",
        },
      ],
      meta: { unread_count: 2 },
      errors: null,
    });
    vi.mocked(apiPost).mockResolvedValue({ success: true, message: null, data: {}, meta: null, errors: null });
  });

  it("menunjukkan jumlah belum dibaca dan membuka kartu saat diklik", async () => {
    const user = userEvent.setup();
    renderBell();

    expect(await screen.findByRole("button", { name: "Notifikasi, 2 belum dibaca" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Notifikasi, 2 belum dibaca" }));
    await user.click(await screen.findByRole("button", { name: /Siti menyebut Anda/ }));

    expect(apiPost).toHaveBeenCalledWith("/notifications/note-1/read");
    expect(push).toHaveBeenCalledWith("/dashboard/bantuan/12#komentar");
  });

  it("menawarkan tandai semua dibaca dan tautan ke seluruh notifikasi", async () => {
    const user = userEvent.setup();
    renderBell();

    await user.click(await screen.findByRole("button", { name: "Notifikasi, 2 belum dibaca" }));
    await user.click(screen.getByRole("button", { name: "Tandai semua dibaca" }));
    expect(apiPost).toHaveBeenCalledWith("/notifications/read-all");
    expect(screen.getByRole("link", { name: "Lihat semua" })).toHaveAttribute("href", "/dashboard/notifikasi");
  });
});
