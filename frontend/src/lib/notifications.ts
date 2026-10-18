import { formatDistanceToNow } from "date-fns";
import { id } from "date-fns/locale";

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

export function notificationText(item: AppNotification): string {
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

export function notificationTime(value: string | null): string {
  if (!value) {
    return "";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "";
  }

  return formatDistanceToNow(date, { addSuffix: true, locale: id });
}
