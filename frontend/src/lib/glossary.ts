export type GlossaryTerm = {
  id: string;
  name: string;
  description: string;
  aliases?: string[];
};

export type GlossaryGroup = {
  id: string;
  title: string;
  terms: GlossaryTerm[];
};

const glossaryGroupDefinitions: GlossaryGroup[] = [
  {
    id: "konsep-inti",
    title: "Konsep inti",
    terms: [
      {
        id: "amanah-ledger",
        name: "Amanah Ledger",
        aliases: ["Buku besar", "Ledger"],
        description:
          "Buku besar yang menjadi sumber kebenaran saldo. Setiap uang masuk dan uang keluar menghasilkan baris jurnal. Transaksi yang sudah disetujui tetap tersimpan.",
      },
      {
        id: "dana-amanah",
        name: "Dana Amanah",
        aliases: ["Dana"],
        description:
          "Pembatas penggunaan uang: untuk apa uang boleh dipakai, misalnya zakat atau operasional. Dana Amanah bukan rekening fisik.",
      },
      {
        id: "debit",
        name: "Debit",
        description:
          "Pada Kas/Bank, debit mencatat uang masuk. Pada Dana Amanah, debit mencatat pemakaian dana. Setiap jurnal memasangkan debit dan kredit dengan jumlah yang sama.",
      },
      {
        id: "kas-bank",
        name: "Kas/Bank",
        aliases: ["Akun", "Rekening"],
        description:
          "Tempat fisik uang berada, misalnya kas kantor atau rekening bank. Satu uang selalu tercatat di Kas/Bank dan di Dana Amanah sekaligus.",
      },
      {
        id: "kredit",
        name: "Kredit",
        description:
          "Pada Kas/Bank, kredit mencatat uang keluar. Pada Dana Amanah, kredit mencatat dana yang bertambah. Jumlah kredit pada satu jurnal selalu sama dengan jumlah debit.",
      },
      {
        id: "rekonsiliasi",
        name: "Rekonsiliasi",
        aliases: ["Rekonsiliasi bank", "Seimbang"],
        description:
          "Pencocokan mutasi rekening koran dengan catatan Kas/Bank di SIMA. Menyelesaikan rekonsiliasi tidak mengubah buku besar. Tanda Seimbang di dashboard berarti total Kas/Bank sama dengan total Dana Amanah.",
      },
      {
        id: "restricted",
        name: "Restricted",
        aliases: ["Terikat"],
        description:
          "Dana yang terikat pada niat atau peruntukan tertentu. Pemakaian mengikuti tujuan itu. Biaya bank tidak boleh mengambil dari dana restricted.",
      },
      {
        id: "saldo",
        name: "Saldo",
        description:
          "Hasil hitungan jurnal, bukan angka yang diketik di form master. Total saldo Kas/Bank harus sama dengan total saldo Dana Amanah.",
      },
      {
        id: "unrestricted",
        name: "Unrestricted",
        aliases: ["Umum"],
        description:
          "Dana umum lembaga, tanpa ikatan pada satu niat donatur. Dana ini boleh menanggung beban operasional, termasuk biaya bank.",
      },
    ],
  },
  {
    id: "dana-sistem",
    title: "Dana sistem",
    terms: [
      {
        id: "dana-belum-dialokasikan",
        name: "Dana Belum Dialokasikan",
        aliases: ["Suspense"],
        description:
          "Dana sistem untuk penerimaan yang belum dibagi ke dana tujuan. Pada alur yang aktif, pembagian dicatat langsung di penerimaan dan masuk buku besar saat penerimaan disetujui.",
      },
      {
        id: "dana-biaya-administrasi-bank",
        name: "Dana Biaya Administrasi Bank",
        aliases: ["Dana biaya bank"],
        description: "Dana sistem opsional untuk beban administrasi bank. Dana sistem tidak dihapus dari master data.",
      },
      {
        id: "dana-operasional",
        name: "Dana Operasional",
        description:
          "Dana sistem untuk beban internal lembaga. Dipakai sebagai penanggung biaya bank bila dana tidak dipilih. Tipenya unrestricted.",
      },
      {
        id: "saldo-awal",
        name: "Saldo Awal",
        aliases: ["Opening balance"],
        description:
          "Saldo kas dan dana pada hari mulai memakai SIMA. Dicatat lewat menu Saldo Awal. Setelah itu saldo dihitung dari jurnal.",
      },
    ],
  },
  {
    id: "transaksi",
    title: "Transaksi",
    terms: [
      {
        id: "alokasi",
        name: "Alokasi",
        description:
          "Pembagian nominal penerimaan ke satu atau lebih Dana Amanah tujuan. Alokasi disimpan bersama penerimaan dan masuk buku besar saat penerimaan disetujui.",
      },
      {
        id: "approval",
        name: "Approval",
        aliases: ["Persetujuan"],
        description:
          "Keputusan menyetujui transaksi. Pada penerimaan dan pengeluaran, approval memposting jurnal dan mengubah saldo.",
      },
      {
        id: "biaya-bank",
        name: "Biaya Bank",
        aliases: ["Biaya administrasi bank"],
        description:
          "Potongan administrasi bank. Mengurangi saldo kas dan dana unrestricted, biasanya Dana Operasional. Tidak boleh dibebankan ke dana restricted.",
      },
      {
        id: "penerimaan",
        name: "Penerimaan",
        description:
          "Uang masuk, misalnya donasi. Mencatat Kas/Bank tempat uang berada dan Dana Amanah tujuannya. Saldo bertambah setelah status menjadi approved.",
      },
      {
        id: "pengeluaran",
        name: "Pengeluaran",
        description:
          "Uang keluar dari Kas/Bank dan dari Dana Amanah yang dipilih. Satu pengeluaran boleh memakai beberapa sumber dana, asal jumlahnya sama dengan nominal. Saldo berkurang setelah approved.",
      },
      {
        id: "reversal",
        name: "Reversal",
        aliases: ["Void", "Pembatalan"],
        description:
          "Cara mengoreksi transaksi yang sudah approved. Sistem menulis jurnal kebalikan. Transaksi asal tetap tersimpan dan tidak diedit.",
      },
      {
        id: "sumber-dana",
        name: "Sumber Dana",
        description:
          "Dana Amanah yang menanggung sebuah pengeluaran. Boleh lebih dari satu baris. Jumlah seluruh sumber harus sama dengan nominal pengeluaran.",
      },
      {
        id: "transfer-antar-rekening",
        name: "Transfer antar rekening",
        aliases: ["Transfer"],
        description: "Memindahkan uang dari satu Kas/Bank ke Kas/Bank lain. Saldo Dana Amanah tidak berubah.",
      },
      {
        id: "transfer-dana",
        name: "Transfer Dana",
        aliases: ["Transfer Dana Amanah"],
        description:
          "Memindahkan batas penggunaan dari satu Dana Amanah ke Dana Amanah lain. Lokasi Kas/Bank tidak berubah.",
      },
    ],
  },
  {
    id: "status",
    title: "Status",
    terms: [
      {
        id: "approved",
        name: "Approved",
        aliases: ["Disetujui"],
        description:
          "Transaksi sudah disetujui dan diposting ke buku besar. Saldo Kas/Bank dan Dana Amanah berubah. Koreksi berikutnya memakai reversal.",
      },
      {
        id: "draft",
        name: "Draft",
        aliases: ["Draf"],
        description:
          "Catatan yang belum dikirim. Penerimaan dan pengeluaran berstatus draft belum masuk buku besar, jadi saldo belum berubah.",
      },
      {
        id: "rejected",
        name: "Rejected",
        aliases: ["Ditolak"],
        description: "Transaksi tidak dilanjutkan. Tidak memposting jurnal dan tidak mengubah saldo.",
      },
      {
        id: "reversed",
        name: "Reversed",
        aliases: ["Dibatalkan"],
        description:
          "Transaksi yang sudah approved dibatalkan dengan jurnal kebalikan. Jejak transaksi asli tetap ada di buku besar.",
      },
      {
        id: "submitted",
        name: "Submitted",
        aliases: ["Dikirim"],
        description:
          "Sudah dikirim dan menunggu langkah berikutnya. Penerimaan menunggu persetujuan. Pengeluaran menunggu verifikasi. Saldo belum berubah.",
      },
      {
        id: "verified",
        name: "Verified",
        aliases: ["Diverifikasi"],
        description:
          "Khusus pengeluaran. Kelengkapan sudah diperiksa dan menunggu persetujuan yang akan memposting jurnal. Penerimaan tidak memakai status ini.",
      },
    ],
  },
  {
    id: "master-data",
    title: "Master data",
    terms: [
      {
        id: "donatur",
        name: "Donatur",
        description:
          "Pemberi dana. Penerimaan dapat ditautkan ke donatur. Akun portal hanya melihat penerimaan approved milik donatur itu.",
      },
      {
        id: "event-program",
        name: "Event/Program",
        aliases: ["Program", "Event"],
        description:
          "Kegiatan atau anggaran yang ingin dilacak. Event/Program bukan pengganti Dana Amanah. Batas pemakaian uang tetap dari dana.",
      },
      {
        id: "vendor",
        name: "Vendor",
        description: "Penerima pembayaran pada pengeluaran, agar nama penerima dan riwayat pembayaran mudah dilacak.",
      },
    ],
  },
  {
    id: "pengajuan-bantuan",
    title: "Pengajuan bantuan",
    terms: [
      {
        id: "individual",
        name: "Individual",
        aliases: ["Perorangan"],
        description: "Cakupan bantuan untuk satu orang pada satu kartu pengajuan.",
      },
      {
        id: "kolektif",
        name: "Kolektif",
        description:
          "Cakupan bantuan untuk banyak orang pada satu kartu. Target jumlah orang diisi sebelum approval. Realisasi diisi saat serah terima selesai.",
      },
      {
        id: "pengajuan-bantuan",
        name: "Pengajuan bantuan",
        aliases: ["Bantuan"],
        description:
          "Kartu satu penerima manfaat, perorangan atau organisasi. Modul ini tidak menulis jurnal. Uang keluar hanya lewat pengeluaran yang ditautkan.",
      },
      {
        id: "selesai",
        name: "Selesai",
        aliases: ["Completed"],
        description:
          "Pengajuan yang pengeluarannya sudah approved dan bukti penyaluran sesuai cara bayar sudah ada. Status approved pada kartu berarti siap diserahkan.",
      },
      {
        id: "serah-terima",
        name: "Serah terima",
        aliases: ["Handover"],
        description:
          "Penyerahan bantuan kepada penerima. Tunai membutuhkan foto penyerahan. Transfer membutuhkan bukti transfer.",
      },
      {
        id: "verifikasi",
        name: "Verifikasi",
        description:
          "Tahap melengkapi data dan lampiran pengajuan di lapangan. Verifikasi ini berbeda dari status verified pada pengeluaran.",
      },
    ],
  },
  {
    id: "kas-kecil",
    title: "Kas kecil",
    terms: [
      {
        id: "float",
        name: "Float",
        aliases: ["Kas kecil"],
        description:
          "Uang muka yang dicairkan lewat pengeluaran. Catatan belanja di dalam float tidak menulis jurnal. Satu float untuk operasional satu periode atau untuk satu event.",
      },
      {
        id: "penanggung-jawab",
        name: "Penanggung jawab",
        aliases: ["PJ"],
        description:
          "Pengguna yang ditugaskan mencatat belanja dan menutup satu float. Penugasan melekat pada float, bukan role baru.",
      },
      {
        id: "top-up",
        name: "Top-up",
        description:
          "Tambahan uang muka pada float yang sama. Dicairkan lewat pengeluaran tambahan setelah diminta penanggung jawab.",
      },
      {
        id: "tutup",
        name: "Tutup",
        aliases: ["Closed"],
        description:
          "Menutup float saat jumlah belanja ditambah sisa yang dikembalikan sama dengan total yang sudah dicairkan.",
      },
    ],
  },
  {
    id: "peran",
    title: "Peran",
    terms: [
      {
        id: "admin",
        name: "Admin",
        aliases: ["Administrator"],
        description: "Menyiapkan pengguna, hak akses, master awal, dan saldo awal saat lembaga mulai memakai SIMA.",
      },
      {
        id: "asisten-bendahara",
        name: "Asisten Bendahara",
        description:
          "Menginput master harian, penerimaan, dan pengeluaran, lalu mengirimkannya. Tidak menyetujui dan tidak membatalkan transaksi yang sudah disetujui.",
      },
      {
        id: "auditor",
        name: "Auditor",
        description: "Melihat laporan dan jejak audit untuk memeriksa riwayat. Tidak mengubah transaksi.",
      },
      {
        id: "bendahara",
        name: "Bendahara",
        description:
          "Menyetujui, menolak, dan membatalkan transaksi keuangan sehingga jurnal masuk atau keluar dari buku besar.",
      },
      {
        id: "donatur-role",
        name: "Donatur",
        description:
          "Peran untuk pemilik dana yang masuk lewat portal. Hanya melihat profil dan penerimaan approved miliknya.",
      },
      {
        id: "ketua",
        name: "Ketua",
        description:
          "Approver pimpinan. Dapat menyetujui, menolak, atau membatalkan transaksi, serta menugaskan dan menyetujui pengajuan bantuan.",
      },
      {
        id: "petugas-bantuan",
        name: "Petugas Bantuan",
        description:
          "Mengajukan, memverifikasi, dan menyerahkan bantuan pada kartu sendiri atau yang ditugaskan. Tidak mengelola master data atau transaksi keuangan.",
      },
      {
        id: "verifikator",
        name: "Verifikator",
        description:
          "Memeriksa kelengkapan pengeluaran sebelum disetujui. Pada bantuan, dapat memverifikasi kartu yang ditugaskan.",
      },
    ],
  },
  {
    id: "jejak",
    title: "Jejak",
    terms: [
      {
        id: "audit-trail",
        name: "Audit Trail",
        aliases: ["Jejak audit"],
        description:
          "Catatan siapa melakukan apa dan kapan. Dipakai untuk menelusuri riwayat perubahan tanpa mengubah angka di buku besar.",
      },
    ],
  },
];

function byName(left: GlossaryTerm, right: GlossaryTerm): number {
  return left.name.localeCompare(right.name, "id");
}

export const glossaryGroups: GlossaryGroup[] = glossaryGroupDefinitions.map((group) => ({
  ...group,
  terms: [...group.terms].sort(byName),
}));

function normalize(value: string): string {
  return value.toLocaleLowerCase("id");
}

export function filterGlossary(groups: readonly GlossaryGroup[], query: string): GlossaryGroup[] {
  const needle = normalize(query).trim();

  if (needle.length === 0) {
    return [...groups];
  }

  return groups
    .map((group) => ({
      ...group,
      terms: group.terms.filter((term) => {
        const haystack = [term.name, term.description, ...(term.aliases ?? [])].join(" ");
        return normalize(haystack).includes(needle);
      }),
    }))
    .filter((group) => group.terms.length > 0);
}
