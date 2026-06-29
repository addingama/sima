"use client";

import { useParams } from "next/navigation";

import { CrudDetailPage } from "@/components/sima/crud/crud-detail-page";
import { CrudFormPage } from "@/components/sima/crud/crud-form-page";
import type { ResourceDef } from "@/lib/resources/types";

export function CrudCreateRoute({ config }: { config: ResourceDef }) {
  return <CrudFormPage config={config} />;
}

export function CrudDetailRoute({ config }: { config: ResourceDef }) {
  const params = useParams<{ id: string }>();

  return <CrudDetailPage config={config} id={String(params.id)} />;
}

export function CrudEditRoute({ config }: { config: ResourceDef }) {
  const params = useParams<{ id: string }>();

  return <CrudFormPage config={config} id={String(params.id)} />;
}
