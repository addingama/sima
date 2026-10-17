#!/usr/bin/env python3
"""Generate the professional SIMA product presentation.

  python3 -m venv .venv-pptx
  .venv-pptx/bin/pip install python-pptx
  .venv-pptx/bin/python docs/demo/generate_pptx.py
"""

from pathlib import Path

from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import PP_ALIGN
from pptx.oxml.ns import qn
from pptx.util import Inches, Pt
from lxml import etree

OUT = Path(__file__).resolve().parent / "SIMA-Presentasi-Demo.pptx"
VIDEO_DIR = Path(__file__).resolve().parent / "videos"

NAVY = RGBColor(0x0B, 0x1F, 0x3A)
NAVY_MID = RGBColor(0x14, 0x32, 0x54)
TEAL = RGBColor(0x0D, 0x7A, 0x6F)
TEAL_SOFT = RGBColor(0x14, 0x9A, 0x8C)
GOLD = RGBColor(0xC4, 0xA3, 0x5A)
SLATE = RGBColor(0x2D, 0x3A, 0x4A)
MUTED = RGBColor(0x64, 0x74, 0x8B)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
OFF_WHITE = RGBColor(0xF7, 0xF5, 0xF2)
LIGHT = RGBColor(0xEE, 0xF3, 0xF6)
CARD = RGBColor(0xFF, 0xFF, 0xFF)
LINE = RGBColor(0xD6, 0xDE, 0xE6)

W = Inches(13.333)
H = Inches(7.5)
TOTAL = 26


def set_run(run, size=18, bold=False, color=SLATE, name="Calibri"):
    run.font.size = Pt(size)
    run.font.bold = bold
    run.font.color.rgb = color
    run.font.name = name


def _set_shape_no_line(shape):
    shape.line.fill.background()


def add_rect(slide, left, top, width, height, color, rounded=False):
    kind = MSO_SHAPE.ROUNDED_RECTANGLE if rounded else MSO_SHAPE.RECTANGLE
    shape = slide.shapes.add_shape(kind, left, top, width, height)
    shape.fill.solid()
    shape.fill.fore_color.rgb = color
    _set_shape_no_line(shape)
    if rounded:
        shape.adjustments[0] = 0.08
    return shape


def send_to_back(slide, shape):
    sp_tree = slide.shapes._spTree
    sp = shape._element
    sp_tree.remove(sp)
    sp_tree.insert(2, sp)


def add_textbox(slide, left, top, width, height, text, size=18, bold=False, color=SLATE, align=PP_ALIGN.LEFT):
    box = slide.shapes.add_textbox(left, top, width, height)
    tf = box.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    p.alignment = align
    p.text = text
    set_run(p.runs[0], size=size, bold=bold, color=color)
    return box


def add_notes(slide, text):
    notes = slide.notes_slide.notes_text_frame
    notes.text = text


def add_bg(slide, color=OFF_WHITE):
    fill = add_rect(slide, 0, 0, W, H, color)
    send_to_back(slide, fill)


def add_header_bar(slide):
    add_rect(slide, 0, 0, W, Inches(0.08), TEAL)
    add_rect(slide, 0, Inches(7.42), W, Inches(0.08), NAVY)


def add_footer(slide, page: int):
    add_textbox(
        slide,
        Inches(0.6),
        Inches(7.08),
        Inches(9.2),
        Inches(0.28),
        "SIMA  ·  Sistem Informasi Manajemen Amanah  ·  Presentasi produk",
        size=11,
        color=MUTED,
    )
    add_textbox(
        slide,
        Inches(10.5),
        Inches(7.08),
        Inches(2.2),
        Inches(0.28),
        f"{page:02d}  /  {TOTAL:02d}",
        size=11,
        color=MUTED,
        align=PP_ALIGN.RIGHT,
    )


def content_title(slide, title, kicker=None):
    add_header_bar(slide)
    if kicker:
        add_textbox(slide, Inches(0.6), Inches(0.28), Inches(12), Inches(0.28), kicker.upper(), size=11, bold=True, color=TEAL)
        add_textbox(slide, Inches(0.6), Inches(0.52), Inches(12.1), Inches(0.55), title, size=28, bold=True, color=NAVY)
    else:
        add_textbox(slide, Inches(0.6), Inches(0.32), Inches(12.1), Inches(0.6), title, size=28, bold=True, color=NAVY)


