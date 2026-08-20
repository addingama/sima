"use client";

import { CrudDetailRoute } from "@/components/sima/crud/crud-routes";
import { userResource } from "@/lib/resources";

import { ResetPasswordPanel } from "../_components/reset-password-panel";

export default function Page() {
  return (
    <div className="flex flex-col gap-6">
      <CrudDetailRoute config={userResource} />
      <ResetPasswordPanel />
    </div>
  );
}
