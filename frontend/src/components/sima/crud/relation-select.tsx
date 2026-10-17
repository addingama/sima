"use client";

import { useMemo } from "react";

import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox";
import { NativeSelect, NativeSelectOption } from "@/components/ui/native-select";
import { Skeleton } from "@/components/ui/skeleton";
import { useResourceQuery } from "@/hooks/use-resource-query";

type RelationOption = {
  value: string;
  label: string;
};

function rowLabel(row: Record<string, unknown>, labelKey: string): string {
  const primary = row[labelKey];
  if (primary !== null && primary !== undefined && String(primary).trim() !== "") {
    return String(primary);
  }
  if (row.code !== null && row.code !== undefined) {
    return String(row.code);
  }
  return String(row.id ?? "");
}

export function RelationSelect({
  resource,
  labelKey,
  valueKey = "id",
  params,
  value,
  onChange,
  placeholder = "Pilih...",
  disabled,
  searchable = false,
  allowClear = false,
}: {
  resource: string;
  labelKey: string;
  valueKey?: string;
  params?: Record<string, string | number | boolean>;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  disabled?: boolean;
  /** Gunakan combobox dengan filter teks (cocok untuk daftar panjang). */
  searchable?: boolean;
  /** Tampilkan tombol clear (berguna untuk field opsional). */
  allowClear?: boolean;
}) {
  const queryParams = useMemo(
    () => ({
      per_page: 100,
      ...params,
    }),
    [params],
  );

  const { data, isLoading } = useResourceQuery<Record<string, unknown>>(resource, queryParams);

  const options = useMemo<RelationOption[]>(
    () =>
      (data?.rows ?? []).map((row) => ({
        value: String(row[valueKey]),
        label: rowLabel(row, labelKey),
      })),
    [data?.rows, labelKey, valueKey],
  );

  if (isLoading) {
    return <Skeleton className="h-9 w-full" />;
  }

  if (!searchable) {
    return (
      <NativeSelect value={value} onChange={(event) => onChange(event.target.value)} disabled={disabled}>
        <NativeSelectOption value="">{placeholder}</NativeSelectOption>
        {options.map((option) => (
          <NativeSelectOption key={option.value} value={option.value}>
            {option.label}
          </NativeSelectOption>
        ))}
      </NativeSelect>
    );
  }

  const selected = options.find((option) => option.value === value) ?? null;

  return (
    <Combobox
      items={options}
      value={selected}
      onValueChange={(item) => onChange(item?.value ?? "")}
      itemToStringLabel={(item) => item.label}
      disabled={disabled}
    >
      <ComboboxInput
        className="w-full"
        placeholder={placeholder}
        disabled={disabled}
        showClear={allowClear && Boolean(value)}
      />
      <ComboboxContent className="w-(--anchor-width)">
        <ComboboxEmpty>Tidak ditemukan</ComboboxEmpty>
        <ComboboxList>
          {(item) => (
            <ComboboxItem key={item.value} value={item}>
              {item.label}
            </ComboboxItem>
          )}
        </ComboboxList>
      </ComboboxContent>
    </Combobox>
  );
}