def title_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide, NAVY)
    add_rect(slide, 0, 0, Inches(0.22), H, TEAL)
    add_rect(slide, 0, Inches(6.55), W, Inches(0.95), NAVY_MID)

    add_textbox(slide, Inches(0.85), Inches(1.55), Inches(11.5), Inches(0.35), "PRESENTASI PRODUK", size=13, bold=True, color=GOLD)
    add_textbox(slide, Inches(0.85), Inches(2.05), Inches(11.5), Inches(1.0), "SIMA", size=60, bold=True, color=WHITE)
    add_textbox(
        slide,
        Inches(0.85),
        Inches(3.15),
        Inches(11.2),
        Inches(0.5),
        "Sistem Informasi Manajemen Amanah",
        size=26,
        color=WHITE,
    )
    add_textbox(
        slide,
        Inches(0.85),
        Inches(3.85),
        Inches(10.8),
        Inches(1.3),
        "Mengelola dana titipan lembaga sosial dengan dua dimensi yang selalu seimbang:\n"
        "di mana uang berada, dan untuk apa uang boleh dipakai.",
        size=16,
        color=RGBColor(0xCB, 0xD5, 0xE1),
    )
    add_textbox(
        slide,
        Inches(0.85),
        Inches(6.78),
        Inches(11.5),
        Inches(0.4),
        "Untuk pimpinan  ·  bendahara  ·  auditor  ·  tim implementasi",
        size=14,
        color=WHITE,
    )
    add_notes(
        slide,
        "Buka dengan konteks lembaga: dana titipan, niat donatur, dan tanggung jawab ke publik. "
        "SIMA bukan sekadar aplikasi kas, melainkan sistem amanah.",
    )


def agenda_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Agenda", "Alur presentasi 20–25 menit")

    items = [
        ("01", "Masalah", "Dana titipan mudah tercampur dan sulit diaudit"),
        ("02", "Konsep inti", "Kas/Bank, Dana Amanah, dan Amanah Ledger"),
        ("03", "Kendali organisasi", "Peran, approval, dan jejak audit"),
        ("04", "Operasional", "Keuangan harian, bantuan, laporan, portal"),
        ("05", "Siap pakai", "Keamanan, go-live, dan tanya jawab"),
    ]
    for i, (num, title, desc) in enumerate(items):
        top = Inches(1.35 + i * 1.05)
        add_rect(slide, Inches(0.6), top, Inches(12.1), Inches(0.92), WHITE, rounded=True)
        add_rect(slide, Inches(0.6), top, Inches(0.12), Inches(0.92), TEAL)
        add_textbox(slide, Inches(1.0), top + Inches(0.18), Inches(1.0), Inches(0.55), num, size=22, bold=True, color=TEAL)
        add_textbox(slide, Inches(2.15), top + Inches(0.12), Inches(9.8), Inches(0.38), title, size=18, bold=True, color=NAVY)
        add_textbox(slide, Inches(2.15), top + Inches(0.48), Inches(9.8), Inches(0.32), desc, size=14, color=MUTED)

    add_footer(slide, 2)
    add_notes(slide, "Janji waktu. Jangan masuk detail teknis di awal kecuali audiens IT.")


def problem_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Masalah yang diselesaikan", "Mengapa Excel dan buku kas biasa tidak cukup")

    cards = [
        ("Tercampur", "Donasi zakat, yatim, dan dana umum masuk ke satu kas. Niat donatur mudah hilang."),
        ("Sulit dibuktikan", "Pengeluaran sulit ditunjukkan dari dana mana. Auditor dan donatur sulit dijawab."),
        ("Saldo tidak cocok", "Angka kas dan peruntukan sering drift. Koreksi dilakukan dengan edit atau hapus baris."),
        ("Tidak terdokumentasi", "Persetujuan ketua/verifikator ada di chat atau kertas, bukan di sistem."),
    ]
    for i, (title, body) in enumerate(cards):
        col = i % 2
        row = i // 2
        left = Inches(0.6 + col * 6.25)
        top = Inches(1.4 + row * 2.55)
        add_rect(slide, left, top, Inches(5.95), Inches(2.3), WHITE, rounded=True)
        add_rect(slide, left, top, Inches(5.95), Inches(0.1), TEAL if i % 2 == 0 else GOLD)
        add_textbox(slide, left + Inches(0.35), top + Inches(0.35), Inches(5.25), Inches(0.45), title, size=20, bold=True, color=NAVY)
        add_textbox(slide, left + Inches(0.35), top + Inches(0.95), Inches(5.25), Inches(1.05), body, size=15, color=SLATE)

    add_footer(slide, 3)
    add_notes(
        slide,
        "Tanyakan ke audiens: bagaimana lembaga menjawab donatur yang ingin tahu apakah zakatnya tersalur sesuai niat.",
    )


def promise_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide, NAVY)
    add_rect(slide, 0, 0, Inches(0.22), H, GOLD)
    add_textbox(slide, Inches(0.9), Inches(1.7), Inches(11.5), Inches(0.35), "JANJI PRODUK", size=13, bold=True, color=GOLD)
    add_textbox(
        slide,
        Inches(0.9),
        Inches(2.2),
        Inches(11.4),
        Inches(2.6),
        "Setiap uang masuk punya tujuan.\nSetiap uang keluar mengambil dari dana yang sesuai.\nSetiap perubahan meninggalkan jejak yang bisa diaudit.",
        size=28,
        bold=True,
        color=WHITE,
    )
    add_textbox(
        slide,
        Inches(0.9),
        Inches(5.3),
        Inches(11.2),
        Inches(0.8),
        "SIMA tidak hanya mencatat angka. SIMA menjaga aturan amanah.",
        size=16,
        color=RGBColor(0xCB, 0xD5, 0xE1),
    )
    add_notes(slide, "Ini slide yang harus diingat audiens. Berhenti sejenak.")


