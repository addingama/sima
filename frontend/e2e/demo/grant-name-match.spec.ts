import { test } from "@playwright/test";

import { seedCompletedSameNameGrant, showSameNameFlag } from "../flows/grant-name-match";
import { caption } from "./helpers";

test("bnt-nama penerima sama boleh dibantu lagi", async ({ page, request }) => {
  test.setTimeout(10 * 60 * 1000);
  await seedCompletedSameNameGrant(request, "http://127.0.0.1:8110/api");
  await showSameNameFlag(page, (text, holdMs) => caption(page, text, holdMs));
});
