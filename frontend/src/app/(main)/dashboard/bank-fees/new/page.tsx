"use client";

import { CrudCreateRoute } from "@/components/sima/crud/crud-routes";
import { bankFeeResource } from "@/lib/resources";

export default function Page() {
  return <CrudCreateRoute config={bankFeeResource} />;
}
