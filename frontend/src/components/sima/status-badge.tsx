import { Badge } from "@/components/ui/badge";

const variants: Record<string, "default" | "secondary" | "destructive" | "outline"> = {
  draft: "secondary",
  submitted: "outline",
  verified: "outline",
  approved: "default",
  posted: "default",
  rejected: "destructive",
  reversed: "destructive",
  deferred: "outline",
  outstanding: "outline",
  settled: "default",
  void: "destructive",
  completed: "default",
  partially_settled: "outline",
};

export function StatusBadge({ status }: { status: string }) {
  return <Badge variant={variants[status] ?? "secondary"}>{status.replaceAll("_", " ")}</Badge>;
}
