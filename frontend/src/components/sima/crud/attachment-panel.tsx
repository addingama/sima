"use client";

import type { ReactNode } from "react";
import { useEffect, useRef, useState } from "react";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Download,
  ExternalLink,
  Eye,
  FileText,
  ImageIcon,
  Paperclip,
  RotateCcw,
  Trash2,
  Upload,
  ZoomIn,
  ZoomOut,
} from "lucide-react";
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
const PREVIEW_MIN_ZOOM = 1;
const PREVIEW_MAX_ZOOM = 6;
const PREVIEW_ZOOM_STEP = 0.25;

function isImageAttachment(attachment: AttachmentRecord): boolean {
  return attachment.mime_type.startsWith("image/");
}

function isPdfAttachment(attachment: AttachmentRecord): boolean {
  return attachment.mime_type === "application/pdf" || attachment.original_name.toLowerCase().endsWith(".pdf");
}

function clampPreviewZoom(value: number): number {
  return Math.min(PREVIEW_MAX_ZOOM, Math.max(PREVIEW_MIN_ZOOM, value));
}

function AttachmentImagePreview({ attachment }: { attachment: AttachmentRecord }) {
  const viewportRef = useRef<HTMLDivElement>(null);
  const dragRef = useRef<{ x: number; y: number } | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [status, setStatus] = useState<"loading" | "error" | "ready">("loading");
  const [retry, setRetry] = useState(0);
  const [zoom, setZoom] = useState(PREVIEW_MIN_ZOOM);
  const [offset, setOffset] = useState({ x: 0, y: 0 });
  const [isDragging, setIsDragging] = useState(false);

  useEffect(() => {
    void retry;
    let cancelled = false;
    let objectUrl: string | null = null;
    setStatus("loading");
    setPreviewUrl(null);
    setZoom(PREVIEW_MIN_ZOOM);
    setOffset({ x: 0, y: 0 });

    void apiBlob(`/attachments/${attachment.id}/download`)
      .then((blob) => {
        if (cancelled) {
          return;
        }
        const url = URL.createObjectURL(blob);
        if (cancelled) {
          URL.revokeObjectURL(url);
          return;
        }
        objectUrl = url;
        setPreviewUrl(url);
        setStatus("ready");
      })
      .catch(() => {
        if (!cancelled) {
          setStatus("error");
        }
      });

    return () => {
      cancelled = true;
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
      }
    };
  }, [attachment.id, retry]);

  const applyZoom = (next: number) => {
    const clamped = clampPreviewZoom(next);
    setZoom(clamped);
    if (clamped <= PREVIEW_MIN_ZOOM) {
      setOffset({ x: 0, y: 0 });
    }
  };

  useEffect(() => {
    const viewport = viewportRef.current;
    if (!viewport || status !== "ready") {
      return;
    }

    const onWheel = (event: WheelEvent) => {
      event.preventDefault();
      const direction = event.deltaY > 0 ? -1 : 1;
      setZoom((current) => {
        const next = clampPreviewZoom(current + direction * PREVIEW_ZOOM_STEP);
        if (next <= PREVIEW_MIN_ZOOM) {
          setOffset({ x: 0, y: 0 });
        }
        return next;
      });
    };

    viewport.addEventListener("wheel", onWheel, { passive: false });
    return () => viewport.removeEventListener("wheel", onWheel);
  }, [status]);

  if (status === "loading") {
    return (
      <div className="flex h-full min-h-64 items-center justify-center">
        <TableSkeleton rows={3} />
      </div>
    );
  }

  if (status === "error") {
    return (
      <div className="flex h-full min-h-64 items-center justify-center">
        <ErrorState onRetry={() => setRetry((count) => count + 1)} />
      </div>
    );
  }

  if (!previewUrl) {
    return null;
  }

  let cursor = "zoom-in";
  if (zoom > PREVIEW_MIN_ZOOM) {
    cursor = isDragging ? "grabbing" : "grab";
  }

  return (
    <div className="relative flex h-full min-h-0 flex-col">
      <section
        ref={viewportRef}
        aria-label="Preview gambar"
        className="relative min-h-0 flex-1 touch-none overflow-hidden rounded-lg border bg-neutral-950/90"
        onPointerDown={(event) => {
          if (zoom <= PREVIEW_MIN_ZOOM || event.button !== 0) {
            return;
          }
          dragRef.current = { x: event.clientX, y: event.clientY };
          setIsDragging(true);
          event.currentTarget.setPointerCapture(event.pointerId);
        }}
        onPointerMove={(event) => {
          if (!dragRef.current) {
            return;
          }
          const dx = event.clientX - dragRef.current.x;
          const dy = event.clientY - dragRef.current.y;
          dragRef.current = { x: event.clientX, y: event.clientY };
          setOffset((current) => ({ x: current.x + dx, y: current.y + dy }));
        }}
        onPointerUp={() => {
          dragRef.current = null;
          setIsDragging(false);
        }}
        onPointerCancel={() => {
          dragRef.current = null;
          setIsDragging(false);
        }}
        onDoubleClick={() => {
          if (zoom > PREVIEW_MIN_ZOOM) {
            applyZoom(PREVIEW_MIN_ZOOM);
            return;
          }
          applyZoom(2);
        }}
      >
        <div
          className="flex h-full w-full items-center justify-center"
          style={{
            transform: `translate(${offset.x}px, ${offset.y}px) scale(${zoom})`,
            transformOrigin: "center center",
            cursor,
          }}
        >
          {/* biome-ignore lint/performance/noImgElement: blob URL lokal, bukan aset Next.js */}
          <img
            src={previewUrl}
            alt={attachment.original_name}
            draggable={false}
            className="max-h-full max-w-full select-none object-contain"
          />
        </div>
      </section>

      <div className="pointer-events-none absolute inset-x-0 bottom-3 flex justify-center">
        <div className="pointer-events-auto flex items-center gap-1 rounded-full border bg-background/95 p-1 shadow-md">
          <Button
            type="button"
            size="icon-sm"
            variant="ghost"
            aria-label="Perkecil"
            disabled={zoom <= PREVIEW_MIN_ZOOM}
            onClick={() => applyZoom(zoom - PREVIEW_ZOOM_STEP)}
          >
            <ZoomOut className="size-4" />
          </Button>
          <span className="min-w-12 text-center font-medium text-xs tabular-nums">{Math.round(zoom * 100)}%</span>
          <Button
            type="button"
            size="icon-sm"
            variant="ghost"
            aria-label="Perbesar"
            disabled={zoom >= PREVIEW_MAX_ZOOM}
            onClick={() => applyZoom(zoom + PREVIEW_ZOOM_STEP)}
          >
            <ZoomIn className="size-4" />
          </Button>
          <Button
            type="button"
            size="icon-sm"
            variant="ghost"
            aria-label="Sesuai layar"
            disabled={zoom <= PREVIEW_MIN_ZOOM && offset.x === 0 && offset.y === 0}
            onClick={() => applyZoom(PREVIEW_MIN_ZOOM)}
          >
            <RotateCcw className="size-4" />
          </Button>
        </div>
      </div>
      <p className="sr-only">Gulir untuk zoom. Geser gambar jika sudah diperbesar. Klik dua kali untuk zoom cepat.</p>
    </div>
  );
}

