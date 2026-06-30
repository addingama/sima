"use client";

import { useRef, useState } from "react";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Download, Paperclip, Trash2, Upload } from "lucide-react";
import { toast } from "sonner";

import { ErrorState } from "@/components/sima/error-state";
import { TableSkeleton } from "@/components/sima/skeletons";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { apiDelete, apiFetch, apiGet } from "@/lib/api/client";
import type { AttachmentRecord } from "@/lib/api/entities";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format/datetime";
import { useAuth } from "@/providers/auth-provider";

export function AttachmentPanel({
  attachableType,
  attachableId,
  managePermission,
}: {
  attachableType: "receipt" | "disbursement" | "bank_fee";
  attachableId: number;
  managePermission: string;
}) {
  const { user } = useAuth();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [title, setTitle] = useState("");
  const queryClient = useQueryClient();
  const canManage = hasPermission(user, managePermission);

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ["/attachments", attachableType, attachableId],
    queryFn: async () => {
      const response = await apiGet<AttachmentRecord[]>("/attachments", {
        attachable_type: attachableType,
        attachable_id: attachableId,
      });

      return response.data;
    },
  });

  const uploadMutation = useMutation({
    mutationFn: async (file: File) => {
      const formData = new FormData();
      formData.append("attachable_type", attachableType);
      formData.append("attachable_id", String(attachableId));
      formData.append("file", file);
      if (title.trim()) {
        formData.append("title", title.trim());
      }

      await apiFetch("/attachments", { method: "POST", body: formData });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["/attachments", attachableType, attachableId] });
      setTitle("");
      toast.success("Lampiran berhasil diunggah.");
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (attachmentId: number) => {
      await apiDelete(`/attachments/${attachmentId}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["/attachments", attachableType, attachableId] });
      toast.success("Lampiran dihapus.");
    },
  });

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-4">
        <CardTitle>Lampiran</CardTitle>
        {canManage ? (
          <div className="flex flex-wrap items-center gap-2">
            <Input
              value={title}
              onChange={(event) => setTitle(event.target.value)}
              placeholder="Judul (opsional)"
              className="h-9 w-44"
            />
            <input
              ref={fileInputRef}
              type="file"
              className="hidden"
              onChange={(event) => {
                const file = event.target.files?.[0];
                if (file) {
                  uploadMutation.mutate(file);
                }
                event.target.value = "";
              }}
            />
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={uploadMutation.isPending}
              onClick={() => fileInputRef.current?.click()}
            >
              <Upload className="size-4" />
              Unggah
            </Button>
          </div>
        ) : null}
      </CardHeader>
      <CardContent>
        {isError ? (
          <ErrorState onRetry={() => refetch()} />
        ) : isLoading ? (
          <TableSkeleton rows={3} />
        ) : !data?.length ? (
          <p className="text-muted-foreground text-sm">Belum ada lampiran.</p>
        ) : (
          <ul className="space-y-3">
            {data.map((attachment) => (
              <li
                key={attachment.id}
                className="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="flex items-start gap-3">
                  <Paperclip className="mt-0.5 size-4 text-muted-foreground" />
                  <div>
                    <p className="font-medium text-sm">{attachment.title || attachment.original_name}</p>
                    <p className="text-muted-foreground text-xs">
                      {attachment.original_name} · {formatDateTime(attachment.created_at)}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <Button asChild size="sm" variant="outline">
                    <a href={attachment.url} target="_blank" rel="noreferrer">
                      <Download className="size-4" />
                      Unduh
                    </a>
                  </Button>
                  {canManage ? (
                    <Button
                      size="sm"
                      variant="ghost"
                      disabled={deleteMutation.isPending}
                      onClick={() => deleteMutation.mutate(attachment.id)}
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
