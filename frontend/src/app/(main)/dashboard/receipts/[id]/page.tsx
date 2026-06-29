"use client";

import { CrudDetailRoute } from "@/components/sima/crud/crud-routes";
import { receiptResource } from "@/lib/resources";

export default function Page() {
  return <CrudDetailRoute config={receiptResource} />;
}
