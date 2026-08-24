"use client";

import { CrudCreateRoute } from "@/components/sima/crud/crud-routes";
import { fundTransferResource } from "@/lib/resources";

export default function Page() {
  return <CrudCreateRoute config={fundTransferResource} />;
}
