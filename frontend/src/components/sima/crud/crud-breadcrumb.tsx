import Link from "next/link";
import { Fragment } from "react";

import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";

export function CrudBreadcrumb({
  items,
}: {
  items: Array<{ label: string; href?: string }>;
}) {
  return (
    <Breadcrumb>
      <BreadcrumbList>
        {items.map((item, index) => (
          <Fragment key={`${item.label}-${index}`}>
            {index > 0 ? <BreadcrumbSeparator /> : null}
            <BreadcrumbItem>
              {item.href ? (
                <BreadcrumbLink asChild>
                  <Link href={item.href}>{item.label}</Link>
                </BreadcrumbLink>
              ) : (
                <BreadcrumbPage>{item.label}</BreadcrumbPage>
              )}
            </BreadcrumbItem>
          </Fragment>
        ))}
      </BreadcrumbList>
    </Breadcrumb>
  );
}

function resolveTitle(configTitle: string | ((row: Record<string, unknown>) => string), row?: Record<string, unknown>) {
  if (!row) {
    return "";
  }

  return typeof configTitle === "function" ? configTitle(row) : String(row[configTitle] ?? "");
}

export function buildCrudBreadcrumbs(
  config: { labelPlural: string; basePath: string; label: string; titleField: string | ((row: Record<string, unknown>) => string) },
  mode: "list" | "create" | "detail" | "edit",
  row?: Record<string, unknown>,
) {
  const items: Array<{ label: string; href?: string }> = [{ label: config.labelPlural, href: config.basePath }];

  if (mode === "create") {
    items.push({ label: `Tambah ${config.label}` });
  }

  if ((mode === "detail" || mode === "edit") && row) {
    items.push({
      label: resolveTitle(config.titleField, row),
      href: mode === "edit" ? `${config.basePath}/${row.id}` : undefined,
    });
  }

  if (mode === "edit") {
    items.push({ label: "Edit" });
  }

  return items;
}
