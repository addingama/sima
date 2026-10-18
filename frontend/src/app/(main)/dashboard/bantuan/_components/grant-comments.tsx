"use client";

import { useEffect, useRef, useState } from "react";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import {
  commentSegments,
  insertMention,
  type MentionUser,
  mentionQueryAtCursor,
  toEditorText,
  toStoredBody,
} from "@/app/(main)/dashboard/bantuan/_components/grant-comment-text";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Popover, PopoverAnchor, PopoverContent } from "@/components/ui/popover";
import { Textarea } from "@/components/ui/textarea";
import { ApiError, apiDelete, apiGet, apiPost, apiPut } from "@/lib/api/client";

type GrantComment = {
  id: number;
  body: string;
  edited_at: string | null;
  created_at: string | null;
  user: MentionUser | null;
  mentions: MentionUser[];
  can_edit: boolean;
  can_delete: boolean;
};

function useMentionCaret(draft: string, cursor: number, setCursor: (position: number) => void) {
  const ref = useRef<HTMLTextAreaElement>(null);
  const pending = useRef<number | null>(null);

  useEffect(() => {
    const element = ref.current;
    const position = pending.current;

    if (!element || position === null || position > draft.length) {
      return;
    }

    element.focus();
    element.setSelectionRange(position, position);
    pending.current = null;

    if (cursor !== position) {
      setCursor(position);
    }
  }, [cursor, draft, setCursor]);

  return {
    ref,
    pending,
    place(position: number) {
      pending.current = position;
    },
  };
}

function formatWhen(value: string | null): string {
  if (!value) {
    return "";
  }

  return new Date(value).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" });
}

function CommentBody({ body, mentions }: { body: string; mentions: MentionUser[] }) {
  return (
    <p className="whitespace-pre-wrap text-sm">
      {commentSegments(body, mentions).map((segment) =>
        segment.type === "mention" ? (
          <span key={`mention-${segment.offset}`} className="font-medium text-primary">
            @{segment.name}
          </span>
        ) : (
          <span key={`text-${segment.offset}`}>{segment.text}</span>
        ),
      )}
    </p>
  );
}

function CommentComposer({ grantId, onSaved }: { grantId: number; onSaved: () => Promise<void> | void }) {
  const [draft, setDraft] = useState("");
  const [mentions, setMentions] = useState<MentionUser[]>([]);
  const [cursor, setCursor] = useState(0);
  const [busy, setBusy] = useState(false);
  const caret = useMentionCaret(draft, cursor, setCursor);
  const activeQuery = mentionQueryAtCursor(draft, cursor);
  const suggestions = useQuery({
    queryKey: ["/grant-applications/mentionable-users", activeQuery ? activeQuery.query : ""],
    enabled: activeQuery !== null,
    queryFn: async () => {
      const response = await apiGet<MentionUser[]>("/grant-applications/mentionable-users", {
        q: activeQuery ? activeQuery.query : "",
      });

      return response.data;
    },
  });

  const choose = (user: MentionUser) => {
    if (!activeQuery) {
      return;
    }

    const next = insertMention(draft, activeQuery.start, cursor, user);
    caret.place(next.cursor);
    setDraft(next.value);
    setCursor(next.cursor);
    setMentions((current) => (current.some((item) => item.id === user.id) ? current : [...current, user]));
  };

  return (
    <form
      className="space-y-2"
      onSubmit={(event) => {
        event.preventDefault();
        const body = toStoredBody(draft, mentions).trim();

        if (!body) {
          return;
        }

        setBusy(true);
        void apiPost(`/grant-applications/${grantId}/comments`, { body })
          .then(async () => {
            setDraft("");
            setMentions([]);
            setCursor(0);
            await onSaved();
          })
          .catch((error: unknown) => {
            toast.error(error instanceof ApiError ? error.message : "Gagal menyimpan komentar.");
          })
          .finally(() => setBusy(false));
      }}
    >
      <Popover open={activeQuery !== null}>
        <PopoverAnchor asChild>
          <Textarea
            ref={caret.ref}
            aria-label="Tulis komentar"
            value={draft}
            rows={3}
            placeholder="Tulis komentar. Ketik @ untuk menyebut seseorang."
            onChange={(event) => {
              setDraft(event.target.value);
              setCursor(event.target.selectionStart ?? event.target.value.length);
            }}
            onSelect={(event) => {
              if (caret.pending.current !== null) {
                return;
              }

              setCursor(event.currentTarget.selectionStart ?? draft.length);
            }}
          />
        </PopoverAnchor>
        <PopoverContent align="start" className="w-64 p-1" onOpenAutoFocus={(event) => event.preventDefault()}>
          <div role="listbox" aria-label="Pengguna yang bisa disebut" className="max-h-48 overflow-y-auto">
            {(suggestions.data ?? []).length === 0 ? (
              <p className="px-2 py-1.5 text-muted-foreground text-sm">Tidak ada pengguna</p>
            ) : (
              suggestions.data?.map((user) => (
                <button
                  key={user.id}
                  type="button"
                  role="option"
                  className="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-muted"
                  onMouseDown={(event) => {
                    event.preventDefault();
                    choose(user);
                  }}
                >
                  {user.name}
                </button>
              ))
            )}
          </div>
        </PopoverContent>
      </Popover>
      <div className="flex justify-end gap-2">
        <Button type="submit" disabled={busy || draft.trim() === ""}>
          Kirim
        </Button>
      </div>
    </form>
  );
}

