"use client";

import { useState } from "react";

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Field, FieldLabel } from "@/components/ui/field";
import { Textarea } from "@/components/ui/textarea";

export function ConfirmActionDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel = "Lanjutkan",
  destructive = false,
  requiresReason = false,
  reasonLabel = "Alasan",
  reasonRequired = false,
  notesOptional = false,
  onConfirm,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description?: string;
  confirmLabel?: string;
  destructive?: boolean;
  requiresReason?: boolean;
  reasonLabel?: string;
  reasonRequired?: boolean;
  notesOptional?: boolean;
  onConfirm: (payload: { reason?: string; notes?: string }) => Promise<void> | void;
}) {
  const [reason, setReason] = useState("");
  const [notes, setNotes] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleConfirm = async () => {
    if (requiresReason && reasonRequired && !reason.trim()) {
      return;
    }

    setIsSubmitting(true);

    try {
      await onConfirm({
        reason: reason.trim() || undefined,
        notes: notes.trim() || undefined,
      });
      setReason("");
      setNotes("");
      onOpenChange(false);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          {description ? <AlertDialogDescription>{description}</AlertDialogDescription> : null}
        </AlertDialogHeader>

        <div className="space-y-3">
          {notesOptional ? (
            <Field className="gap-1.5">
              <FieldLabel htmlFor="workflow-notes">Catatan</FieldLabel>
              <Textarea
                id="workflow-notes"
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
                placeholder="Opsional"
              />
            </Field>
          ) : null}
          {requiresReason ? (
            <Field className="gap-1.5">
              <FieldLabel htmlFor="workflow-reason">{reasonLabel}</FieldLabel>
              <Textarea
                id="workflow-reason"
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                placeholder={reasonRequired ? "Wajib diisi" : "Opsional"}
                aria-invalid={reasonRequired && !reason.trim()}
              />
            </Field>
          ) : null}
        </div>

        <AlertDialogFooter>
          <AlertDialogCancel disabled={isSubmitting}>Batal</AlertDialogCancel>
          <AlertDialogAction
            variant={destructive ? "destructive" : "default"}
            disabled={isSubmitting || (requiresReason && reasonRequired && !reason.trim())}
            onClick={(event) => {
              event.preventDefault();
              void handleConfirm();
            }}
          >
            {isSubmitting ? "Memproses..." : confirmLabel}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
