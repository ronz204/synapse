/**
 * Bridges this application's `toast` Livewire event to Flux's toast UI.
 *
 * Every Livewire component here reports the outcome of a write by
 * dispatching a `toast` event:
 *
 *   $this->dispatch('toast', variant: 'danger', text: __('...'));
 *
 * Flux's <flux:toast /> does not listen for that name. It listens for the
 * `toast-show` DOM event, which is what its own Flux.toast() helper emits.
 * Without this bridge every notification in the application is dispatched
 * and silently dropped — a rejected save looks identical to an inert
 * button, and a successful one is never confirmed.
 *
 * Named dispatch parameters arrive as a single object; positional ones
 * arrive wrapped in an array, so both shapes are unwrapped here.
 *
 * Register once in resources/js/app.js:
 *   import './toast.js';
 */
document.addEventListener("livewire:init", () => {
  Livewire.on("toast", (payload) => {
    const { text, heading, variant, duration } = (Array.isArray(payload) ? payload[0] : payload) ?? {};

    // Flux publishes its helper on `window.Flux` during `alpine:init`, which
    // always precedes any user interaction able to dispatch a toast.
    window.Flux?.toast({ text, heading, variant, duration });
  });
});
