import { formatIdr } from "@/lib/format/currency";
import { formatDate, formatDateTime } from "@/lib/format/datetime";

export const reportExportHelpers = {
  currency: (value: unknown) => formatIdr(value as string | number),
  date: (value: unknown) => formatDate(String(value ?? "")),
  datetime: (value: unknown) => formatDateTime(String(value ?? "")),
};