def two_dimension_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Dua dimensi uang", "Inti produk — satu uang selalu punya keduanya")

    add_rect(slide, Inches(0.6), Inches(1.4), Inches(5.85), Inches(5.15), WHITE, rounded=True)
    add_rect(slide, Inches(0.6), Inches(1.4), Inches(5.85), Inches(0.72), NAVY)
    add_textbox(slide, Inches(0.85), Inches(1.55), Inches(5.4), Inches(0.45), "Kas / Bank", size=20, bold=True, color=WHITE)
    for i, line in enumerate(
        [
            "Di mana uang berada secara fisik",
            "Kas kantor, rekening BCA, e-wallet lembaga",
            "Bisa dicocokkan dengan rekening koran",
            "Transfer antar rekening tidak mengubah peruntukan",
        ]
    ):
        add_textbox(slide, Inches(0.95), Inches(2.4 + i * 0.85), Inches(5.2), Inches(0.7), f"•  {line}", size=15, color=SLATE)

    add_rect(slide, Inches(6.85), Inches(1.4), Inches(5.85), Inches(5.15), WHITE, rounded=True)
    add_rect(slide, Inches(6.85), Inches(1.4), Inches(5.85), Inches(0.72), TEAL)
    add_textbox(slide, Inches(7.1), Inches(1.55), Inches(5.4), Inches(0.45), "Dana Amanah", size=20, bold=True, color=WHITE)
    for i, line in enumerate(
        [
            "Untuk apa uang boleh dipakai",
            "Restricted: terikat niat donatur",
            "Unrestricted: dana umum lembaga",
            "Pengeluaran dan biaya bank harus dari dana yang sah",
        ]
    ):
        add_textbox(slide, Inches(7.2), Inches(2.4 + i * 0.85), Inches(5.2), Inches(0.7), f"•  {line}", size=15, color=SLATE)

    add_footer(slide, 5)
    add_notes(
        slide,
        "Contoh: Rp10 juta di BCA bisa terdiri dari Rp6 juta zakat dan Rp4 juta dana umum. "
        "Lokasi sama, peruntukan berbeda.",
    )


def fund_type_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Jenis Dana Amanah", "Batasan penggunaan, bukan sekadar label")

    headers = ["Jenis", "Arti", "Contoh", "Biaya bank"]
    rows = [
        ["Restricted", "Terikat niat / peruntukan donatur", "Zakat, infaq yatim, program tertentu", "Tidak boleh"],
        ["Unrestricted", "Dana umum lembaga, lebih fleksibel", "Dana sosial umum, operasional", "Boleh"],
    ]
    _add_table(slide, headers, rows, Inches(0.6), Inches(1.45), Inches(12.1), Inches(2.0))

    add_rect(slide, Inches(0.6), Inches(3.85), Inches(12.1), Inches(2.55), WHITE, rounded=True)
    add_textbox(slide, Inches(0.95), Inches(4.1), Inches(11.4), Inches(0.4), "Aturan yang dijaga sistem", size=16, bold=True, color=TEAL)
    for i, line in enumerate(
        [
            "Dana baru yang dibuat bendahara default-nya restricted — lebih aman.",
            "Biaya administrasi bank hanya boleh dibebankan ke dana unrestricted, biasanya Dana Operasional.",
            "Event/Program boleh dipakai untuk pelacakan kegiatan, tetapi bukan pengganti Dana Amanah.",
        ]
    ):
        add_textbox(slide, Inches(0.95), Inches(4.6 + i * 0.5), Inches(11.4), Inches(0.45), f"•  {line}", size=15, color=SLATE)

    add_footer(slide, 6)


