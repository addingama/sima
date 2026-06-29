"use client";

import { CrudEditRoute } from "@/components/sima/crud/crud-routes";
import { donorResource } from "@/lib/resources";

export default function Page() {
  return <CrudEditRoute config={donorResource} />;
}
