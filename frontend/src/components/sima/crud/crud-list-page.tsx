"use client";

import { useMemo, useState } from "react";

import Link from "next/link";

import type { SortingState } from "@tanstack/react-table";
import { Plus, Search } from "lucide-react";

import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { PaginatedDataTable } from "@/components/sima/paginated-data-table";
import { TableSkeleton } from "@/components/sima/skeletons";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { NativeSelect, NativeSelectOption } from "@/components/ui/native-select";
import { useResourceQuery } from "@/hooks/use-resource-query";
import { hasPermission } from "@/lib/auth/permissions";
import type { FilterDef, ResourceDef } from "@/lib/resources/types";
import { useAuth } from "@/providers/auth-provider";

interface CrudListPageProps {
  config: ResourceDef;
  description?: string;
  emptyMessage?: string;
  initialFilters?: Record<string, string>;
  hideCreate?: boolean;
}

function FilterBar({
  filters,
  values,
  onChange,
}: {
  filters: FilterDef[];
  values: Record<string, string>;
  onChange: (name: string, value: string) => void;
}) {
  return (
    <div className="flex flex-wrap gap-3">
      {filters.map((filter) => {
        const selectId = `filter-${filter.name}`;

        return (
          <div key={filter.name} className="min-w-40 space-y-1">
            <label htmlFor={selectId} className="font-medium text-muted-foreground text-xs">
              {filter.label}
            </label>
            <NativeSelect
              id={selectId}
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
          </div>
        );
      })}
    </div>
  );
}

export function CrudListPage({
  config,
  description,
  emptyMessage,
  initialFilters = {},
  hideCreate = false,
}: CrudListPageProps) {
  const { user } = useAuth();
  const [pageIndex, setPageIndex] = useState(0);
  const [pageSize] = useState(15);
  const [q, setQ] = useState("");
  const [search, setSearch] = useState("");
  const [filterValues, setFilterValues] = useState<Record<string, string>>(initialFilters);
  const [sorting, setSorting] = useState<SortingState>(
    config.defaultSort ? [{ id: config.defaultSort.field, desc: config.defaultSort.direction === "desc" }] : [],
  );

  const params = useMemo(() => {
    const activeSort = sorting[0];

    return {
      page: pageIndex + 1,
      per_page: pageSize,
      q: search || undefined,
      sort: activeSort?.id,
      direction: activeSort ? (activeSort.desc ? ("desc" as const) : ("asc" as const)) : undefined,
      ...Object.fromEntries(Object.entries(filterValues).filter(([, value]) => value !== "")),
    };
  }, [pageIndex, pageSize, search, sorting, filterValues]);

  const { data, isLoading, isError, refetch } = useResourceQuery<Record<string, unknown>>(config.resource, params);

  const canCreate = !hideCreate && hasPermission(user, config.permissions.create ?? config.permissions.manage ?? "");

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={config.labelPlural}
        description={description ?? `Kelola data ${config.label.toLowerCase()}.`}
        actions={
          canCreate ? (
            <Button asChild>
              <Link href={`${config.basePath}/new`}>
                <Plus className="size-4" />
                Tambah {config.label}
              </Link>
            </Button>
          ) : null
        }
      />

      <Card>
        <CardHeader className="gap-4">
          <CardTitle>Daftar Data</CardTitle>
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <form
              className="max-w-md flex-1"
              onSubmit={(event) => {
                event.preventDefault();
                setSearch(q);
                setPageIndex(0);
              }}
            >
              <InputGroup>
                <InputGroupAddon>
                  <Search className="size-4" />
                </InputGroupAddon>
                <InputGroupInput value={q} onChange={(event) => setQ(event.target.value)} placeholder="Cari..." />
              </InputGroup>
            </form>
            <div className="flex flex-wrap items-end gap-3">
              {config.defaultSort ? (
                <div className="min-w-40 space-y-1">
                  <label htmlFor="crud-list-sort" className="font-medium text-muted-foreground text-xs">
                    Urutan
                  </label>
                  <NativeSelect
                    id="crud-list-sort"
                    value={
                      sorting[0]
                        ? `${sorting[0].id}:${sorting[0].desc ? "desc" : "asc"}`
                        : `${config.defaultSort.field}:${config.defaultSort.direction}`
                    }
                    onChange={(event) => {
                      const [field, direction] = event.target.value.split(":");
                      setSorting([{ id: field, desc: direction === "desc" }]);
                      setPageIndex(0);
                    }}
                  >
                    <NativeSelectOption value={`${config.defaultSort.field}:desc`}>Terbaru</NativeSelectOption>
                    <NativeSelectOption value={`${config.defaultSort.field}:asc`}>Terlama</NativeSelectOption>
                  </NativeSelect>
                </div>
              ) : null}
              {config.filters?.length ? (
                <FilterBar
                  filters={config.filters}
                  values={filterValues}
                  onChange={(name, value) => {
                    setFilterValues((current) => ({ ...current, [name]: value }));
                    setPageIndex(0);
                  }}
                />
              ) : null}
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {isError ? (
            <ErrorState onRetry={() => refetch()} />
          ) : isLoading ? (
            <TableSkeleton />
          ) : (
            <PaginatedDataTable
              columns={config.listColumns}
              data={data?.rows ?? []}
              pagination={data?.pagination}
              pageIndex={pageIndex}
              pageSize={pageSize}
              sorting={sorting}
              onSortingChange={(next) => {
                setSorting(next);
                setPageIndex(0);
              }}
              onPaginationChange={(pagination) => setPageIndex(pagination.pageIndex)}
              emptyMessage={emptyMessage ?? `Belum ada data ${config.label.toLowerCase()}.`}
            />
          )}
        </CardContent>
      </Card>
    </div>
  );
}
