import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { createServer } from "node:net";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import lighthouse from "lighthouse";
import { chromium } from "@playwright/test";

const baseUrl = process.env.LH_BASE_URL ?? "http://localhost:5173";
const apiBaseUrl = process.env.LH_API_BASE_URL ?? "http://localhost:8080/api";
const runs = Number.parseInt(process.env.LH_RUNS ?? "3", 10);
const reportDirectory = resolve(process.env.LH_REPORT_DIR ?? "lighthouse-reports");
const categories = ["performance", "accessibility", "best-practices", "seo"];

const defaultRoutes = [
  "/login",
  "/programme",
  "/planifier",
  "/compte",
  "/admin",
  "/admin/materiels",
  "/admin/materiels/new",
  "/programmes/2026-09-02/Salle%20A/21",
  "/chirurgies/159/preparation",
  "/chirurgies/159/validation-partielle",
  "/chirurgies/159/vue-finale",
  "/page-inexistante",
];

const routes = process.env.LH_ROUTES
  ? process.env.LH_ROUTES.split(",").map((route) => route.trim()).filter(Boolean)
  : defaultRoutes;

function getFreePort() {
  return new Promise((resolvePort, reject) => {
    const server = createServer();
    server.unref();
    server.on("error", reject);
    server.listen(0, "127.0.0.1", () => {
      const address = server.address();
      const port = typeof address === "object" && address ? address.port : null;
      server.close(() => (port ? resolvePort(port) : reject(new Error("Port CDP introuvable."))));
    });
  });
}

function routeName(route) {
  return route === "/"
    ? "accueil"
    : route.replace(/^\//, "").replaceAll("/", "-").replaceAll("%20", "-");
}

function scoresFrom(result) {
  return Object.fromEntries(
    categories.map((category) => [category, Math.round(result.lhr.categories[category].score * 100)]),
  );
}

function median(values) {
  const ordered = [...values].sort((left, right) => left - right);
  return ordered[Math.floor(ordered.length / 2)];
}

async function authenticate(context) {
  const email = process.env.LH_EMAIL;
  const password = process.env.LH_PASSWORD;

  if (!email || !password) {
    throw new Error("LH_EMAIL et LH_PASSWORD doivent être définis pour auditer les routes protégées.");
  }

  const response = await context.request.post(`${apiBaseUrl}/login`, {
    data: { email, password },
    headers: { Origin: baseUrl },
  });

  if (!response.ok()) {
    throw new Error(`La connexion Lighthouse a échoué avec le statut ${response.status()}.`);
  }
}

async function auditRoute(route, port, context, requiresAuthentication = false) {
  const results = [];

  for (let run = 1; run <= runs; run += 1) {
    if (requiresAuthentication) await authenticate(context);

    const url = new URL(route, baseUrl).href;
    console.log(`[${run}/${runs}] ${url}`);
    const result = await lighthouse(url, {
      port,
      output: "html",
      logLevel: "error",
      disableStorageReset: true,
    });

    if (!result) throw new Error(`Lighthouse n'a produit aucun résultat pour ${url}.`);

    const name = `${routeName(route)}-${run}`;
    writeFileSync(join(reportDirectory, `${name}.html`), result.report);
    writeFileSync(join(reportDirectory, `${name}.json`), JSON.stringify(result.lhr, null, 2));
    results.push(scoresFrom(result));
  }

  return Object.fromEntries(
    categories.map((category) => [category, median(results.map((result) => result[category]))]),
  );
}

async function main() {
  if (!Number.isInteger(runs) || runs < 1) throw new Error("LH_RUNS doit être un entier positif.");
  if (routes.length === 0) throw new Error("Aucune route Lighthouse configurée.");

  mkdirSync(reportDirectory, { recursive: true });
  const profileDirectory = mkdtempSync(join(tmpdir(), "chirorg-lighthouse-"));
  const port = await getFreePort();
  let context;

  try {
    context = await chromium.launchPersistentContext(profileDirectory, {
      headless: true,
      args: [`--remote-debugging-port=${port}`, "--disable-gpu"],
    });

    const summary = {};
    if (routes.includes("/login")) {
      summary["/login"] = await auditRoute("/login", port, context);
    }

    const protectedRoutes = routes.filter((route) => route !== "/login");

    for (const route of protectedRoutes) {
      summary[route] = await auditRoute(route, port, context, true);
    }

    const global = Object.fromEntries(
      categories.map((category) => {
        const values = Object.values(summary).map((scores) => scores[category]);
        return [category, { median: median(values), minimum: Math.min(...values) }];
      }),
    );

    const report = { generatedAt: new Date().toISOString(), runs, pages: summary, global };
    writeFileSync(join(reportDirectory, "summary.json"), JSON.stringify(report, null, 2));

    console.table(summary);
    console.table(global);
    console.log(`Rapports générés dans ${reportDirectory}`);
  } finally {
    await context?.close();
    try {
      rmSync(profileDirectory, { recursive: true, force: true });
    } catch {
      console.warn(`Le profil Chrome temporaire n'a pas pu être supprimé : ${profileDirectory}`);
    }
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
