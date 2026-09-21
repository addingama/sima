import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { ApiError, apiBlob, apiDelete, apiDownload, apiFetch, apiGet, apiPost, apiPut } from "./client";

const successEnvelope = { success: true, message: "OK", data: { id: 1 } };

function response(options: {
  ok?: boolean;
  status?: number;
  json?: unknown;
  jsonError?: Error;
  blob?: Blob;
}): Response {
  return {
    ok: options.ok ?? true,
    status: options.status ?? 200,
    json: options.jsonError ? vi.fn().mockRejectedValue(options.jsonError) : vi.fn().mockResolvedValue(options.json),
    blob: vi.fn().mockResolvedValue(options.blob ?? new Blob(["file"])),
  } as unknown as Response;
}

describe("API client", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it("mengirim header JSON dan bearer token eksplisit", async () => {
    vi.mocked(fetch).mockResolvedValue(response({ json: successEnvelope }));

    await apiFetch("/funds", { method: "GET" }, "token-rahasia");

    expect(fetch).toHaveBeenCalledOnce();
    const [url, options] = vi.mocked(fetch).mock.calls[0];
    const headers = options?.headers as Headers;

    expect(url).toBe("http://localhost:8000/api/funds");
    expect(headers.get("Accept")).toBe("application/json");
    expect(headers.get("Content-Type")).toBe("application/json");
    expect(headers.get("Authorization")).toBe("Bearer token-rahasia");
  });

  it("tidak memaksa content type untuk FormData", async () => {
    vi.mocked(fetch).mockResolvedValue(response({ json: successEnvelope }));

    await apiFetch("/attachments", { method: "POST", body: new FormData() }, null);

    const headers = vi.mocked(fetch).mock.calls[0][1]?.headers as Headers;
    expect(headers.has("Content-Type")).toBe(false);
  });

  it("meneruskan error API beserta status, kode, dan field validasi", async () => {
    vi.mocked(fetch).mockResolvedValue(
      response({
        ok: false,
        status: 422,
        json: {
          success: false,
          message: "Validasi gagal.",
          data: null,
          errors: { code: "VALIDATION_ERROR", fields: { amount: ["Nominal wajib diisi."] } },
        },
      }),
    );

    await expect(apiPost("/receipts", {})).rejects.toMatchObject({
      name: "ApiError",
      message: "Validasi gagal.",
      status: 422,
      code: "VALIDATION_ERROR",
      fields: { amount: ["Nominal wajib diisi."] },
    });
  });

  it.each([
    [413, "Ukuran unggahan terlalu besar."],
    [500, "Server mengembalikan respons tidak valid."],
  ])("menghasilkan pesan aman untuk respons non-JSON status %s", async (status, message) => {
    vi.mocked(fetch).mockResolvedValue(response({ status, jsonError: new Error("invalid json") }));

    await expect(apiFetch("/invalid")).rejects.toEqual(new ApiError(message, status));
  });

  it("membangun query dan melewati nilai kosong", async () => {
    vi.mocked(fetch).mockResolvedValue(response({ json: successEnvelope }));

    await apiGet("/audits", { page: 2, per_page: 25, q: "amanah", sort: undefined, direction: "desc" });

    expect(fetch).toHaveBeenCalledWith(
      "http://localhost:8000/api/audits?page=2&per_page=25&q=amanah&direction=desc",
      expect.objectContaining({ method: "GET" }),
    );
  });

  it("mengirim body JSON untuk POST dan PUT serta tanpa body untuk DELETE", async () => {
    vi.mocked(fetch).mockResolvedValue(response({ json: successEnvelope }));

    await apiPost("/funds", { name: "Dana" }, "token");
    await apiPut("/funds/1", { name: "Dana Baru" }, "token");
    await apiDelete("/funds/1", "token");

    expect(vi.mocked(fetch).mock.calls[0][1]).toMatchObject({ method: "POST", body: '{"name":"Dana"}' });
    expect(vi.mocked(fetch).mock.calls[1][1]).toMatchObject({ method: "PUT", body: '{"name":"Dana Baru"}' });
    expect(vi.mocked(fetch).mock.calls[2][1]).toMatchObject({ method: "DELETE" });
  });

  it("mengembalikan blob dan memakai pesan error download dari API", async () => {
    const file = new Blob(["laporan"]);
    vi.mocked(fetch)
      .mockResolvedValueOnce(response({ blob: file }))
      .mockResolvedValueOnce(response({ ok: false, status: 403, json: { message: "Tidak diizinkan." } }));

    await expect(apiBlob("/reports/export", "token")).resolves.toBe(file);
    await expect(apiBlob("/reports/export", "token")).rejects.toMatchObject({
      message: "Tidak diizinkan.",
      status: 403,
    });
  });

  it("mengunduh blob melalui anchor sementara dan mencabut object URL", async () => {
    vi.mocked(fetch).mockResolvedValue(response({ blob: new Blob(["laporan"]) }));
    const click = vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => undefined);
    const createObjectURL = vi.fn(() => "blob:report");
    const revokeObjectURL = vi.fn();
    Object.defineProperty(URL, "createObjectURL", { configurable: true, value: createObjectURL });
    Object.defineProperty(URL, "revokeObjectURL", { configurable: true, value: revokeObjectURL });

    await apiDownload("/reports/export", "laporan.pdf", "token");

    expect(click).toHaveBeenCalledOnce();
    expect(createObjectURL).toHaveBeenCalledOnce();
    expect(revokeObjectURL).toHaveBeenCalledWith("blob:report");
    expect(document.querySelector('a[download="laporan.pdf"]')).toBeNull();
  });
});
