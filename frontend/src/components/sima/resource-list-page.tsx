"use client";

import { useMemo, useState } from "react";

import type { ColumnDef } from "@tanstack/react-table";
import { Search } from "lucide-react";

import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { PaginatedDataTable } from "@/components/sima/paginated-data-table";
import { TableSkeleton } from "@/components/sima/skeletons";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { useResourceQuery } from "@/hooks/use-resource-query";

interface ResourceListPageProps<TData> {
  title: string;
  description: string;
  resource: string;
  columns: ColumnDef<TData, unknown>[];
  actions?: React.ReactNode;
  emptyMessage?: string;
}

export function ResourceListPage<TData>({
  title,
  description,
  resource,
  columns,
  actions,
  emptyMessage,
}: ResourceListPageProps<TData>) {
  const [pageIndex, setPageIndex] = useState(0);
  const [pageSize] = useState(15);
  const [q, setQ] = useState("");
  const [search, setSearch] = useState("");

  const params = useMemo(
    () => ({
      page: pageIndex + 1,
      per_page: pageSize,
      q: search || undefined,
    }),
    [pageIndex, pageSize, search],
  );

  const { data, isLoading, isError, refetch } = useResourceQuery<TData>(resource, params);

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={title} description={description} actions={actions} />

      <Card>
        <CardHeader className="gap-4">
          <CardTitle>Daftar Data</CardTitle>
          <form
            className="max-w-md"
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
        </CardHeader>
        <CardContent>
          {isError ? (
            <ErrorState onRetry={() => refetch()} />
          ) : isLoading ? (
            <TableSkeleton />
          ) : (
            <PaginatedDataTable
              columns={columns}
              data={data?.rows ?? []}
              pagination={data?.pagination}
              pageIndex={pageIndex}
              pageSize={pageSize}
              onPaginationChange={(pagination) => setPageIndex(pagination.pageIndex)}
              emptyMessage={emptyMessage}
            />
          )}
        </CardContent>
      </Card>
    </div>
  );
}
