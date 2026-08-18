/**
 * Immediate press acknowledgement for the sidebar (FR-001, contract rule R-01).
 *
 * The problem this solves: `wire:current` marks the active nav entry only AFTER
 * wire:navigate has finished fetching and swapping the page. That is correct for
 * showing where you are, but it is not an acknowledgement — between the click
 * and the swap the sidebar looks untouched, and the user cannot tell the click
 * registered. The budget for that feedback is 100 ms; a round trip is not.
 *
 * So the class is applied here, synchronously, in the same task as the
 * pointerdown event. No Livewire, no Alpine, no network.
 *
 * Done with one delegated listener rather than an attribute on each link: the
 * sidebar has nine entries today and gains one per module, and a rule that has
 * to be remembered on every new link is a rule that will be forgotten.
 */

const PENDING_CLASS = "nav-pending";

function clearPending() {
  document
    .querySelectorAll(`.${PENDING_CLASS}`)
    .forEach((element) => element.classList.remove(PENDING_CLASS));
}

// pointerdown, not click: it fires at the start of the press, which is the
// moment being measured, and it covers mouse, touch and pen alike.
document.addEventListener(
  "pointerdown",
  (event) => {
    const item = event.target.closest?.(".nav-item, .nav-child");

    if (!item) return;

    clearPending();
    item.classList.add(PENDING_CLASS);
  },
  { capture: true },
);

// Once the destination is on screen, wire:current owns the active state again
// and the pending hint has done its job.
document.addEventListener("livewire:navigated", clearPending);

// A navigation that fails or is abandoned must not leave a link looking stuck
// mid-press (contract rule R-06).
//
// Abandonment itself — asking for module B while A is still loading — is
// already handled by wire:navigate, which cancels the in-flight request and
// renders the last one asked for. Nothing here needs to reimplement that; what
// it does need is to make sure the visual state does not outlive it.
document.addEventListener("livewire:navigate-failed", clearPending);
window.addEventListener("popstate", clearPending);

/**
 * Failed request feedback (FR-009, contract rule R-07).
 *
 * Every wait has to end. When the network drops or the server errors, Livewire
 * stops the request and, without this, the page simply sits there — a loading
 * state with no possible conclusion, which is the exact failure the requirement
 * names. The banner below ends it and offers the one action that can help.
 */
const BANNER_ID = "request-failure-banner";

function showFailureBanner(message) {
  clearPending();

  if (document.getElementById(BANNER_ID)) return;

  const banner = document.createElement("div");
  banner.id = BANNER_ID;
  banner.className = "request-failure";
  banner.setAttribute("role", "alert");

  const text = document.createElement("span");
  text.textContent = message;

  const retry = document.createElement("button");
  retry.type = "button";
  retry.className = "btn btn-primary";
  retry.textContent = banner.dataset.retryLabel || "Reintentar";
  retry.addEventListener("click", () => window.location.reload());

  const dismiss = document.createElement("button");
  dismiss.type = "button";
  dismiss.className = "btn btn-secondary";
  dismiss.textContent = "×";
  dismiss.setAttribute("aria-label", "Cerrar");
  dismiss.addEventListener("click", () => banner.remove());

  banner.append(text, retry, dismiss);
  document.body.appendChild(banner);
}

document.addEventListener("livewire:navigate-failed", () =>
  showFailureBanner(
    "No se pudo cargar el módulo. Revisá tu conexión e intentá de nuevo.",
  ),
);

document.addEventListener("livewire:init", () => {
  window.Livewire.hook("request", ({ fail }) => {
    fail(({ status, preventDefault }) => {
      // Livewire's own expiry handling already reloads on 419; leave it alone.
      if (status === 419) return;

      preventDefault();
      showFailureBanner(
        "La acción no pudo completarse. Revisá tu conexión e intentá de nuevo.",
      );
    });
  });
});

// A successful navigation means whatever went wrong is behind us.
document.addEventListener("livewire:navigated", () =>
  document.getElementById(BANNER_ID)?.remove(),
);