def ledger_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Amanah Ledger", "Satu buku besar immutable sebagai sumber kebenaran")

    points = [
        ("Double-entry", "Setiap transaksi menghasilkan pasangan jurnal: kas/bank dan Dana Amanah."),
        ("Saldo dihitung", "Tidak ada kolom saldo yang diedit manual. Saldo selalu agregasi dari ledger."),
        ("Tidak dihapus", "Koreksi hanya melalui void/reversal. Sejarah tetap utuh."),
        ("Harus seimbang", "Total kas/bank = total Dana Amanah. Selisih harus nol."),
    ]
    for i, (title, body) in enumerate(points):
        left = Inches(0.6 + (i % 4) * 3.1)
        add_rect(slide, left, Inches(1.4), Inches(2.95), Inches(3.55), WHITE, rounded=True)
        add_rect(slide, left, Inches(1.4), Inches(2.95), Inches(0.1), TEAL)
        add_textbox(slide, left + Inches(0.2), Inches(1.7), Inches(2.55), Inches(0.4), f"0{i + 1}", size=18, bold=True, color=TEAL)
        add_textbox(slide, left + Inches(0.2), Inches(2.25), Inches(2.55), Inches(0.8), title, size=18, bold=True, color=NAVY)
        add_textbox(slide, left + Inches(0.2), Inches(3.15), Inches(2.55), Inches(1.5), body, size=13, color=SLATE)

    add_textbox(
        slide,
        Inches(0.6),
        Inches(5.2),
        Inches(12.1),
        Inches(1.4),
        "Invariant operasional: saldo akun dan saldo dana tidak boleh negatif. "
        "Jika dashboard menampilkan “Seimbang”, buku besar dan peruntukan sedang konsisten.",
        size=15,
        color=SLATE,
    )
    add_footer(slide, 7)
    add_notes(slide, "Jangan jelaskan debit/credit teknis kecuali ditanya. Tekankan “tidak bisa dihapus diam-diam”.")


def money_flow_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Alur uang", "Apa yang terjadi setelah transaksi disetujui")

    headers = ["Transaksi", "Efek Kas / Bank", "Efek Dana Amanah"]
    rows = [
        ["Penerimaan disetujui", "Bertambah", "Bertambah di dana tujuan"],
        ["Pengeluaran disetujui", "Berkurang", "Berkurang dari sumber dana"],
        ["Biaya bank diposting", "Berkurang", "Berkurang dari dana operasional"],
        ["Transfer antar rekening", "Pindah antar akun", "Tidak berubah"],
        ["Reversal / void", "Negasi transaksi sumber", "Negasi transaksi sumber"],
    ]
    _add_table(slide, headers, rows, Inches(0.6), Inches(1.4), Inches(12.1), Inches(4.4))
    add_textbox(
        slide,
        Inches(0.6),
        Inches(6.05),
        Inches(12.1),
        Inches(0.7),
        "Draft dan submitted belum mengubah saldo. Hanya approval / posting yang masuk ledger.",
        size=14,
        color=MUTED,
    )
    add_footer(slide, 8)


def roles_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Siapa memakai sistem", "Pemisahan tugas yang jelas")

    headers = ["Peran", "Tanggung jawab"]
    rows = [
        ["Admin", "Instalasi, pengguna, saldo awal, izin penuh"],
        ["Asisten bendahara", "Input master dan transaksi harian; submit; tidak approve"],
        ["Bendahara", "Approver keuangan: setujui, tolak, dan reverse penerimaan/pengeluaran"],
        ["Verifikator", "Verifikasi kelengkapan pengeluaran dan data bantuan"],
        ["Petugas bantuan", "Rekomendasi lapangan, verifikasi data, serah terima bantuan"],
        ["Ketua", "Persetujuan pimpinan / eskalasi, termasuk pengajuan bantuan"],
        ["Auditor", "Laporan, audit trail, dan pengawasan tanpa mengubah transaksi"],
        ["Donatur", "Portal: melihat donasi approved miliknya sendiri"],
    ]
    _add_table(slide, headers, rows, Inches(0.6), Inches(1.3), Inches(12.1), Inches(5.35), font=12)
    add_footer(slide, 9)
    add_notes(
        slide,
        "Tekankan: asisten input, bendahara approve keuangan, ketua approve pimpinan. "
        "Jangan samakan bendahara dengan clerk.",
    )


def finance_flow_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Alur keuangan harian", "Tidak ada jalan pintas ke buku besar")

    blocks = [
        ("Penerimaan", ["Draft", "Submit", "Approve = posting", "Atau ditolak / di-reverse"]),
        ("Pengeluaran", ["Draft", "Submit", "Verifikasi", "Approve = posting"]),
        ("Kontrol", ["Saldo dana cukup", "Saldo kas cukup", "Sumber dana sesuai", "Nomor dokumen unik"]),
    ]
    for i, (title, items) in enumerate(blocks):
        left = Inches(0.6 + i * 4.15)
        add_rect(slide, left, Inches(1.4), Inches(3.95), Inches(5.15), WHITE, rounded=True)
        add_rect(slide, left, Inches(1.4), Inches(3.95), Inches(0.7), TEAL if i < 2 else NAVY)
        add_textbox(slide, left + Inches(0.25), Inches(1.52), Inches(3.45), Inches(0.48), title, size=18, bold=True, color=WHITE)
        for j, item in enumerate(items):
            add_textbox(slide, left + Inches(0.3), Inches(2.4 + j * 0.9), Inches(3.35), Inches(0.75), f"{j + 1}.  {item}", size=15, color=SLATE)

    add_footer(slide, 10)
    add_notes(slide, "Live demo: buat penerimaan, submit, approve, tunjukkan saldo berubah.")


