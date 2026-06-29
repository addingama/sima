"use client";

import { CrudCreateRoute } from "@/components/sima/crud/crud-routes";
import { accountResource } from "@/lib/resources";

export default function Page() {
  return <CrudCreateRoute config={accountResource} />;
}
