"use client";

import { useRouter } from "next/navigation";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Bell } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { apiGet, apiPost } from "@/lib/api/client";

export type AppNotification = {
  id: string;
  kind: string | null;
  grant_application_id: number | null;
  application_number: string | null;
  comment_id: number | null;
  actor_name: string | null;
  excerpt: string | null;
  read_at: string | null;
  created_at: string | null;
};

function notificationText(item: AppNotification): string {
  const actor = item.actor_name ?? "Seseorang";
  const number = item.application_number ?? "pengajuan";

  if (item.kind === "grant_mention") {
    return `${actor} menyebut Anda di ${number}`;
  }

  if (item.kind === "grant_comment") {
    return `${actor} berkomentar di ${number}`;
  }

  return `${actor} mengirim notifikasi`;
}

export function NotificationBell() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const notifications = useQuery({
    queryKey: ["/notifications"],
    queryFn: async () => {
      const response = await apiGet<AppNotification[]>("/notifications");

      return {
        items: response.data,
        unread: response.meta?.unread_count ?? 0,
      };
    },
    refetchInterval: 30_000,
    refetchOnWindowFocus: true,
  });

  const unread = notifications.data?.unread ?? 0;

  const open = async (item: AppNotification) => {
    if (!item.read_at) {
      await apiPost(`/notifications/${item.id}/read`);
      await queryClient.invalidateQueries({ queryKey: ["/notifications"] });
    }

    if (item.grant_application_id) {
      router.push(`/dashboard/bantuan/${item.grant_application_id}#komentar`);
    }
  };

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          size="icon"
          variant="ghost"
          aria-label={unread > 0 ? `Notifikasi, ${unread} belum dibaca` : "Notifikasi"}
          className="relative"
        >
          <Bell />
          {unread > 0 ? (
            <span className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] text-primary-foreground">
              {unread > 9 ? "9+" : unread}
            </span>
          ) : null}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-80 p-0">
        <div className="border-b px-3 py-2 font-medium text-sm">Notifikasi</div>
        <ul className="max-h-80 overflow-y-auto">
          {(notifications.data?.items.length ?? 0) === 0 ? (
            <li className="px-3 py-6 text-center text-muted-foreground text-sm">Tidak ada notifikasi</li>
          ) : (
            notifications.data?.items.map((item) => (
              <li key={item.id}>
                <button
                  type="button"
                  className="flex w-full flex-col gap-0.5 px-3 py-2 text-left hover:bg-muted"
                  onClick={() => void open(item)}
                >
                  <span className={item.read_at ? "text-sm" : "font-medium text-sm"}>{notificationText(item)}</span>
                  {item.excerpt ? (
                    <span className="line-clamp-2 text-muted-foreground text-xs">{item.excerpt}</span>
                  ) : null}
                </button>
              </li>
            ))
          )}
        </ul>
      </PopoverContent>
    </Popover>
  );
}
