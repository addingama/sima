"use client";

import type { ReactNode } from "react";
import { useEffect, useRef, useState } from "react";

import Image from "next/image";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Download, Eye, ImageIcon, Paperclip, Trash2, Upload } from "lucide-react";
import { toast } from "sonner";

import { ErrorState } from "@/components/sima/error-state";
import { TableSkeleton } from "@/components/sima/skeletons";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { ApiError, apiBlob, apiDelete, apiDownload, apiFetch, apiGet } from "@/lib/api/client";
import type { AttachmentRecord } from "@/lib/api/entities";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDateTime } from "@/lib/format/datetime";
import { useAuth } from "@/providers/auth-provider";

const ACCEPTED_TYPES = ".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx";
const MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

function isImageAttachment(attachment: AttachmentRecord): boolean {
  return attachment.mime_type.startsWith("image/");
}

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
  const [previewAttachment, setPreviewAttachment] = useState<AttachmentRecord | null>(null);
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
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Gagal mengunggah lampiran.");
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
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Gagal menghapus lampiran.");
    },
  });

  const downloadMutation = useMutation({
    mutationFn: async (attachment: AttachmentRecord) => {
      await apiDownload(`/attachments/${attachment.id}/download`, attachment.original_name);
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Gagal mengunduh lampiran.");
    },
  });

  const previewQuery = useQuery({
    queryKey: ["/attachments/preview", previewAttachment?.id],
    enabled: previewAttachment !== null,
    queryFn: async () => {
      if (!previewAttachment) {
        throw new Error("Lampiran tidak dipilih.");
      }

      const blob = await apiBlob(`/attachments/${previewAttachment.id}/download`);

      return URL.createObjectURL(blob);
    },
  });

  useEffect(() => {
    const objectUrl = previewQuery.data;

    return () => {
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
      }
    };
  }, [previewQuery.data]);

  let attachmentListContent: ReactNode;

  if (isError) {
    attachmentListContent = <ErrorState onRetry={() => refetch()} />;
  } else if (isLoading) {
    attachmentListContent = <TableSkeleton rows={3} />;
  } else if (!data?.length) {
    attachmentListContent = <p className="text-muted-foreground text-sm">Belum ada lampiran.</p>;
  } else {
    attachmentListContent = (
      <ul className="space-y-3">
        {data.map((attachment) => (
          <li
            key={attachment.id}
            className="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
          >
            <div className="flex min-w-0 items-start gap-3">
              {isImageAttachment(attachment) ? (
                <button
                  type="button"
                  className="flex size-12 shrink-0 items-center justify-center rounded-md border bg-muted/30 text-muted-foreground transition-colors hover:bg-muted"
                  onClick={() => setPreviewAttachment(attachment)}
                  aria-label={`Preview ${attachment.original_name}`}
                >
                  <ImageIcon className="size-5" />
                </button>
              ) : (
                <Paperclip className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
              )}
              <div className="min-w-0">
                <p className="truncate font-medium text-sm">{attachment.title || attachment.original_name}</p>
                <p className="text-muted-foreground text-xs">
                  {attachment.original_name} · {formatDateTime(attachment.created_at)}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              {isImageAttachment(attachment) ? (
                <Button type="button" size="sm" variant="outline" onClick={() => setPreviewAttachment(attachment)}>
                  <Eye className="size-4" />
                  Preview
                </Button>
              ) : null}
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={downloadMutation.isPending}
                onClick={() => downloadMutation.mutate(attachment)}
              >
                <Download className="size-4" />
                Unduh
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
    );
  }

  let previewContent: ReactNode = null;

  if (previewQuery.isLoading) {
    previewContent = <TableSkeleton rows={3} />;
  } else if (previewQuery.isError) {
    previewContent = <ErrorState onRetry={() => previewQuery.refetch()} />;
  } else if (previewQuery.data) {
    previewContent = (
      <Image
        src={previewQuery.data}
        alt={previewAttachment?.original_name ?? "Preview lampiran"}
        width={1600}
        height={1200}
        unoptimized
        className="max-h-[72vh] w-auto max-w-full object-contain"
      />
    );
  }

  return (
    <>
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
                accept={ACCEPTED_TYPES}
                className="hidden"
                onChange={(event) => {
                  const file = event.target.files?.[0];
                  if (file) {
                    if (file.size > MAX_ATTACHMENT_BYTES) {
                      toast.error("Ukuran lampiran maksimal 20 MB.");
                      event.target.value = "";
                      return;
                    }
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
        <CardContent>{attachmentListContent}</CardContent>
      </Card>

      <Dialog open={previewAttachment !== null} onOpenChange={(open) => !open && setPreviewAttachment(null)}>
        <DialogContent className="max-w-4xl">
          <DialogHeader>
            <DialogTitle>
              {previewAttachment?.title || previewAttachment?.original_name || "Preview lampiran"}
            </DialogTitle>
          </DialogHeader>
          <div className="flex max-h-[75vh] min-h-64 items-center justify-center overflow-auto rounded-lg border bg-muted/20">
            {previewContent}
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}
