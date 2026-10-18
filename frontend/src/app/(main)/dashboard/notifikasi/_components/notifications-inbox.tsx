"use client";

import { type ReactNode, useState } from "react";

import { useRouter } from "next/navigation";

import { useQuery, useQueryClient } from "@tanstack/react-query";

import { ConfirmActionDialog } from "@/components/sima/crud/confirm-action-dialog";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { Button } from "@/components/ui/button";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { apiDelete, apiGet, apiPost } from "@/lib/api/client";
import { type AppNotification, notificationText, notificationTime } from "@/lib/notifications";

type InboxStatus = "all" | "unread";

const PER_PAGE = 20;

export function NotificationsInbox() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<InboxStatus>("all");
  const [page, setPage] = useState(1);
  const [pendingDelete, setPendingDelete] = useState<AppNotification | null>(null);
  const [clearReadOpen, setClearReadOpen] = useState(false);

  const notifications = useQuery({
    queryKey: ["/notifications", status, page],
    queryFn: async () => {
      const response = await apiGet<AppNotification[]>("/notifications", {
        status,
        page,
        per_page: PER_PAGE,
      });

      return {
        items: response.data,
        unread: response.meta?.unread_count ?? 0,
        pagination: response.meta?.pagination ?? null,
      };
    },
  });

  const refresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ["/notifications"] });
  };

  const open = async (item: AppNotification) => {
    if (!item.read_at) {
      await apiPost(`/notifications/${item.id}/read`);
      await refresh();
    }

    if (item.grant_application_id) {
      router.push(`/dashboard/bantuan/${item.grant_application_id}#komentar`);
    }
  };

  const markUnread = async (item: AppNotification) => {
    await apiPost(`/notifications/${item.id}/unread`);
    await refresh();
  };

  const markAllRead = async () => {
    await apiPost("/notifications/read-all");
    await refresh();
  };

  const lastPage = notifications.data?.pagination?.last_page ?? 1;
  const items = notifications.data?.items ?? [];
  const unread = notifications.data?.unread ?? 0;

  let listBody: ReactNode;
  if (notifications.isLoading) {
    listBody = <p className="px-4 py-8 text-center text-muted-foreground text-sm">Memuat notifikasi...</p>;
  } else if (items.length === 0) {
    listBody = (
      <p className="px-4 py-8 text-center text-muted-foreground text-sm">
        {status === "unread" ? "Tidak ada notifikasi belum dibaca" : "Tidak ada notifikasi"}
      </p>
    );
  } else {
    listBody = (
      <ul>
        {items.map((item) => {
          const text = notificationText(item);

          return (
            <li key={item.id} className="flex items-start gap-2 border-b px-3 py-3 last:border-b-0">
              <button
                type="button"
                className="flex min-w-0 flex-1 flex-col gap-0.5 text-left"
                onClick={() => void open(item)}
              >
                <span className="flex items-center gap-2">
                  {item.read_at ? null : <span className="size-2 shrink-0 rounded-full bg-primary" aria-hidden />}
                  <span className={item.read_at ? "text-sm" : "font-medium text-sm"}>{text}</span>
                </span>
                {item.excerpt ? (
                  <span className="line-clamp-2 text-muted-foreground text-xs">{item.excerpt}</span>
                ) : null}
                {item.created_at ? (
                  <time className="text-muted-foreground text-xs" dateTime={item.created_at}>
                    {notificationTime(item.created_at)}
                  </time>
                ) : null}
              </button>
              <div className="flex shrink-0 flex-col items-end gap-1">
                {item.read_at ? (
                  <Button
                    size="xs"
                    variant="ghost"
                    aria-label={`Tandai belum dibaca: ${text}`}
                    onClick={() => void markUnread(item)}
                  >
                    Tandai belum dibaca
                  </Button>
                ) : null}
                <Button
                  size="xs"
                  variant="ghost"
                  aria-label={`Hapus notifikasi: ${text}`}
                  onClick={() => setPendingDelete(item)}
                >
                  Hapus
                </Button>
              </div>
            </li>
          );
        })}
      </ul>
    );
  }

  return (
    <div className="mx-auto flex w-full max-w-3xl flex-col gap-4">
      <PageHeader
        title="Notifikasi"
        description="Semua pemberitahuan Anda, yang sudah dibaca maupun yang belum."
        actions={
          <>
            <Button variant="outline" disabled={unread === 0} onClick={() => void markAllRead()}>
              Tandai semua dibaca
            </Button>
            <Button variant="outline" onClick={() => setClearReadOpen(true)}>
              Hapus yang sudah dibaca
            </Button>
          </>
        }
      />

      <Tabs
        value={status}
        onValueChange={(value) => {
          setStatus(value as InboxStatus);
          setPage(1);
        }}
      >
        <TabsList>
          <TabsTrigger value="all">Semua</TabsTrigger>
          <TabsTrigger value="unread">Belum dibaca</TabsTrigger>
        </TabsList>
      </Tabs>

      {notifications.isError ? (
        <ErrorState onRetry={() => void notifications.refetch()} />
      ) : (
        <div className="rounded-lg border bg-card">{listBody}</div>
      )}

      {lastPage > 1 ? (
        <div className="flex items-center justify-between gap-3">
          <p className="text-muted-foreground text-sm">
            Halaman {page} dari {lastPage}
          </p>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((current) => current - 1)}>
              Sebelumnya
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= lastPage}
              onClick={() => setPage((current) => current + 1)}
            >
              Berikutnya
            </Button>
          </div>
        </div>
      ) : null}

      <ConfirmActionDialog
        open={pendingDelete !== null}
        onOpenChange={(open) => {
          if (!open) {
            setPendingDelete(null);
          }
        }}
        title="Hapus notifikasi?"
        description={pendingDelete ? notificationText(pendingDelete) : undefined}
        confirmLabel="Hapus"
        destructive
        onConfirm={async () => {
          if (!pendingDelete) {
            return;
          }

          await apiDelete(`/notifications/${pendingDelete.id}`);
          await refresh();
        }}
      />
      <ConfirmActionDialog
        open={clearReadOpen}
        onOpenChange={setClearReadOpen}
        title="Hapus yang sudah dibaca?"
        description="Notifikasi yang belum dibaca tetap ada."
        confirmLabel="Hapus yang sudah dibaca"
        destructive
        onConfirm={async () => {
          await apiDelete("/notifications/read");
          setPage(1);
          await refresh();
        }}
      />
    </div>
  );
}
