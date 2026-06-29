"use client";

import { CrudEditRoute } from "@/components/sima/crud/crud-routes";
import { accountResource } from "@/lib/resources";

export default function Page() {
  return <CrudEditRoute config={accountResource} />;
}