function AttachmentKindIcon({
  attachment,
  openingPdfId,
  onPreviewImage,
  onOpenPdf,
}: {
  attachment: AttachmentRecord;
  openingPdfId: number | null;
  onPreviewImage: (attachment: AttachmentRecord) => void;
  onOpenPdf: (attachment: AttachmentRecord) => void;
}) {
  if (isImageAttachment(attachment)) {
    return (
      <button
        type="button"
        className="flex size-12 shrink-0 items-center justify-center rounded-md border bg-muted/30 text-muted-foreground transition-colors hover:bg-muted"
        onClick={() => onPreviewImage(attachment)}
        aria-label={`Preview ${attachment.original_name}`}
      >
        <ImageIcon className="size-5" />
      </button>
    );
  }

  if (isPdfAttachment(attachment)) {
    return (
      <button
        type="button"
        className="flex size-12 shrink-0 items-center justify-center rounded-md border bg-muted/30 text-muted-foreground transition-colors hover:bg-muted"
        disabled={openingPdfId === attachment.id}
        onClick={() => onOpenPdf(attachment)}
        aria-label={`Buka PDF ${attachment.original_name}`}
      >
        <FileText className="size-5" />
      </button>
    );
  }

  return <Paperclip className="mt-0.5 size-4 shrink-0 text-muted-foreground" />;
}

