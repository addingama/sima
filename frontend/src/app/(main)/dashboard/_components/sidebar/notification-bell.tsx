"use client";

import { useState } from "react";

import Link from "next/link";
import { useRouter } from "next/navigation";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Bell } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { apiGet, apiPost } from "@/lib/api/client";
import { type AppNotification, notificationText, notificationTime } from "@/lib/notifications";

export function NotificationBell() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const notifications = useQuery({
    queryKey: ["/notifications", "preview"],
    queryFn: async () => {
      const response = await apiGet<AppNotification[]>("/notifications", { per_page: 8 });

      return {
        items: response.data,
        unread: response.meta?.unread_count ?? 0,
      };
    },
    refetchInterval: 30_000,
    refetchOnWindowFocus: true,
  });

  const unread = notifications.data?.unread ?? 0;

  const openItem = async (item: AppNotification) => {
    if (!item.read_at) {
      await apiPost(`/notifications/${item.id}/read`);
      await queryClient.invalidateQueries({ queryKey: ["/notifications"] });
    }

    setOpen(false);

    if (item.grant_application_id) {
      router.push(`/dashboard/bantuan/${item.grant_application_id}#komentar`);
    }
  };

  const markAllRead = async () => {
    await apiPost("/notifications/read-all");
    await queryClient.invalidateQueries({ queryKey: ["/notifications"] });
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
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
        <div className="flex items-center justify-between gap-2 border-b px-3 py-2">
          <span className="font-medium text-sm">Notifikasi</span>
          {unread > 0 ? (
            <Button size="xs" variant="ghost" onClick={() => void markAllRead()}>
              Tandai semua dibaca
            </Button>
          ) : null}
        </div>
        <ul className="max-h-80 overflow-y-auto">
          {(notifications.data?.items.length ?? 0) === 0 ? (
            <li className="px-3 py-6 text-center text-muted-foreground text-sm">Tidak ada notifikasi</li>
          ) : (
            notifications.data?.items.map((item) => (
              <li key={item.id}>
                <button
                  type="button"
                  className="flex w-full flex-col gap-0.5 px-3 py-2 text-left hover:bg-muted"
                  onClick={() => void openItem(item)}
                >
                  <span className={item.read_at ? "text-sm" : "font-medium text-sm"}>{notificationText(item)}</span>
                  {item.excerpt ? (
                    <span className="line-clamp-2 text-muted-foreground text-xs">{item.excerpt}</span>
                  ) : null}
                  {item.created_at ? (
                    <time className="text-muted-foreground text-xs" dateTime={item.created_at}>
                      {notificationTime(item.created_at)}
                    </time>
                  ) : null}
                </button>
              </li>
            ))
          )}
        </ul>
        <div className="border-t px-3 py-2">
          <Link
            href="/dashboard/notifikasi"
            className="text-sm underline-offset-4 hover:underline"
            onClick={() => setOpen(false)}
          >
            Lihat semua
          </Link>
        </div>
      </PopoverContent>
    </Popover>
  );
}
