"use client";

import { CheckCircle2, Clock3, XCircle } from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type { ApprovalRecord } from "@/lib/api/entities";
import { formatDateTime } from "@/lib/format/datetime";
import { cn } from "@/lib/utils";

const actionStyles: Record<string, string> = {
  submitted: "text-blue-600",
  verified: "text-indigo-600",
  approved: "text-green-600",
  rejected: "text-destructive",
  reversed: "text-destructive",
  posted: "text-green-600",
};

function ActionIcon({ action }: { action: string }) {
  if (action === "rejected" || action === "reversed") {
    return <XCircle className="size-4" />;
  }

  if (action === "submitted" || action === "verified") {
    return <Clock3 className="size-4" />;
  }

  return <CheckCircle2 className="size-4" />;
}

export function ApprovalTimeline({ approvals }: { approvals: ApprovalRecord[] }) {
  if (!approvals.length) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Timeline Persetujuan</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-muted-foreground text-sm">Belum ada riwayat persetujuan.</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Timeline Persetujuan</CardTitle>
      </CardHeader>
      <CardContent>
        <ol className="space-y-4">
          {approvals.map((item) => (
            <li key={item.id} className="flex gap-3">
              <div className={cn("mt-0.5", actionStyles[item.action] ?? "text-muted-foreground")}>
                <ActionIcon action={item.action} />
              </div>
              <div className="min-w-0 flex-1 space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="font-medium capitalize">{item.action.replaceAll("_", " ")}</span>
                  <span className="text-muted-foreground text-xs">{formatDateTime(item.acted_at)}</span>
                </div>
                <p className="text-muted-foreground text-sm">
                  {item.actor?.name ?? "Sistem"}
                  {item.actor_role ? ` · ${item.actor_role}` : ""}
                </p>
                {item.notes ? <p className="text-sm">{item.notes}</p> : null}
              </div>
            </li>
          ))}
        </ol>
      </CardContent>
    </Card>
  );
}
