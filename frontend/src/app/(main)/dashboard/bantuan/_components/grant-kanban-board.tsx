"use client";

import { useMemo, useState } from "react";

import Link from "next/link";

import {
  DndContext,
  type DragEndEvent,
  PointerSensor,
  useDraggable,
  useDroppable,
  useSensor,
  useSensors,
} from "@dnd-kit/core";
import { GripVertical } from "lucide-react";
import { toast } from "sonner";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { apiPost } from "@/lib/api/client";
import { cn } from "@/lib/utils";

export const ACTIVE_COLUMNS = [
  { id: "draft", title: "Rekomendasi" },
  { id: "verification", title: "Verifikasi" },
  { id: "pending_approval", title: "Menunggu approval" },
  { id: "approved", title: "Siap diserahkan" },
] as const;

export const ARCHIVE_COLUMNS = [
  { id: "completed", title: "Selesai" },
  { id: "rejected", title: "Ditolak" },
] as const;

const TRANSITIONS: Record<string, Record<string, string>> = {
  draft: { verification: "send-to-verification" },
  verification: { pending_approval: "submit-for-approval" },
  pending_approval: { approved: "approve" },
};

export type GrantBoardMode = "active" | "archive";

export type GrantCard = {
  id: number;
  application_number?: string | null;
  recipient_name?: string | null;
  recommended_amount?: string | null;
  verified_amount?: string | null;
  approved_amount?: string | null;
  status: string;
  status_label?: string | null;
  assigned_verifier?: { id: number; name: string } | null;
};

function GrantKanbanCard({ grant, draggable }: { grant: GrantCard; draggable: boolean }) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: String(grant.id),
    data: { status: grant.status },
    disabled: !draggable,
  });
  const amount = grant.approved_amount ?? grant.verified_amount ?? grant.recommended_amount;

  return (
    <article
      ref={setNodeRef}
      style={{
        transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined,
      }}
      className={cn("rounded-md border bg-background p-2.5 shadow-xs", isDragging && "opacity-60")}
    >
      <div className="flex items-start gap-1.5">
        {draggable ? (
          <button type="button" className="mt-0.5 cursor-grab text-muted-foreground" {...listeners} {...attributes}>
            <GripVertical className="size-4" />
          </button>
        ) : null}
        <Link href={`/dashboard/bantuan/${grant.id}`} className="min-w-0 flex-1 hover:underline">
          <p className="truncate font-medium text-sm">{grant.recipient_name}</p>
          <div className="mt-0.5 flex items-center justify-between gap-2">
            <p className="truncate text-muted-foreground text-xs">{grant.application_number}</p>
            <CurrencyDisplay value={amount} className="shrink-0 text-xs" />
          </div>
          {grant.assigned_verifier?.name ? (
            <p className="mt-0.5 truncate text-muted-foreground text-xs">{grant.assigned_verifier.name}</p>
          ) : null}
        </Link>
      </div>
    </article>
  );
}

function GrantKanbanColumn({
  column,
  grants,
  draggable,
}: {
  column: { id: string; title: string };
  grants: GrantCard[];
  draggable: boolean;
}) {
  const { setNodeRef, isOver } = useDroppable({ id: column.id, disabled: !draggable });

  return (
    <section
      ref={setNodeRef}
      className={cn(
        "flex h-[calc(100dvh-16rem)] w-72 shrink-0 flex-col rounded-xl border bg-muted/40 p-3",
        isOver && "ring-2 ring-primary/40",
      )}
    >
      <header className="mb-2 flex items-center justify-between gap-2">
        <h2 className="font-medium text-sm">{column.title}</h2>
        <span className="text-muted-foreground text-xs tabular-nums">{grants.length}</span>
      </header>
      <div className="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto pr-0.5">
        {grants.length === 0 ? (
          <p className="px-1 text-muted-foreground text-xs">Kosong</p>
        ) : (
          grants.map((grant) => <GrantKanbanCard key={grant.id} grant={grant} draggable={draggable} />)
        )}
      </div>
    </section>
  );
}

export function GrantKanbanBoard({
  grants,
  mode,
  onMoved,
}: {
  grants: GrantCard[];
  mode: GrantBoardMode;
  onMoved: () => unknown;
}) {
  const [optimistic, setOptimistic] = useState<GrantCard[] | null>(null);
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));
  const rows = optimistic ?? grants;
  const columns = mode === "archive" ? ARCHIVE_COLUMNS : ACTIVE_COLUMNS;
  const grouped = useMemo(() => {
    const map: Record<string, GrantCard[]> = {};
    for (const column of columns) {
      map[column.id] = [];
    }
    for (const grant of rows) {
      if (!map[grant.status]) {
        continue;
      }
      map[grant.status].push(grant);
    }
    return map;
  }, [columns, rows]);

  const handleDragEnd = async (event: DragEndEvent) => {
    const grantId = Number(event.active.id);
    const toStatus = event.over ? String(event.over.id) : null;
    const grant = rows.find((item) => item.id === grantId);
    if (!grant || !toStatus || toStatus === grant.status) {
      return;
    }

    const action = TRANSITIONS[grant.status]?.[toStatus];
    if (!action) {
      toast.error(
        "Pindah kolom itu tidak diizinkan. Gunakan tombol di halaman detail jika butuh alasan atau kelengkapan.",
      );
      return;
    }

    const previous = rows;
    setOptimistic(rows.map((item) => (item.id === grantId ? { ...item, status: toStatus } : item)));

    try {
      await apiPost(`/grant-applications/${grantId}/${action}`);
      toast.success("Status diperbarui.");
      await onMoved();
      setOptimistic(null);
    } catch (error) {
      setOptimistic(previous);
      toast.error(error instanceof Error ? error.message : "Gagal memindahkan kartu.");
    }
  };

  return (
    <DndContext sensors={sensors} onDragEnd={(event) => void handleDragEnd(event)}>
      <div className="flex gap-3 overflow-x-auto pb-2">
        {columns.map((column) => (
          <GrantKanbanColumn
            key={column.id}
            column={column}
            grants={grouped[column.id] ?? []}
            draggable={mode === "active"}
          />
        ))}
      </div>
    </DndContext>
  );
}
