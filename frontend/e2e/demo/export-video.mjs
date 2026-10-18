import { execFileSync } from "node:child_process";
import { existsSync, mkdirSync, readdirSync, statSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const frontendRoot = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
const projectRoot = resolve(frontendRoot, "..");
const resultsDir = resolve(frontendRoot, "test-results-demo");
const outDir = resolve(projectRoot, "docs/demo/videos");

const targets = [
  { match: /dashboard-dan-dana|rekonsiliasi-seimbang/, basename: "dashboard-dana" },
  { match: /penerimaan-dari-draft/, basename: "penerimaan" },
  { match: /pengeluaran-dari-draft/, basename: "pengeluaran" },
  { match: /portal-donatur/, basename: "portal-donatur" },
  { match: /instansi|kolektif|air-bersih/, basename: "pengajuan-bantuan-instansi" },
  { match: /rekomendasi-sampai-selesai/, basename: "pengajuan-bantuan" },
  { match: /bnt-nama/, basename: "pengajuan-bantuan-nama-sama" },
  { match: /bnt-komentar/, basename: "pengajuan-bantuan-komentar" },
  // Keyword pendek di awal judul tes (Playwright memotong nama folder jika terlalu panjang).
  { match: /ksk-ops/, basename: "kas-kecil-operasional" },
  { match: /ksk-event|kas-kecil-event/, basename: "kas-kecil-event" },
];

function findVideos(dir) {
  if (!existsSync(dir)) {
    return [];
  }

  const found = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) {
      found.push(...findVideos(path));
    } else if (entry.name === "video.webm") {
      found.push(path);
    }
  }

  return found;
}

function convert(source, basename) {
  const outMp4 = join(outDir, `${basename}.mp4`);
  const outPoster = join(outDir, `${basename}-poster.png`);

  execFileSync(
    "ffmpeg",
    [
      "-y",
      "-i",
      source,
      "-an",
      "-vf",
      "scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2",
      "-c:v",
      "libx264",
      "-preset",
      "medium",
      "-crf",
      "23",
      "-pix_fmt",
      "yuv420p",
      "-movflags",
      "+faststart",
      outMp4,
    ],
    { stdio: "inherit" },
  );

  execFileSync("ffmpeg", ["-y", "-ss", "00:00:08", "-i", outMp4, "-frames:v", "1", outPoster], { stdio: "inherit" });
  console.log(`Wrote ${outMp4}`);
  console.log(`Wrote ${outPoster}`);
}

const videos = findVideos(resultsDir).sort((a, b) => statSync(b).mtimeMs - statSync(a).mtimeMs);
if (!videos.length) {
  throw new Error(`Tidak ada video.webm di ${resultsDir}. Jalankan npm run demo:grant-video dulu.`);
}

mkdirSync(outDir, { recursive: true });

const used = new Set();
let converted = 0;
for (const target of targets) {
  const source = videos.find((path) => target.match.test(path) && !used.has(path));
  if (!source) {
    console.warn(`Skip ${target.basename} — tidak ada video.webm yang cocok (pola ${target.match}).`);
    continue;
  }
  used.add(source);
  convert(source, target.basename);
  converted += 1;
}

if (converted === 0) {
  throw new Error(`Tidak ada video yang dikonversi dari ${resultsDir}.`);
}
console.log(`Selesai: ${converted} video → ${outDir}`);
