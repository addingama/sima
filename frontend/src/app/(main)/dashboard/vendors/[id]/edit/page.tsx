"use client";

import { CrudEditRoute } from "@/components/sima/crud/crud-routes";
import { vendorResource } from "@/lib/resources";

export default function Page() {
  return <CrudEditRoute config={vendorResource} />;
}
