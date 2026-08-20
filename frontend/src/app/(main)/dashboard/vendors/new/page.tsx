"use client";

import { CrudCreateRoute } from "@/components/sima/crud/crud-routes";
import { vendorResource } from "@/lib/resources";

export default function Page() {
  return <CrudCreateRoute config={vendorResource} />;
}