export function AttachmentPanel({
  attachableType,
  attachableId,
  managePermission,
  helperText,
  defaultTitle = "",
}: {
  attachableType: "receipt" | "disbursement" | "bank_fee" | "grant_application";
  attachableId: number;
  managePermission: string;
  helperText?: string;
  defaultTitle?: string;
}) {
  const { user } = useAuth();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [title, setTitle] = useState(defaultTitle);
  const [previewAttachment, setPreviewAttachment] = useState<AttachmentRecord | null>(null);
  const [openingPdfId, setOpeningPdfId] = useState<number | null>(null);
  const pdfObjectUrlsRef = useRef<string[]>([]);
  const queryClient = useQueryClient();
  const canManage = hasPermission(user, managePermission);

  useEffect(() => {
    return () => {
      for (const url of pdfObjectUrlsRef.current) {
        URL.revokeObjectURL(url);
      }
    };
  }, []);

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
      void queryClient.invalidateQueries({ queryKey: ["/attachments", attachableType, attachableId] });
      setTitle(defaultTitle);
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
      void queryClient.invalidateQueries({ queryKey: ["/attachments", attachableType, attachableId] });
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

  const openPdfInNewTab = async (attachment: AttachmentRecord) => {
    const tab = window.open("about:blank", "_blank");
    if (!tab) {
      toast.error("Izinkan pop-up browser untuk membuka PDF.");
      return;
    }

    setOpeningPdfId(attachment.id);
    try {
      const blob = await apiBlob(`/attachments/${attachment.id}/download`);
      const pdfBlob = new Blob([blob], { type: "application/pdf" });
      const objectUrl = URL.createObjectURL(pdfBlob);
      pdfObjectUrlsRef.current.push(objectUrl);
      tab.location.replace(objectUrl);
    } catch (error) {
      tab.close();
      toast.error(error instanceof ApiError ? error.message : "Gagal membuka PDF.");
    } finally {
      setOpeningPdfId(null);
    }
  };

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
              <AttachmentKindIcon
                attachment={attachment}
                openingPdfId={openingPdfId}
                onPreviewImage={setPreviewAttachment}
                onOpenPdf={(item) => void openPdfInNewTab(item)}
              />
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
              {isPdfAttachment(attachment) ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={openingPdfId === attachment.id}
                  onClick={() => void openPdfInNewTab(attachment)}
                >
                  <ExternalLink className="size-4" />
                  {openingPdfId === attachment.id ? "Membuka..." : "Buka"}
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

  return (
    <>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-4">
          <div className="space-y-1">
            <CardTitle>Lampiran</CardTitle>
            {helperText ? <p className="text-muted-foreground text-sm">{helperText}</p> : null}
          </div>
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
        <DialogContent className="flex h-[92vh] w-[min(96vw,90rem)] max-w-[min(96vw,90rem)] flex-col gap-2 overflow-hidden p-3 sm:max-w-[min(96vw,90rem)]">
          <DialogHeader className="shrink-0 space-y-0 pr-10">
            <DialogTitle className="truncate">
              {previewAttachment?.title?.trim()
                ? previewAttachment.title
                : (previewAttachment?.original_name ?? "Preview lampiran")}
            </DialogTitle>
          </DialogHeader>
          <div className="min-h-0 flex-1">
            {previewAttachment ? (
              <AttachmentImagePreview key={previewAttachment.id} attachment={previewAttachment} />
            ) : null}
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}
