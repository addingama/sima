"use client";

import { CrudDetailRoute } from "@/components/sima/crud/crud-routes";
import { accountResource } from "@/lib/resources";

export default function Page() {
  return <CrudDetailRoute config={accountResource} />;
}
