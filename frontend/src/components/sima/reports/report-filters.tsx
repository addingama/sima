"use client";

import { useMemo } from "react";

import { RelationSelect } from "@/components/sima/crud/relation-select";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { NativeSelect, NativeSelectOption } from "@/components/ui/native-select";
import type { ReportFilterDef } from "@/lib/reports/types";

export function ReportFiltersBar({
  filters,
  values,
  onChange,
}: {
  filters: ReportFilterDef[];
  values: Record<string, string>;
  onChange: (name: string, value: string) => void;
}) {
  if (!filters.length) {
    return null;
  }

  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
      {filters.map((filter) => (
        <div key={filter.name} className="space-y-1.5">
          <Label htmlFor={`report-filter-${filter.name}`}>{filter.label}</Label>
          {filter.type === "select" ? (
            <NativeSelect
              id={`report-filter-${filter.name}`}
              value={values[filter.name] ?? ""}
              onChange={(event) => onChange(filter.name, event.target.value)}
            >
              <NativeSelectOption value="">{filter.allLabel ?? "Semua"}</NativeSelectOption>
              {filter.options?.map((option) => (
                <NativeSelectOption key={option.value} value={option.value}>
                  {option.label}
                </NativeSelectOption>
              ))}
            </NativeSelect>
          ) : filter.type === "relation" && filter.relation ? (
            <RelationSelect
              resource={filter.relation.resource}
              labelKey={filter.relation.labelKey}
              params={filter.relation.params}
              value={values[filter.name] ?? ""}
              onChange={(value) => onChange(filter.name, value)}
              placeholder={filter.placeholder ?? "Pilih..."}
            />
          ) : (
            <Input
              id={`report-filter-${filter.name}`}
              type={filter.type === "date" ? "date" : "text"}
              value={values[filter.name] ?? ""}
              onChange={(event) => onChange(filter.name, event.target.value)}
              placeholder={filter.placeholder}
            />
          )}
        </div>
      ))}
    </div>
  );
}

export function useReportFilterDefaults(filters?: ReportFilterDef[]) {
  return useMemo(() => {
    const defaults: Record<string, string> = {};

    for (const filter of filters ?? []) {
      defaults[filter.name] = "";
    }

    return defaults;
  }, [filters]);
}
