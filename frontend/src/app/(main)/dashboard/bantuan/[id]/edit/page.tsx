"use client";

import { CrudEditRoute } from "@/components/sima/crud/crud-routes";
import { grantApplicationResource } from "@/lib/resources";

export default function Page() {
  return <CrudEditRoute config={grantApplicationResource} />;
}
