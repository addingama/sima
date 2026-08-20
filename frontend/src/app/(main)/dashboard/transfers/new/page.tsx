"use client";

import { CrudCreateRoute } from "@/components/sima/crud/crud-routes";
import { accountTransferResource } from "@/lib/resources";

export default function Page() {
  return <CrudCreateRoute config={accountTransferResource} />;
}
