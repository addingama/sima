"use client";

import Link from "next/link";

import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";

export type GrantNameMatch = {
  id: number;
  application_number?: string | null;
  status?: string;
  status_label?: string | null;
  reason?: string | null;
  nik_relation?: "same" | "different" | "unknown" | null;
  viewable?: boolean;
};

export function nikRelationLabel(relation: GrantNameMatch["nik_relation"]): string {
  if (relation === "same") return "NIK sama";
  if (relation === "different") return "NIK berbeda";
  return "NIK belum lengkap";
}

export function nameMatchTooltip(matches: GrantNameMatch[]): string {
  return matches.map((match) => [match.application_number, match.reason].filter(Boolean).join(" · ")).join("\n");
}

export function GrantNameMatchBadge({
  warning,
  matches,
  completedCount,
}: {
  warning?: boolean;
  matches?: GrantNameMatch[];
  completedCount?: number;
}) {
  if (!warning) return null;

  const count = Number(completedCount ?? 0);

  return (
    <div className="mt-1 space-y-0.5">
      <Badge
        variant="outline"
        className="border-amber-300 bg-amber-50 text-amber-800"
        title={nameMatchTooltip(matches ?? [])}
      >
        Nama sama
      </Badge>
      {count > 0 ? <p className="text-muted-foreground text-xs">Sudah dibantu {count} kali</p> : null}
    </div>
  );
}

export function GrantNameMatchBanner({ row }: { row: Record<string, unknown> }) {
  if (!row.name_match_warning) return null;

  const matches = Array.isArray(row.name_matches) ? (row.name_matches as GrantNameMatch[]) : [];
  const count = Number(row.completed_assistance_count ?? 0);

  return (
    <Alert className="border-amber-300 bg-amber-50 text-amber-950">
      <AlertTitle>Nama penerima sama</AlertTitle>
      <AlertDescription>
        <p>
          Bantuan berulang diperbolehkan, baik tujuan sama maupun berbeda. Verifikator perlu memastikan orangnya dan
          tujuannya.
        </p>
        {count > 0 ? <p>Sudah dibantu {count} kali.</p> : null}
        {matches.length > 0 ? (
          <ul className="mt-2 space-y-1">
            {matches.map((match) => (
              <li key={match.id}>
                {match.viewable ? (
                  <Link href={`/dashboard/bantuan/${match.id}`} className="underline">
                    {match.application_number}
                  </Link>
                ) : (
                  <span>{match.application_number}</span>
                )}
                {" · "}
                {match.status_label}
                {" · "}
                {match.reason}
                {" · "}
                {nikRelationLabel(match.nik_relation)}
              </li>
            ))}
          </ul>
        ) : null}
      </AlertDescription>
    </Alert>
  );
}
