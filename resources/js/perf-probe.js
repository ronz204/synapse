/**
 * Browser layer of the performance harness (feature 002-perceived-performance).
 *
 * This is NOT bundled into the application — it is not listed in vite.config.js
 * inputs. It is a Node driver that `php artisan perf:measure` spawns, and it
 * reuses the Puppeteer that Browsershot already installs for PDF export, so the
 * harness adds no dependency of its own.
 *
 * What it measures, and why it has to be a real browser:
 *
 *   firstPaintMs   pointerdown -> first DOM mutation. This is budget B-01, the
 *                  "the click registered" acknowledgement. The server never
 *                  sees it; only a browser can.
 *   contentReadyMs pointerdown -> rows painted and interactive. Budget B-02 and
 *                  B-04. A skeleton showing does NOT count as content ready —
 *                  that is what B-01 covers.
 *
 * Usage (driven by the artisan command, not by hand):
 *   node resources/js/perf-probe.js <config.json>   -> JSON on stdout
 */

import puppeteer from "puppeteer";
import { readFileSync } from "node:fs";

const config = JSON.parse(readFileSync(process.argv[2], "utf8"));

/** Rows are painted when a non-header data row exists, or the empty state is shown. */
const CONTENT_SELECTOR = ".table-inner .data-row:not(.data-row-head)";
const EMPTY_SELECTOR = ".card-footer";

/**
 * Installs the in-page probe. Everything below runs inside the browser, so it
 * must stay dependency-free.
 */
async function armProbe(page) {
  await page.evaluate(() => {
    window.__perf = { start: null, firstPaint: null };

    if (window.__perfObserver) {
      window.__perfObserver.disconnect();
    }

    window.__perfObserver = new MutationObserver(() => {
      if (window.__perf.start !== null && window.__perf.firstPaint === null) {
        window.__perf.firstPaint = performance.now();
      }
    });

    window.__perfObserver.observe(document.documentElement, {
      subtree: true,
      childList: true,
      attributes: true,
      characterData: true,
    });
  });
}

async function readProbe(page) {
  return page.evaluate(() => {
    const contentReady = performance.now();

    return {
      firstPaintMs:
        window.__perf.firstPaint === null
          ? null
          : Math.round(window.__perf.firstPaint - window.__perf.start),
      contentReadyMs: Math.round(contentReady - window.__perf.start),
    };
  });
}

/**
 * One observation: arm the probe, dispatch the interaction, wait for content.
 * The clock starts inside the page on pointerdown, not here — measuring from
 * Node would fold the CDP round-trip into every number.
 */
async function measure(page, interact) {
  await armProbe(page);

  await page.evaluate(() => {
    window.__perf.start = performance.now();
    window.__perf.firstPaint = null;
  });

  await interact();

  await page
    .waitForFunction(
      (contentSel, emptySel) => {
        const rows = document.querySelectorAll(contentSel);
        if (rows.length > 0) return true;
        const footer = document.querySelector(emptySel);
        return footer !== null && footer.textContent.trim().length > 0;
      },
      { timeout: config.timeoutMs ?? 15000, polling: "mutation" },
      CONTENT_SELECTOR,
      EMPTY_SELECTOR,
    )
    .catch(() => {
      /* Timeout is reported as a slow observation, not swallowed as a pass. */
    });

  return readProbe(page);
}

async function signIn(page) {
  await page.goto(`${config.baseUrl}/login`, { waitUntil: "networkidle2" });
  await page.type('input[name="email"]', config.email);
  await page.type('input[name="password"]', config.password);

  await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle2" }),
    page.click('button[type="submit"]'),
  ]);
}

/**
 * Navigating by clicking the real sidebar link, not by page.goto: wire:navigate
 * is exactly what is being measured, and goto() would bypass it and measure a
 * full page load instead.
 */
async function openModule(page, module) {
  const selector = `a[href$="${module.path}"]`;

  return measure(page, async () => {
    await page.evaluate((sel) => {
      const link = document.querySelector(sel);
      link.dispatchEvent(new PointerEvent("pointerdown", { bubbles: true }));
      link.click();
    }, selector);
  });
}

async function sortFirstColumn(page) {
  return measure(page, async () => {
    await page.evaluate(() => {
      const header = document.querySelector(
        '.data-row-head [role="columnheader"][data-sortable="true"]',
      );
      if (!header) return;
      header.dispatchEvent(new PointerEvent("pointerdown", { bubbles: true }));
      header.click();
    });
  });
}

async function paginateNext(page) {
  return measure(page, async () => {
    await page.evaluate(() => {
      const buttons = [...document.querySelectorAll(".pagination .page-btn")];
      const next = buttons[buttons.length - 1];
      if (!next || next.disabled) return;
      next.dispatchEvent(new PointerEvent("pointerdown", { bubbles: true }));
      next.click();
    });
  });
}

/**
 * Search is measured from when the input settles, not from each keystroke.
 * FR-008 forbids a query per key, so the debounce is mandatory; measuring from
 * the keystroke would make B-04 unreachable by construction. See
 * contracts/performance-budgets.md, measurement rule 2.
 */
async function search(page, term) {
  await page.evaluate((value) => {
    const input = document.querySelector('input[type="search"]');
    if (!input) return;
    input.focus();
    input.value = value;
    input.dispatchEvent(new Event("input", { bubbles: true }));
  }, term);

  await new Promise((resolve) => setTimeout(resolve, config.debounceMs ?? 300));

  return measure(page, async () => {});
}

const INTERACTIONS = {
  "sort:first-column": sortFirstColumn,
  "paginate:next": paginateNext,
  "search:hit": (page) => search(page, config.searchHitTerm ?? "a"),
  "search:miss": (page) =>
    search(page, config.searchMissTerm ?? "zzzzzzzzzzzz"),
};

async function run() {
  const browser = await puppeteer.launch({
    headless: "new",
    args: ["--no-sandbox", "--disable-dev-shm-usage", "--disable-gpu"],
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });

  const observations = [];
  const notMeasured = [];

  try {
    await signIn(page);

    for (let repetition = 0; repetition < config.repetitions; repetition++) {
      for (const module of config.modules) {
        try {
          const open = await openModule(page, module);
          observations.push({
            module: module.key,
            interaction: "open",
            class: "ModuleOpen",
            ...open,
          });
        } catch (error) {
          notMeasured.push({
            module: module.key,
            interaction: "open",
            reason: error.message,
          });
          continue;
        }

        if (!module.hasList) continue;

        for (const [name, fn] of Object.entries(INTERACTIONS)) {
          try {
            const result = await fn(page);
            observations.push({
              module: module.key,
              interaction: name,
              class: "InModule",
              ...result,
            });
          } catch (error) {
            notMeasured.push({
              module: module.key,
              interaction: name,
              reason: error.message,
            });
          }
        }
      }
    }
  } finally {
    await browser.close();
  }

  process.stdout.write(JSON.stringify({ observations, notMeasured }, null, 2));
}

run().catch((error) => {
  process.stderr.write(`perf-probe failed: ${error.stack}\n`);
  process.exit(1);
});