export function GrantComments({
  grantId,
  mentionAccess,
  onChanged,
}: {
  grantId: number;
  mentionAccess: MentionUser[];
  onChanged: () => void;
}) {
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [editingId, setEditingId] = useState<number | null>(null);
  const comments = useQuery({
    queryKey: ["/grant-applications", grantId, "comments", page],
    queryFn: async () => {
      const response = await apiGet<GrantComment[]>(`/grant-applications/${grantId}/comments`, {
        page,
        per_page: 100,
      });

      return {
        rows: response.data,
        lastPage: response.meta?.pagination?.last_page ?? 1,
      };
    },
  });

  useEffect(() => {
    void apiPost("/notifications/read-all", { grant_application_id: grantId })
      .then(() => queryClient.invalidateQueries({ queryKey: ["/notifications"] }))
      .catch(() => undefined);
  }, [grantId, queryClient]);

  const refresh = async () => {
    await comments.refetch();
    onChanged();
  };

  const remove = async (commentId: number) => {
    if (!window.confirm("Hapus komentar ini?")) {
      return;
    }

    try {
      await apiDelete(`/grant-applications/${grantId}/comments/${commentId}`);
      await refresh();
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal menghapus komentar.");
    }
  };

  return (
    <Card id="komentar">
      <CardHeader>
        <CardTitle>Komentar</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {mentionAccess.length > 0 ? (
          <p className="text-muted-foreground text-sm">
            Bisa melihat lewat mention: {mentionAccess.map((user) => user.name).join(", ")}
          </p>
        ) : null}
        {comments.data?.rows.length ? (
          <ol className="space-y-3">
            {comments.data.rows.map((comment) => (
              <li key={comment.id} className="rounded-md border p-3">
                <div className="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                  <p className="font-medium text-sm">{comment.user?.name ?? "Pengguna"}</p>
                  <p className="text-muted-foreground text-xs">
                    {formatWhen(comment.created_at)}
                    {comment.edited_at ? " · diedit" : ""}
                  </p>
                </div>
                {editingId === comment.id ? (
                  <EditComment
                    grantId={grantId}
                    comment={comment}
                    onCancel={() => setEditingId(null)}
                    onSaved={async () => {
                      setEditingId(null);
                      await refresh();
                    }}
                  />
                ) : (
                  <>
                    <CommentBody body={comment.body} mentions={comment.mentions} />
                    {comment.can_edit || comment.can_delete ? (
                      <div className="mt-2 flex gap-2">
                        {comment.can_edit ? (
                          <Button type="button" variant="ghost" size="sm" onClick={() => setEditingId(comment.id)}>
                            Ubah
                          </Button>
                        ) : null}
                        {comment.can_delete ? (
                          <Button type="button" variant="ghost" size="sm" onClick={() => void remove(comment.id)}>
                            Hapus
                          </Button>
                        ) : null}
                      </div>
                    ) : null}
                  </>
                )}
              </li>
            ))}
          </ol>
        ) : (
          <p className="text-muted-foreground text-sm">
            {comments.isLoading ? "Memuat komentar..." : "Belum ada komentar."}
          </p>
        )}
        {(comments.data?.lastPage ?? 1) > page ? (
          <Button type="button" variant="outline" onClick={() => setPage((current) => current + 1)}>
            Komentar berikutnya
          </Button>
        ) : null}
        <CommentComposer grantId={grantId} onSaved={refresh} />
      </CardContent>
    </Card>
  );
}