def grant_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Pengajuan bantuan", "Kasus lapangan, terpisah dari jurnal, tetap tertib")

    steps = [
        ("Rekomendasi", "Petugas memasukkan data penerima dari rekomendasi orang, bukan portal pemohon."),
        ("Verifikasi", "Verifikator assigned melengkapi data, lampiran, dan rekening bila perlu."),
        ("Persetujuan", "Ketua memutuskan. Setelah disetujui, bendahara membuat satu pengeluaran."),
        ("Serah terima", "Selesai hanya jika pengeluaran approved dan ada bukti foto/transfer."),
    ]
    for i, (title, body) in enumerate(steps):
        top = Inches(1.35 + i * 1.3)
        add_rect(slide, Inches(0.6), top, Inches(12.1), Inches(1.15), WHITE, rounded=True)
        add_rect(slide, Inches(0.6), top, Inches(1.35), Inches(1.15), TEAL)
        add_textbox(slide, Inches(0.75), top + Inches(0.35), Inches(1.1), Inches(0.45), f"0{i + 1}", size=20, bold=True, color=WHITE)
        add_textbox(slide, Inches(2.2), top + Inches(0.16), Inches(10.1), Inches(0.35), title, size=17, bold=True, color=NAVY)
        add_textbox(slide, Inches(2.2), top + Inches(0.55), Inches(10.1), Inches(0.45), body, size=14, color=SLATE)

    add_footer(slide, 14)
    add_notes(
        slide,
        "Tekankan: modul ini tidak mem-posting ledger sendiri. Uang keluar hanya lewat Pengeluaran SIMA. "
        "Satu pengeluaran per penerima.",
    )


def add_embedded_video(slide, video, poster):
    if video.exists():
        slide.shapes.add_movie(
            str(video),
            Inches(1.4),
            Inches(1.28),
            Inches(10.53),
            Inches(5.55),
            poster_frame_image=str(poster) if poster.exists() else None,
            mime_type="video/mp4",
        )
        return

    add_rect(slide, Inches(1.4), Inches(1.55), Inches(10.53), Inches(5.1), WHITE, rounded=True)
    add_textbox(
        slide,
        Inches(1.8),
        Inches(3.2),
        Inches(9.7),
        Inches(1.6),
        "Video belum digenerate.\nJalankan: cd frontend && npm run demo:videos",
        size=18,
        color=SLATE,
        align=PP_ALIGN.CENTER,
    )


def demo_video_slide(prs, page, title, kicker, basename, notes):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, title, kicker)
    add_embedded_video(slide, VIDEO_DIR / f"{basename}.mp4", VIDEO_DIR / f"{basename}-poster.png")
    add_footer(slide, page)
    add_notes(slide, notes)


def reports_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Laporan yang dipakai organisasi", "Mudah disaring, mudah dijelaskan, bisa diekspor")

    cards = [
        ("Keuangan", "Saldo Dana Amanah, saldo kas/bank, buku kas, buku bank, ledger, per program, per donatur, per vendor."),
        ("Pengawasan", "Approval, saldo awal go-live, audit trail, rekonsiliasi global “Seimbang” di dashboard."),
        ("Bantuan", "Jumlah kartu, antrian, usulan, disetujui, siap diserahkan, sudah diserahkan, ditolak."),
        ("Portal donatur", "Donatur hanya melihat penerimaan approved miliknya. Bukan seluruh kas lembaga."),
    ]
    for i, (title, body) in enumerate(cards):
        col = i % 2
        row = i // 2
        left = Inches(0.6 + col * 6.25)
        top = Inches(1.4 + row * 2.55)
        add_rect(slide, left, top, Inches(5.95), Inches(2.3), WHITE, rounded=True)
        add_textbox(slide, left + Inches(0.35), top + Inches(0.3), Inches(5.25), Inches(0.45), title, size=20, bold=True, color=NAVY)
        add_textbox(slide, left + Inches(0.35), top + Inches(0.9), Inches(5.25), Inches(1.1), body, size=14, color=SLATE)

    add_footer(slide, 17)


def modules_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Modul yang siap dipakai", "Bukan prototipe — alur utama sudah end-to-end")

    groups = [
        ("Master", "Donatur, vendor, Dana Amanah, kas/bank, event/program, pengguna"),
        ("Keuangan", "Penerimaan, pengeluaran, biaya bank, transfer, rekonsiliasi, liabilitas"),
        ("Kendali", "Approval, saldo awal, reversal, audit trail, RBAC"),
        ("Layanan", "Kanban bantuan, laporan bantuan, portal donatur, API/Swagger"),
    ]
    for i, (title, body) in enumerate(groups):
        left = Inches(0.6 + i * 3.15)
        add_rect(slide, left, Inches(1.45), Inches(2.98), Inches(4.95), WHITE, rounded=True)
        add_rect(slide, left, Inches(1.45), Inches(2.98), Inches(0.12), GOLD if i % 2 else TEAL)
        add_textbox(slide, left + Inches(0.22), Inches(1.85), Inches(2.55), Inches(0.9), title, size=20, bold=True, color=NAVY)
        add_textbox(slide, left + Inches(0.22), Inches(2.85), Inches(2.55), Inches(3.2), body, size=14, color=SLATE)

    add_footer(slide, 19)


