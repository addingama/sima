"use client";

import { CrudDetailRoute } from "@/components/sima/crud/crud-routes";
import { vendorResource } from "@/lib/resources";

export default function Page() {
  return <CrudDetailRoute config={vendorResource} />;
}
