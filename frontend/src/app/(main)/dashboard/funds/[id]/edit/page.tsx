"use client";

import { CrudEditRoute } from "@/components/sima/crud/crud-routes";
import { fundResource } from "@/lib/resources";

export default function Page() {
  return <CrudEditRoute config={fundResource} />;
}
