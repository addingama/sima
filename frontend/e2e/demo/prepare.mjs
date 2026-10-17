import { execFileSync } from "node:child_process";
import { closeSync, existsSync, openSync, rmSync } from "node:fs";
import { resolve } from "node:path";

const projectRoot = resolve(import.meta.dirname, "../../..");
const database = resolve(projectRoot, "storage/e2e-demo.sqlite");

if (existsSync(database)) {
  rmSync(database);
}
closeSync(openSync(database, "w"));

const env = {
  ...process.env,
  APP_ENV: "testing",
  DB_CONNECTION: "sqlite",
  DB_DATABASE: database,
  CACHE_STORE: "array",
  SESSION_DRIVER: "array",
  QUEUE_CONNECTION: "sync",
  SIMA_PORTAL_AUTO_CREATE_USER: "false",
};

execFileSync("php", ["artisan", "migrate:fresh", "--seed", "--force"], {
  cwd: projectRoot,
  env,
  stdio: "inherit",
});
execFileSync("php", ["artisan", "sima:seed-demo", "--force"], {
  cwd: projectRoot,
  env,
  stdio: "inherit",
});
