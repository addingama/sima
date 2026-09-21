"use client";

import { ErrorState } from "@/components/sima/error-state";

export default function RouteError({ reset }: { reset: () => void }) {
  return <ErrorState onRetry={reset} />;
}
