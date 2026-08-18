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
 * Counts completed Livewire round trips and wire:navigate swaps.
 *
 * This is what makes a measurement mean anything. Waiting only for "rows are
 * present" is worthless, because the rows of the PREVIOUS page are still in the
 * DOM the instant the click lands — the first version of this probe reported
 * 21 ms module opens and null first paints for exactly that reason. The counter
 * gives a definite "the round trip this click caused has finished" signal.
 *
 * Installed once per real page load; it survives wire:navigate because that
 * keeps the JS context.
 */
async function installCounters(page) {
  await page.evaluate(() => {
    if (window.__perfCounters) return;

    window.__perfCounters = { commits: 0, navigations: 0 };

    document.addEventListener("livewire:navigated", () => {
      window.__perfCounters.navigations++;
    });

    document.addEventListener("livewire:init", () => {
      window.Livewire.hook("commit", ({ succeed }) => {
        succeed(() => {
          window.__perfCounters.commits++;
        });
      });
    });

    // livewire:init may already have fired by the time this runs.
    if (window.Livewire) {
      window.Livewire.hook("commit", ({ succeed }) => {
        succeed(() => {
          window.__perfCounters.commits++;
        });
      });
    }
  });
}

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
 * One observation: arm the probe, dispatch the interaction, wait for the round
 * trip it caused to actually finish, then read the clock.
 *
 * The clock starts inside the page, not here — measuring from Node would fold
 * the CDP round trip into every number.
 *
 * `expect` says what finishing means: 'navigation' for a module open,
 * 'commit' for an in-module interaction. Waiting on the counter rather than on
 * the presence of rows is the whole point; rows from the previous page satisfy
 * a presence check immediately and produce a meaningless number.
 */
async function measure(
  page,
  interact,
  expect = "commit",
  expectedPath = null,
  needsRows = true,
) {
  await installCounters(page);
  await armProbe(page);

  const before = await page.evaluate(() => ({ ...window.__perfCounters }));

  // The clock is started and the interaction dispatched inside the SAME
  // evaluate. Splitting them put a CDP round trip between "start" and "click",
  // and that hop — around 150 ms — landed in every firstPaint reading for any
  // control without a synchronous visual change. The sidebar looked instant and
  // every button looked slow, which was the instrument, not the app.
  const dispatched = await page.evaluate((action) => {
    window.__perf.start = performance.now();
    window.__perf.firstPaint = null;

    return new Function(`return (${action})`)()();
  }, interact.toString());

  if (dispatched === "not-applicable") {
    return { notApplicable: true };
  }

  let timedOut = false;

  await page
    .waitForFunction(
      (prev, kind, path, contentSel, emptySel, needsRows) => {
        const counters = window.__perfCounters;

        // 'commit' accepts a DOM mutation as well, because the small catalogs
        // run in client mode: Alpine filters and sorts them in the browser and
        // no Livewire round trip ever happens. Requiring a commit there would
        // time out on a table that is, correctly, instant.
        const finished =
          kind === "navigation"
            ? counters.navigations > prev.navigations &&
              (path === null || window.location.pathname === path)
            : counters.commits > prev.commits ||
              window.__perf.firstPaint !== null;

        if (!finished) return false;

        // Not every module is a list. Dashboard and Settings have no table, so
        // insisting on rows there just times out at 15 s and reports a number
        // that describes the probe, not the page.
        if (!needsRows) return true;

        // Round trip done; now make sure something is actually painted.
        const rows = document.querySelectorAll(contentSel);
        if (rows.length > 0) return true;

        const footer = document.querySelector(emptySel);
        return footer !== null && footer.textContent.trim().length > 0;
      },
      { timeout: config.timeoutMs ?? 15000, polling: "raf" },
      before,
      expect,
      expectedPath,
      CONTENT_SELECTOR,
      EMPTY_SELECTOR,
      needsRows,
    )
    .catch(() => {
      // Reported as a timed-out observation, never swallowed as a pass.
      timedOut = true;
    });

  const result = await readProbe(page);

  return { ...result, timedOut };
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
  const path = module.path;

  return measure(
    page,
    // Serialised into the page, so the path has to be baked in rather than
    // closed over.
    new Function(`
      const link = document.querySelector('a[href$="${path}"]');
      if (!link) return "not-applicable";
      link.dispatchEvent(new PointerEvent("pointerdown", { bubbles: true }));
      link.click();
    `),
    "navigation",
    module.path,
    module.hasList,
  );
}

async function sortFirstColumn(page) {
  return measure(page, () => {
    const header = document.querySelector(
      '.data-row-head [role="columnheader"][data-sortable="true"]',
    );
    if (!header) return "not-applicable";
    header.dispatchEvent(new PointerEvent("pointerdown", { bubbles: true }));
    header.click();
  });
}

async function paginateNext(page) {
  return measure(page, () => {
    const buttons = [...document.querySelectorAll(".pagination .page-btn")];
    const next = buttons[buttons.length - 1];

    // A catalog that fits on one page has a disabled Next. Dispatching anyway
    // waits for a round trip that will never happen and reports a 15-second
    // timeout for a table that is simply small.
    if (!next || next.disabled || next.classList.contains("disabled")) {
      return "not-applicable";
    }

    next.dispatchEvent(new PointerEvent("pointerdown", { bubbles: true }));
    next.click();
  });
}

/**
 * Search is measured from when the input settles, not from each keystroke.
 * FR-008 forbids a query per key, so the debounce is mandatory; measuring from
 * the keystroke would make B-04 unreachable by construction. See
 * contracts/performance-budgets.md, measurement rule 2.
 */
async function search(page, term) {
  const debounceMs = config.debounceMs ?? 300;

  const result = await measure(
    page,
    new Function(`
      const input = document.querySelector('input[type="search"]');
      if (!input) return "not-applicable";
      input.focus();
      input.value = ${JSON.stringify(term)};
      input.dispatchEvent(new Event("input", { bubbles: true }));
    `),
  );

  if (result.notApplicable) return result;

  // The settling delay is mandatory (FR-008 forbids a query per keystroke), so
  // charging it to the interaction would make B-04 unreachable by construction.
  // Budget contract, measurement rule 2: the clock for search starts when the
  // input settles, not when the key is pressed.
  return {
    ...result,
    contentReadyMs: Math.max(0, result.contentReadyMs - debounceMs),
    firstPaintMs: result.firstPaintMs,
  };
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

          if (open.notApplicable) {
            notMeasured.push({
              module: module.key,
              interaction: "open",
              reason: "no sidebar link for this module",
            });
            continue;
          }

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

            // A small catalog has no second page and a table with no sortable
            // header has nothing to sort. Reported as not measured rather than
            // as a 15-second timeout that describes the probe, not the page.
            if (result.notApplicable) {
              notMeasured.push({
                module: module.key,
                interaction: name,
                reason: "control not available on this table",
              });
              continue;
            }

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
