"use client";

import { useMemo } from "react";

import { NativeSelect, NativeSelectOption } from "@/components/ui/native-select";
import { Skeleton } from "@/components/ui/skeleton";
import { useResourceQuery } from "@/hooks/use-resource-query";

export function RelationSelect({
  resource,
  labelKey,
  valueKey = "id",
  params,
  value,
  onChange,
  placeholder = "Pilih...",
  disabled,
}: {
  resource: string;
  labelKey: string;
  valueKey?: string;
  params?: Record<string, string | number | boolean>;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  disabled?: boolean;
}) {
  const queryParams = useMemo(
    () => ({
      per_page: 100,
      ...params,
    }),
    [params],
  );

  const { data, isLoading } = useResourceQuery<Record<string, unknown>>(resource, queryParams);

  if (isLoading) {
    return <Skeleton className="h-9 w-full" />;
  }

  return (
    <NativeSelect value={value} onChange={(event) => onChange(event.target.value)} disabled={disabled}>
      <NativeSelectOption value="">{placeholder}</NativeSelectOption>
      {(data?.rows ?? []).map((row) => (
        <NativeSelectOption key={String(row[valueKey])} value={String(row[valueKey])}>
          {String(row[labelKey] ?? row.code ?? row.id)}
        </NativeSelectOption>
      ))}
    </NativeSelect>
  );
}