def security_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Keamanan dan kepatuhan", "Kontrol yang bisa ditunjukkan ke auditor")

    items = [
        "Hak akses granular: menu dan API mengikuti permission, bukan sekadar nama role.",
        "Ledger append-only: model dan trigger database menolak penghapusan jurnal.",
        "Transaksi approved tidak bisa diedit langsung; koreksi wajib lewat reversal beralasan.",
        "Nomor dokumen unik dan idempotency mencegah double-posting.",
        "Audit trail mencatat siapa melakukan apa dan kapan.",
        "Rekonsiliasi kas↔dana dan rekonsiliasi bank memberi bukti keseimbangan, bukan mengubah buku.",
    ]
    for i, item in enumerate(items):
        top = Inches(1.35 + i * 0.85)
        add_rect(slide, Inches(0.6), top, Inches(12.1), Inches(0.75), WHITE, rounded=True)
        add_textbox(slide, Inches(0.9), top + Inches(0.18), Inches(11.5), Inches(0.42), item, size=16, color=SLATE)

    add_footer(slide, 20)
    add_notes(slide, "Jika ditanya syariat: sistem membatasi secara teknis; keputusan niat tetap kebijakan lembaga.")


def architecture_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Fondasi teknis", "Cukup untuk pimpinan IT, tanpa jargon berlebih")

    cards = [
        ("Aplikasi", "Backend Laravel 11 + MySQL\nFrontend Next.js + TypeScript\nAuth Sanctum + RBAC Spatie"),
        ("Integritas", "Domain service, Form Request,\nPolicy, transaksi database,\noperasi uang dengan bcmath"),
        ("Operasi", "Docker production, backup,\nhealth check, OpenAPI/Swagger,\nkoleksi Postman"),
        ("Kualitas", "PHPUnit untuk API/domain\nVitest untuk komponen UI\nPlaywright untuk alur browser"),
    ]
    for i, (title, body) in enumerate(cards):
        left = Inches(0.6 + i * 3.15)
        add_rect(slide, left, Inches(1.5), Inches(2.98), Inches(4.85), WHITE, rounded=True)
        add_textbox(slide, left + Inches(0.22), Inches(1.8), Inches(2.55), Inches(0.7), title, size=18, bold=True, color=NAVY)
        add_textbox(slide, left + Inches(0.22), Inches(2.65), Inches(2.55), Inches(3.2), body, size=14, color=SLATE)

    add_footer(slide, 21)


def golive_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Jalan menuju go-live", "Waktu terbesar biasanya kesepakatan organisasi, bukan coding")

    phases = [
        ("0", "Sepakati dana, rekening, dan worksheet saldo awal"),
        ("1", "Instalasi, pengguna, dan role"),
        ("2", "Master Dana Amanah, kas/bank, donatur, vendor"),
        ("3", "Posting saldo awal oleh admin — cutover"),
        ("4", "Verifikasi laporan dan rekonsiliasi “Seimbang”"),
        ("5", "Transaksi harian, approval, dan pengajuan bantuan"),
    ]
    for i, (num, text) in enumerate(phases):
        col = i % 3
        row = i // 3
        left = Inches(0.6 + col * 4.15)
        top = Inches(1.45 + row * 2.55)
        add_rect(slide, left, top, Inches(3.95), Inches(2.3), WHITE, rounded=True)
        add_textbox(slide, left + Inches(0.3), top + Inches(0.3), Inches(3.35), Inches(0.5), f"Fase {num}", size=16, bold=True, color=TEAL)
        add_textbox(slide, left + Inches(0.3), top + Inches(0.95), Inches(3.35), Inches(1.0), text, size=16, color=SLATE)

    add_footer(slide, 22)
    add_notes(slide, "Rujuk docs/PANDUAN-MULAI.md. Admin produksi: php artisan sima:create-admin.")


