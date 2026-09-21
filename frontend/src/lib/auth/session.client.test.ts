// biome-ignore-all lint/suspicious/noDocumentCookie: Test perlu menyiapkan state cookie jsdom secara langsung.
import { beforeEach, describe, expect, it } from "vitest";

import type { SimaUser } from "@/lib/api/types";

import { AUTH_TOKEN_COOKIE, AUTH_USER_COOKIE } from "./constants";
import { clearClientSession, getClientToken, getClientUser, setClientSession } from "./session.client";

const user: SimaUser = {
  id: 7,
  name: "Bendahara",
  email: "bendahara@sima.test",
  phone: null,
  is_active: true,
  roles: ["bendahara"],
  permissions: ["receipt.view"],
};

describe("client session", () => {
  beforeEach(() => {
    document.cookie = `${AUTH_TOKEN_COOKIE}=; Path=/; Max-Age=0`;
    document.cookie = `${AUTH_USER_COOKIE}=; Path=/; Max-Age=0`;
  });

  it("menyimpan dan membaca token serta pengguna", () => {
    setClientSession("token dengan spasi", user);

    expect(getClientToken()).toBe("token dengan spasi");
    expect(getClientUser()).toEqual(user);
  });

  it("mengembalikan null ketika cookie pengguna rusak", () => {
    document.cookie = `${AUTH_USER_COOKIE}=${encodeURIComponent("bukan-json")}; Path=/`;

    expect(getClientUser()).toBeNull();
  });

  it("menghapus seluruh data sesi", () => {
    setClientSession("token", user, true);
    clearClientSession();

    expect(getClientToken()).toBeNull();
    expect(getClientUser()).toBeNull();
  });
});
