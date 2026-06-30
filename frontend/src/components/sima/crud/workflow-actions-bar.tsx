"use client";

import { useState } from "react";

import { toast } from "sonner";

import { ConfirmActionDialog } from "@/components/sima/crud/confirm-action-dialog";
import { Button } from "@/components/ui/button";
import { useWorkflowAction } from "@/hooks/use-resource-mutation";
import { hasPermission } from "@/lib/auth/permissions";
import type { WorkflowActionDef } from "@/lib/resources/types";
import { useAuth } from "@/providers/auth-provider";

export function WorkflowActionsBar({
  resource,
  id,
  status,
  actions,
  onCompleted,
}: {
  resource: string;
  id: string | number;
  status: string;
  actions: WorkflowActionDef[];
  onCompleted?: () => void;
}) {
  const { user } = useAuth();
  const mutation = useWorkflowAction(resource, id);
  const [pendingAction, setPendingAction] = useState<WorkflowActionDef | null>(null);

  const visibleActions = actions.filter(
    (action) => action.statuses.includes(status) && hasPermission(user, action.permission),
  );

  if (!visibleActions.length) {
    return null;
  }

  const runAction = async (action: WorkflowActionDef, payload: { reason?: string; notes?: string }) => {
    try {
      const body: Record<string, string> = {};

      if (payload.notes) {
        body.notes = payload.notes;
      }

      if (payload.reason) {
        body.reason = payload.reason;
      }

      await mutation.mutateAsync({
        action: action.action,
        body: Object.keys(body).length ? body : undefined,
      });
      toast.success(`${action.label} berhasil.`);
      onCompleted?.();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Aksi gagal.");
    }
  };

  return (
    <>
      <div className="flex flex-wrap gap-2">
        {visibleActions.map((action) => (
          <Button
            key={action.action}
            variant={action.variant ?? "default"}
            size="sm"
            disabled={mutation.isPending}
            onClick={() => {
              if (action.requiresReason || action.notesOptional || action.confirmTitle) {
                setPendingAction(action);
                return;
              }

              void runAction(action, {});
            }}
          >
            {action.label}
          </Button>
        ))}
      </div>

      {pendingAction ? (
        <ConfirmActionDialog
          open
          onOpenChange={(open) => {
            if (!open) {
              setPendingAction(null);
            }
          }}
          title={pendingAction.confirmTitle ?? pendingAction.label}
          description={pendingAction.confirmDescription}
          confirmLabel={pendingAction.label}
          destructive={pendingAction.variant === "destructive"}
          requiresReason={pendingAction.requiresReason}
          reasonLabel={pendingAction.reasonLabel}
          reasonRequired={pendingAction.reasonRequired}
          notesOptional={pendingAction.notesOptional}
          onConfirm={(payload) => runAction(pendingAction, payload)}
        />
      ) : null}
    </>
  );
}
