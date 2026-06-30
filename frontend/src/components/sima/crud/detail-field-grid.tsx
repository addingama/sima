import type { ReactNode } from "react";

import { CurrencyDisplay } from "@/components/sima/currency-display";
import { StatusBadge } from "@/components/sima/status-badge";
import { formatDate, formatDateTime } from "@/lib/format/datetime";
import type { DetailFieldDef } from "@/lib/resources/types";

function renderValue(value: unknown, type?: DetailFieldDef["type"]): ReactNode {
  if (value === null || value === undefined || value === "") {
    return "-";
  }

  switch (type) {
    case "currency":
      return <CurrencyDisplay value={value as string | number} />;
    case "date":
      return formatDate(String(value));
    case "datetime":
      return formatDateTime(String(value));
    case "boolean":
      return value ? "Ya" : "Tidak";
    case "status":
      return <StatusBadge status={String(value)} />;
    default:
      return String(value);
  }
}

export function DetailFieldGrid({ fields, data }: { fields: DetailFieldDef[]; data: Record<string, unknown> }) {
  return (
    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      {fields.map((field) => {
        const value = typeof field.accessor === "function" ? field.accessor(data) : data[field.accessor as string];

        return (
          <div key={field.label} className="space-y-1">
            <dt className="font-medium text-muted-foreground text-sm">{field.label}</dt>
            <dd className="text-sm">{renderValue(value, field.type)}</dd>
          </div>
        );
      })}
    </dl>
  );
}
