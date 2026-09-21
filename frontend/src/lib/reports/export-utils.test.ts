import { afterEach, describe, expect, it, vi } from "vitest";

import { exportReportToExcel, exportReportToPdf, printReportElement } from "./export-utils";

const mocks = vi.hoisted(() => ({
  aoaToSheet: vi.fn((data: unknown) => ({ data })),
  bookNew: vi.fn(() => ({ sheets: [] })),
  appendSheet: vi.fn(),
  writeFile: vi.fn(),
  pdfText: vi.fn(),
  pdfSetFontSize: vi.fn(),
  pdfSave: vi.fn(),
  autoTable: vi.fn(),
}));

vi.mock("xlsx", () => ({
  utils: {
    aoa_to_sheet: mocks.aoaToSheet,
    book_new: mocks.bookNew,
    book_append_sheet: mocks.appendSheet,
  },
  writeFile: mocks.writeFile,
}));

vi.mock("jspdf", () => ({
  jsPDF: class {
    setFontSize = mocks.pdfSetFontSize;
    text = mocks.pdfText;
    save = mocks.pdfSave;
  },
}));

vi.mock("jspdf-autotable", () => ({ default: mocks.autoTable }));

const columns = [
  { header: "Nama", value: (row: Record<string, unknown>) => String(row.name) },
  { header: "Nominal", value: (row: Record<string, unknown>) => String(row.amount) },
];

describe("report export utilities", () => {
  afterEach(() => {
    vi.clearAllMocks();
    document.body.innerHTML = "";
  });

  it("membuat workbook dengan header, rows, nama sheet terpotong, dan filename", async () => {
    await exportReportToExcel("laporan-dana", "Judul Sheet Yang Panjang Sekali Melebihi Batas Excel", columns, [
      { name: "Dana Pendidikan", amount: 1000 },
    ]);

    expect(mocks.aoaToSheet).toHaveBeenCalledWith([
      ["Nama", "Nominal"],
      ["Dana Pendidikan", "1000"],
    ]);
    expect(mocks.appendSheet.mock.calls[0][2]).toHaveLength(31);
    expect(mocks.writeFile).toHaveBeenCalledWith(expect.anything(), "laporan-dana.xlsx");
  });

  it("membuat PDF portrait untuk sedikit kolom dan landscape untuk banyak kolom", async () => {
    await exportReportToPdf("ringkas", "Laporan Ringkas", columns, [{ name: "Dana", amount: 1000 }]);
    await exportReportToPdf(
      "lebar",
      "Laporan Lebar",
      Array.from({ length: 6 }, (_, index) => ({
        header: `Kolom ${index + 1}`,
        value: () => String(index + 1),
      })),
      [{}],
    );

    expect(mocks.pdfText).toHaveBeenCalledWith("Laporan Ringkas", 40, 40);
    expect(mocks.autoTable).toHaveBeenCalledWith(
      expect.anything(),
      expect.objectContaining({ head: [["Nama", "Nominal"]], body: [["Dana", "1000"]] }),
    );
    expect(mocks.pdfSave).toHaveBeenNthCalledWith(1, "ringkas.pdf");
    expect(mocks.pdfSave).toHaveBeenNthCalledWith(2, "lebar.pdf");
  });

  it("memakai print browser jika elemen tidak ditemukan", () => {
    const print = vi.spyOn(window, "print").mockImplementation(() => undefined);

    printReportElement("tidak-ada");

    expect(print).toHaveBeenCalledOnce();
  });

  it("memakai print browser jika popup diblokir", () => {
    document.body.innerHTML = '<div id="laporan">Isi</div>';
    vi.spyOn(window, "open").mockReturnValue(null);
    const print = vi.spyOn(window, "print").mockImplementation(() => undefined);

    printReportElement("laporan");

    expect(print).toHaveBeenCalledOnce();
  });

  it("menulis isi laporan dan mencetak melalui popup", () => {
    document.body.innerHTML = '<div id="laporan"><h1>Dana Amanah</h1></div>';
    const popup = {
      document: { write: vi.fn(), close: vi.fn() },
      focus: vi.fn(),
      print: vi.fn(),
      close: vi.fn(),
    };
    vi.spyOn(window, "open").mockReturnValue(popup as unknown as Window);

    printReportElement("laporan");

    expect(popup.document.write).toHaveBeenCalledWith(expect.stringContaining("<h1>Dana Amanah</h1>"));
    expect(popup.focus).toHaveBeenCalledOnce();
    expect(popup.print).toHaveBeenCalledOnce();
    expect(popup.close).toHaveBeenCalledOnce();
  });
});
