import { describe, expect, it } from "vitest";

import { type AppNotification, notificationText, notificationTime } from "./notifications";

const item = (overrides: Partial<AppNotification> = {}): AppNotification => ({
  id: "note-1",
  kind: "grant_comment",
  grant_application_id: 1,
  application_number: "BNT/2026/000001",
  comment_id: 1,
  actor_name: "Siti",
  excerpt: null,
  read_at: null,
  created_at: "2026-10-18T00:00:00Z",
  ...overrides,
});

describe("notificationText", () => {
  it("membedakan mention, komentar, dan notifikasi lain", () => {
    expect(notificationText(item({ kind: "grant_mention" }))).toBe("Siti menyebut Anda di BNT/2026/000001");
    expect(notificationText(item())).toBe("Siti berkomentar di BNT/2026/000001");
    expect(notificationText(item({ kind: null, actor_name: null }))).toBe("Seseorang mengirim notifikasi");
  });
});

describe("notificationTime", () => {
  it("mengosongkan nilai yang tidak valid dan memformat waktu lampau", () => {
    expect(notificationTime(null)).toBe("");
    expect(notificationTime("bukan-tanggal")).toBe("");
    expect(notificationTime(new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString())).toMatch(/jam/);
  });
});