def demo_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Urutan demo yang disarankan", "15 menit di layar, sisanya tanya jawab")

    steps = [
        "Login bendahara — dashboard metric dan rekonsiliasi global",
        "Tunjukkan Dana Amanah restricted vs unrestricted",
        "Penerimaan + alokasi → submit → approve → saldo naik",
        "Pengeluaran dari dana yang tepat → verifikasi → approve → saldo turun",
        "Kanban bantuan: rekomendasi → verifikasi → approval → serah terima",
        "Laporan bantuan dan portal donatur",
    ]
    for i, step in enumerate(steps):
        top = Inches(1.35 + i * 0.85)
        add_rect(slide, Inches(0.6), top, Inches(12.1), Inches(0.75), WHITE, rounded=True)
        add_rect(slide, Inches(0.6), top, Inches(0.85), Inches(0.75), TEAL)
        add_textbox(slide, Inches(0.72), top + Inches(0.18), Inches(0.6), Inches(0.4), f"{i + 1}", size=18, bold=True, color=WHITE)
        add_textbox(slide, Inches(1.7), top + Inches(0.18), Inches(10.6), Inches(0.42), step, size=16, color=SLATE)

    add_footer(slide, 23)
    add_notes(
        slide,
        "Akun demo: *@sima.test / password. Data: php artisan sima:seed-demo. "
        "Jangan pakai akun demo di production. Video alur ada di slide 11–13, 15–16, dan 18.",
    )


def hard_questions_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    content_title(slide, "Jawaban untuk pertanyaan sulit", "Siap diucapkan di depan pimpinan")

    pairs = [
        (
            "Apa bedanya dengan Excel?",
            "Excel mencatat angka. SIMA menjaga aturan: dana tidak keluar dari peruntukan yang salah, dan sejarah tidak bisa dihapus diam-diam.",
        ),
        (
            "Apakah ini menjamin syariat 100%?",
            "Sistem membatasi secara teknis. Keputusan niat dan program tetap kebijakan lembaga — SIMA membuat kebijakan itu bisa diaudit.",
        ),
        (
            "Kalau bendahara salah input?",
            "Sebelum approve bisa dikoreksi atau ditolak. Sesudah approve, pakai reverse beralasan. Jejak tetap ada.",
        ),
        (
            "Berapa lama implementasi?",
            "Setelah master dan saldo awal siap, transaksi harian bisa langsung. Waktu terbesar: sepakati daftar dana dan worksheet opening.",
        ),
    ]
    for i, (q, a) in enumerate(pairs):
        col = i % 2
        row = i // 2
        left = Inches(0.6 + col * 6.25)
        top = Inches(1.35 + row * 2.6)
        add_rect(slide, left, top, Inches(5.95), Inches(2.4), WHITE, rounded=True)
        add_textbox(slide, left + Inches(0.3), top + Inches(0.25), Inches(5.35), Inches(0.7), q, size=16, bold=True, color=NAVY)
        add_textbox(slide, left + Inches(0.3), top + Inches(1.0), Inches(5.35), Inches(1.15), a, size=13, color=SLATE)

    add_footer(slide, 24)


def summary_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide, NAVY)
    add_rect(slide, 0, 0, Inches(0.22), H, TEAL)
    add_textbox(slide, Inches(0.9), Inches(1.5), Inches(11.5), Inches(0.35), "RINGKASAN", size=13, bold=True, color=GOLD)
    add_textbox(
        slide,
        Inches(0.9),
        Inches(2.05),
        Inches(11.3),
        Inches(3.4),
        "SIMA memastikan dana titipan tetap amanah:\nuang punya tempat, uang punya tujuan,\nsemua perubahan lewat ledger yang bisa diaudit,\ndan koreksi tidak pernah dengan menghapus sejarah.",
        size=26,
        bold=True,
        color=WHITE,
    )
    add_notes(slide, "Tutup konsep sebelum Q&A. Jangan tambah fitur baru di slide ini.")


def closing_slide(prs):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    add_bg(slide)
    add_header_bar(slide)
    add_textbox(slide, Inches(0.7), Inches(2.15), Inches(12), Inches(0.9), "Terima kasih", size=40, bold=True, color=NAVY)
    add_textbox(
        slide,
        Inches(0.7),
        Inches(3.2),
        Inches(11.8),
        Inches(1.4),
        "Siap tanya jawab.\nPanduan go-live: docs/PANDUAN-MULAI.md  ·  FAQ presenter: docs/demo/FAQ-DEMO.md",
        size=18,
        color=SLATE,
    )
    add_rect(slide, Inches(0.7), Inches(5.0), Inches(11.9), Inches(1.35), WHITE, rounded=True)
    add_textbox(
        slide,
        Inches(0.95),
        Inches(5.25),
        Inches(11.4),
        Inches(0.9),
        "Langkah berikutnya yang kami sarankan: rapat Fase 0 — tetapkan daftar Dana Amanah, rekening kas/bank, dan worksheet saldo awal.",
        size=16,
        color=SLATE,
    )
    add_footer(slide, 26)
    add_notes(slide, "Tawarkan jadwal workshop Fase 0 jika audiens pimpinan siap.")