function EditComment({
  grantId,
  comment,
  onCancel,
  onSaved,
}: {
  grantId: number;
  comment: GrantComment;
  onCancel: () => void;
  onSaved: () => Promise<void>;
}) {
  const [draft, setDraft] = useState(() => toEditorText(comment.body, comment.mentions));
  const [mentions, setMentions] = useState<MentionUser[]>(comment.mentions);
  const [cursor, setCursor] = useState(draft.length);
  const [busy, setBusy] = useState(false);
  const caret = useMentionCaret(draft, cursor, setCursor);
  const activeQuery = mentionQueryAtCursor(draft, cursor);
  const suggestions = useQuery({
    queryKey: ["/grant-applications/mentionable-users", "edit", activeQuery ? activeQuery.query : ""],
    enabled: activeQuery !== null,
    queryFn: async () => {
      const response = await apiGet<MentionUser[]>("/grant-applications/mentionable-users", {
        q: activeQuery ? activeQuery.query : "",
      });

      return response.data;
    },
  });

  return (
    <form
      className="space-y-2"
      onSubmit={(event) => {
        event.preventDefault();
        const body = toStoredBody(draft, mentions).trim();

        if (!body) {
          return;
        }

        setBusy(true);
        void apiPut(`/grant-applications/${grantId}/comments/${comment.id}`, { body })
          .then(() => onSaved())
          .catch((error: unknown) => {
            toast.error(error instanceof ApiError ? error.message : "Gagal menyimpan komentar.");
          })
          .finally(() => setBusy(false));
      }}
    >
      <Popover open={activeQuery !== null}>
        <PopoverAnchor asChild>
          <Textarea
            ref={caret.ref}
            aria-label="Ubah komentar"
            value={draft}
            rows={3}
            onChange={(event) => {
              setDraft(event.target.value);
              setCursor(event.target.selectionStart ?? event.target.value.length);
            }}
            onSelect={(event) => {
              if (caret.pending.current !== null) {
                return;
              }

              setCursor(event.currentTarget.selectionStart ?? draft.length);
            }}
          />
        </PopoverAnchor>
        <PopoverContent align="start" className="w-64 p-1" onOpenAutoFocus={(event) => event.preventDefault()}>
          <div role="listbox" aria-label="Pengguna yang bisa disebut">
            {suggestions.data?.map((user) => (
              <button
                key={user.id}
                type="button"
                role="option"
                className="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-muted"
                onMouseDown={(event) => {
                  event.preventDefault();
                  if (!activeQuery) {
                    return;
                  }
                  const next = insertMention(draft, activeQuery.start, cursor, user);
                  caret.place(next.cursor);
                  setDraft(next.value);
                  setCursor(next.cursor);
                  setMentions((current) =>
                    current.some((item) => item.id === user.id) ? current : [...current, user],
                  );
                }}
              >
                {user.name}
              </button>
            ))}
          </div>
        </PopoverContent>
      </Popover>
      <div className="flex justify-end gap-2">
        <Button type="button" variant="ghost" onClick={onCancel}>
          Batal
        </Button>
        <Button type="submit" disabled={busy || draft.trim() === ""}>
          Simpan
        </Button>
      </div>
    </form>
  );
}
