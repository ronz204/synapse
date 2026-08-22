/**
 * Client-side-filtered course picker for a small, already-known set of
 * candidates — e.g. the courses already linked to the study plan being
 * edited. Unlike <x-ui.course-combobox> (App\Livewire\Concerns\
 * InteractsWithCourseSearch), which searches the whole catalog through a
 * debounced Livewire round trip per keystroke, this filters entirely in the
 * browser: the candidate list is already on the page and never more than a
 * few dozen rows, so a network request per keystroke would be pure
 * overhead.
 *
 * Usage (Blade):
 *   <div x-data="localCourseCombobox({ options: @js($options) })">
 *     <input x-model="query" @focus="open = true">
 *     <template x-for="course in filtered" :key="course.id">
 *       <button @click="choose(course, (c) => $wire.someAction(c.id))">...</button>
 *     </template>
 *   </div>
 *
 * Register once in resources/js/app.js: import './local-course-combobox.js';
 */
document.addEventListener("alpine:init", () => {
  Alpine.data("localCourseCombobox", (config = {}) => ({
    options: config.options ?? [],
    query: "",
    open: false,
    dropTop: 0,
    dropLeft: 0,
    dropWidth: 0,

    get filtered() {
      const term = this.query.trim().toLowerCase();
      if (term === "") return this.options;

      return this.options.filter(
        (course) =>
          course.code.toLowerCase().includes(term) ||
          course.name.toLowerCase().includes(term),
      );
    },

    reposition() {
      // position:fixed is viewport-relative, so this must NOT add
      // window.scrollY/scrollX — the dropdown would render off the
      // portion of the page currently in view.
      const rect = this.$refs.input.getBoundingClientRect();
      this.dropTop   = rect.bottom + 4;
      this.dropLeft  = rect.left;
      this.dropWidth = rect.width;
    },

    openDrop() {
      this.reposition();
      this.open = true;
    },

    _onScroll: null,
    _onResize: null,

    init() {
      this._onScroll = () => { if (this.open) this.reposition(); };
      this._onResize = () => { if (this.open) this.reposition(); };
      window.addEventListener("scroll", this._onScroll, true);
      window.addEventListener("resize", this._onResize);
    },

    destroy() {
      window.removeEventListener("scroll", this._onScroll, true);
      window.removeEventListener("resize", this._onResize);
    },

    choose(course, onChosen) {
      onChosen(course);
      this.query = "";
      this.open = false;
    },
  }));
});