def _set_cell_border(cell, color_hex="D6DEE6"):
    tc = cell._tc
    tcPr = tc.get_or_add_tcPr()
    for border_name in ("lnL", "lnR", "lnT", "lnB"):
        ln = etree.SubElement(tcPr, qn(f"a:{border_name}"), w="6350", cap="flat", cmpd="sng", algn="ctr")
        sf = etree.SubElement(ln, qn("a:solidFill"))
        etree.SubElement(sf, qn("a:srgbClr"), val=color_hex)
        etree.SubElement(ln, qn("a:prstDash"), val="solid")


def _add_table(slide, headers, rows, left, top, width, height, font=13):
    table_shape = slide.shapes.add_table(len(rows) + 1, len(headers), left, top, width, height)
    table = table_shape.table
    for i in range(len(headers)):
        table.columns[i].width = int(width / len(headers))

    for i, header in enumerate(headers):
        cell = table.cell(0, i)
        cell.text = header
        cell.fill.solid()
        cell.fill.fore_color.rgb = NAVY
        for para in cell.text_frame.paragraphs:
            para.alignment = PP_ALIGN.LEFT
            for run in para.runs:
                set_run(run, size=font, bold=True, color=WHITE)
        _set_cell_border(cell, "0B1F3A")

    for r, row in enumerate(rows):
        for c, value in enumerate(row):
            cell = table.cell(r + 1, c)
            cell.text = value
            cell.fill.solid()
            cell.fill.fore_color.rgb = WHITE if r % 2 == 0 else LIGHT
            for para in cell.text_frame.paragraphs:
                for run in para.runs:
                    set_run(run, size=font, color=SLATE)
            _set_cell_border(cell)


def build():
    prs = Presentation()
    prs.slide_width = W
    prs.slide_height = H

    title_slide(prs)
    agenda_slide(prs)
    problem_slide(prs)
    promise_slide(prs)
    two_dimension_slide(prs)
    fund_type_slide(prs)
    ledger_slide(prs)
    money_flow_slide(prs)
    roles_slide(prs)
    finance_flow_slide(prs)
    demo_video_slide(
        prs,
        11,
        "Demo: dashboard dan Dana Amanah",
        "Rekaman Playwright — rekonsiliasi Seimbang, restricted vs unrestricted",
        "dashboard-dana",
        "Buka dengan bendahara. Tunjukkan saldo kas, saldo dana, dan badge Seimbang. "
        "Lalu bandingkan Zakat (restricted) dengan Dana Sosial Umum (unrestricted).",
    )
    demo_video_slide(
        prs,
        12,
        "Demo: penerimaan sampai posting",
        "Asisten input + alokasi niat → ketua approve = jurnal",
        "penerimaan",
        "Tekankan: uang masuk wajib dialokasi. Approve baru mem-posting ledger. "
        "Contoh: donasi Budi, 10% operasional, sisanya Dana Sosial Umum.",
    )
    demo_video_slide(
        prs,
        13,
        "Demo: pengeluaran sampai posting",
        "Asisten draft → verifikator → bendahara approve, saldo turun, buku tetap seimbang",
        "pengeluaran",
        "Uang keluar harus dari dana yang sesuai. Verifikasi sebelum approve keuangan. "
        "Setelah posting, rekonsiliasi tetap Seimbang.",
    )
    grant_slide(prs)
    demo_video_slide(
        prs,
        15,
        "Demo: bantuan perorangan",
        "Rekaman Playwright — rekomendasi sampai selesai",
        "pengajuan-bantuan",
        "Contoh Siti Aminah, santunan sakit tunai. "
        "Petugas → verifikasi → ketua approve → bendahara pengeluaran → foto handover.",
    )
    demo_video_slide(
        prs,
        16,
        "Demo: bantuan instansi / kelompok",
        "Contoh air bersih desa — kolektif sampai realisasi orang",
        "pengajuan-bantuan-instansi",
        "Kelompok Air Bersih Desa Cibadak, target 120 jiwa, PIC Kepala Desa, "
        "satu pengeluaran, realisasi 118 orang. Bukan portal pemohon.",
    )
    reports_slide(prs)
    demo_video_slide(
        prs,
        18,
        "Demo: portal donatur",
        "Donatur hanya melihat penerimaan approved miliknya",
        "portal-donatur",
        "Login donatur@sima.test. Tunjukkan total donasi Budi Santoso dan riwayat RCP. "
        "Bukan seluruh kas lembaga.",
    )
    modules_slide(prs)
    security_slide(prs)
    architecture_slide(prs)
    golive_slide(prs)
    demo_slide(prs)
    hard_questions_slide(prs)
    summary_slide(prs)
    closing_slide(prs)

    assert len(prs.slides) == TOTAL, f"expected {TOTAL} slides, got {len(prs.slides)}"
    OUT.parent.mkdir(parents=True, exist_ok=True)
    prs.save(OUT)
    print(f"Wrote {OUT} ({TOTAL} slides)")


if __name__ == "__main__":
    build()
