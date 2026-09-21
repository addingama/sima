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
import { StatusBadge } from "@/components/sima/status-badge";
import { apiPost } from "@/lib/api/client";
import { cn } from "@/lib/utils";

const COLUMNS = [
  { id: "draft", title: "Rekomendasi" },
  { id: "verification", title: "Verifikasi" },
  { id: "pending_approval", title: "Menunggu approval" },
  { id: "approved", title: "Siap diserahkan" },
  { id: "completed", title: "Selesai" },
  { id: "rejected", title: "Ditolak" },
] as const;

const TRANSITIONS: Record<string, Record<string, string>> = {
  draft: { verification: "send-to-verification" },
  verification: { pending_approval: "submit-for-approval" },
  pending_approval: { approved: "approve" },
};

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

function GrantKanbanCard({ grant }: { grant: GrantCard }) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: String(grant.id),
    data: { status: grant.status },
  });
  const amount = grant.approved_amount ?? grant.verified_amount ?? grant.recommended_amount;

  return (
    <article
      ref={setNodeRef}
      style={{
        transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined,
      }}
      className={cn("rounded-lg border bg-background p-3 shadow-sm", isDragging && "opacity-60")}
    >
      <div className="flex items-start justify-between gap-2">
        <button type="button" className="cursor-grab text-muted-foreground" {...listeners} {...attributes}>
          <GripVertical className="size-4" />
        </button>
        <Link href={`/dashboard/bantuan/${grant.id}`} className="min-w-0 flex-1 hover:underline">
          <p className="truncate font-medium text-sm">{grant.recipient_name}</p>
          <p className="text-muted-foreground text-xs">{grant.application_number}</p>
        </Link>
      </div>
      <div className="mt-2 flex items-center justify-between gap-2">
        <CurrencyDisplay value={amount} className="text-sm" />
        <StatusBadge status={grant.status} />
      </div>
      {grant.assigned_verifier?.name ? (
        <p className="mt-1 truncate text-muted-foreground text-xs">{grant.assigned_verifier.name}</p>
      ) : (
        <p className="mt-1 text-muted-foreground text-xs">Belum ada verifikator</p>
      )}
    </article>
  );
}

function GrantKanbanColumn({ column, grants }: { column: (typeof COLUMNS)[number]; grants: GrantCard[] }) {
  const { setNodeRef, isOver } = useDroppable({ id: column.id });

  return (
    <section
      ref={setNodeRef}
      className={cn(
        "flex min-h-72 min-w-64 flex-1 flex-col rounded-xl border bg-muted/40 p-3",
        isOver && "ring-2 ring-primary/40",
      )}
    >
      <header className="mb-3 flex items-center justify-between gap-2">
        <h2 className="font-medium text-sm">{column.title}</h2>
        <span className="text-muted-foreground text-xs tabular-nums">{grants.length}</span>
      </header>
      <div className="flex flex-1 flex-col gap-2">
        {grants.map((grant) => (
          <GrantKanbanCard key={grant.id} grant={grant} />
        ))}
      </div>
    </section>
  );
}

export function GrantKanbanBoard({ grants, onMoved }: { grants: GrantCard[]; onMoved: () => unknown }) {
  const [optimistic, setOptimistic] = useState<GrantCard[] | null>(null);
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));
  const rows = optimistic ?? grants;
  const grouped = useMemo(() => {
    const map: Record<string, GrantCard[]> = {};
    for (const column of COLUMNS) {
      map[column.id] = [];
    }
    for (const grant of rows) {
      if (!map[grant.status]) {
        map[grant.status] = [];
      }
      map[grant.status].push(grant);
    }
    return map;
  }, [rows]);

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
      <div className="flex gap-3 overflow-x-auto pb-4">
        {COLUMNS.map((column) => (
          <GrantKanbanColumn key={column.id} column={column} grants={grouped[column.id] ?? []} />
        ))}
      </div>
    </DndContext>
  );
}
