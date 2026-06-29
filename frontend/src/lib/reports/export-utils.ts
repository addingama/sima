import type { ExportColumn } from "./types";

function buildSheetRows(columns: ExportColumn[], rows: Array<Record<string, unknown>>) {
  return rows.map((row) => columns.map((column) => column.value(row)));
}

export async function exportReportToExcel(
  filename: string,
  title: string,
  columns: ExportColumn[],
  rows: Array<Record<string, unknown>>,
) {
  const XLSX = await import("xlsx");
  const sheetData = [columns.map((column) => column.header), ...buildSheetRows(columns, rows)];
  const worksheet = XLSX.utils.aoa_to_sheet(sheetData);
  const workbook = XLSX.utils.book_new();

  XLSX.utils.book_append_sheet(workbook, worksheet, title.slice(0, 31));
  XLSX.writeFile(workbook, `${filename}.xlsx`);
}

export async function exportReportToPdf(
  filename: string,
  title: string,
  columns: ExportColumn[],
  rows: Array<Record<string, unknown>>,
) {
  const [{ jsPDF }, { default: autoTable }] = await Promise.all([
    import("jspdf"),
    import("jspdf-autotable"),
  ]);

  const doc = new jsPDF({ orientation: columns.length > 5 ? "landscape" : "portrait", unit: "pt" });
  const generatedAt = new Intl.DateTimeFormat("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date());

  doc.setFontSize(14);
  doc.text(title, 40, 40);
  doc.setFontSize(9);
  doc.text(`Dicetak: ${generatedAt}`, 40, 58);

  autoTable(doc, {
    startY: 72,
    head: [columns.map((column) => column.header)],
    body: buildSheetRows(columns, rows),
    styles: { fontSize: 8, cellPadding: 4 },
    headStyles: { fillColor: [30, 41, 59] },
  });

  doc.save(`${filename}.pdf`);
}

export function printReportElement(elementId: string) {
  const element = document.getElementById(elementId);

  if (!element) {
    window.print();
    return;
  }

  const printWindow = window.open("", "_blank", "noopener,noreferrer,width=1024,height=768");

  if (!printWindow) {
    window.print();
    return;
  }

  printWindow.document.write(`
    <!doctype html>
    <html>
      <head>
        <title>Cetak Laporan</title>
        <style>
          body { font-family: system-ui, sans-serif; padding: 24px; color: #111; }
          table { width: 100%; border-collapse: collapse; font-size: 12px; }
          th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
          th { background: #f4f4f5; }
          h1 { font-size: 18px; margin-bottom: 4px; }
          p { color: #666; font-size: 12px; margin-top: 0; }
        </style>
      </head>
      <body>${element.innerHTML}</body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
  printWindow.close();
}
