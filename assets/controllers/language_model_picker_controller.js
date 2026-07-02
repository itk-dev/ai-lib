import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";

/*
 * Free-tagging language-model picker on the wizard's metadata
 * step, powered by Choices.js.
 *
 * Enhances a `<select>` (rendered by
 * `templates/assistant/_new_step_metadata.html.twig`) into a
 * search-as-you-type combobox that offers the SUPPORTED_LANGUAGE_MODELS
 * ∪ previously-persisted-values shortlist as options. When the
 * typed value matches nothing in the shortlist, an ad-hoc
 * "+ Tilføj: '…'" choice is injected via the `search` event and
 * a click on it commits the typed value as a new known option.
 *
 * Choices.js's text-input mode ignores the constructor `choices`
 * option — that's why the picker uses `<select>` here rather
 * than the more obvious `<input type="text">`. Server-side
 * stays a plain `TextType` because the `<select>` on the form
 * carries the full form-field name, so a submitted `<option>`
 * value (whether from the shortlist or a promoted free-tag)
 * round-trips through the form as a plain string.
 *
 * Filtering strategy
 * ------------------
 * We disable Choices.js's built-in `searchChoices` filter and do
 * the filtering ourselves on every keystroke. Two reasons:
 *
 * 1. `setChoices()` (which we call to inject / remove the
 *    "+ Tilføj" row) resets Choices.js's internal filter state,
 *    so relying on the built-in filter and calling `setChoices`
 *    on the side leads to the dropdown flickering back to the
 *    unfiltered list.
 * 2. Doing the filter ourselves lets us guarantee the "+ Tilføj"
 *    row appears exactly once — at the bottom, only when the
 *    query doesn't already match a known option — and lets us
 *    hide the currently-selected value from the dropdown so it
 *    doesn't reappear as the top row on every keystroke.
 *
 * Hiding the current selection
 * ----------------------------
 * Choices.js keeps the currently-picked `<option>` in the
 * underlying `<select>` to preserve the committed value across
 * `setChoices` calls. When the pick came from an injected
 * "+ Tilføj" row, that residual `<option>` still carries its
 * `+ Tilføj: '…'` label + `data-custom-properties="{injected:
 * true}"` marker, and it resurfaces as a duplicate dropdown row
 * on subsequent renders. Excluding the current value from our
 * `setChoices` payload keeps the dropdown clean — the pill
 * still displays the current selection at the top of the
 * widget, but the value doesn't get a second row inside the
 * dropdown list.
 */
export default class extends Controller {
    static values = {
        existingValue: String,
        addText: String,
        searchPlaceholder: String,
        noResultsText: String,
        noChoicesText: String,
        itemSelectText: String,
    };

    connect() {
        // eslint-disable-next-line no-console
        console.log("[language-model-picker] connect (build 26f98e9)");
        const select = this.element.querySelector("select");
        if (!select) {
            // eslint-disable-next-line no-console
            console.warn("[language-model-picker] no <select> found");
            return;
        }
        this.select = select;

        let known;
        try {
            const parsed = JSON.parse(this.existingValueValue || "[]");
            known = Array.isArray(parsed) ? parsed : [];
        } catch {
            known = [];
        }
        // Original casing preserved for the dropdown labels; the
        // lower-cased set handles the case-insensitive membership
        // check so `GPT-4o` and `gpt-4o` share the same
        // is-known state.
        this.knownOriginals = known.slice();
        this.knownLower = new Set(known.map((v) => String(v).toLowerCase()));

        this.choices = new Choices(this.select, {
            allowHTML: false,
            searchEnabled: true,
            // Do the filtering ourselves — see the class docblock.
            searchChoices: false,
            searchResultLimit: 50,
            shouldSort: false,
            removeItemButton: false,
            placeholder: true,
            placeholderValue: this.searchPlaceholderValue,
            searchPlaceholderValue: this.searchPlaceholderValue,
            noResultsText: this.noResultsTextValue,
            noChoicesText: this.noChoicesTextValue,
            itemSelectText: this.itemSelectTextValue,
        });

        this.onSearch = this.onSearch.bind(this);
        this.onChoice = this.onChoice.bind(this);
        this.select.addEventListener("search", this.onSearch);
        this.select.addEventListener("choice", this.onChoice);
    }

    disconnect() {
        if (this.select) {
            this.select.removeEventListener("search", this.onSearch);
            this.select.removeEventListener("choice", this.onChoice);
        }
        if (this.choices) {
            this.choices.destroy();
            this.choices = null;
        }
    }

    onSearch(event) {
        const query = String(event.detail?.value ?? "").trim();
        // eslint-disable-next-line no-console
        console.log("[language-model-picker] onSearch", {
            query,
            currentValue: this.currentValue(),
        });
        this.render(query);
    }

    onChoice(event) {
        const props = event.detail?.choice?.customProperties;
        // eslint-disable-next-line no-console
        console.log("[language-model-picker] onChoice", {
            value: event.detail?.choice?.value,
            injected: Boolean(props?.injected),
        });
        if (!props || !props.injected) {
            return;
        }
        // Promote the typed value into a regular known option so
        // the next `render()` includes it in the shortlist and
        // stops offering to re-add it. The residual injected
        // `<option>` Choices.js keeps around for the current
        // selection is hidden by `render()`'s current-value
        // filter — the pill still shows the picked value at the
        // top of the widget, but the dropdown won't render a
        // duplicate row for it.
        const value = event.detail.choice.value;
        const valueLower = value.toLowerCase();
        if (!this.knownLower.has(valueLower)) {
            this.knownOriginals.push(value);
            this.knownLower.add(valueLower);
        }
    }

    /**
     * Rebuild the dropdown's choice list for the given search
     * query.
     *
     * Emits every known option whose value contains the query
     * (case-insensitive substring) *except* the currently-
     * selected value. When the query is non-empty, doesn't
     * exactly match any known value, and isn't the current
     * selection, appends a single "+ Tilføj: '<query>'" row so
     * the operator can commit the free-typed value in one click.
     *
     * @param {string} query the current search-input contents
     */
    render(query) {
        const queryLower = query.toLowerCase();
        const currentValue = this.currentValue();
        const currentValueLower =
            null === currentValue ? null : currentValue.toLowerCase();

        const filtered = (
            "" === query
                ? this.knownOriginals
                : this.knownOriginals.filter((v) =>
                      v.toLowerCase().includes(queryLower),
                  )
        ).filter((v) => v.toLowerCase() !== currentValueLower);

        const list = filtered.map((v) => ({ value: v, label: v }));

        if (
            "" !== query &&
            !this.knownLower.has(queryLower) &&
            queryLower !== currentValueLower
        ) {
            list.push({
                value: query,
                label: this.addTextValue.replace("%s", query),
                customProperties: { injected: true },
            });
        }

        // eslint-disable-next-line no-console
        console.log("[language-model-picker] render → setChoices", {
            query,
            currentValue,
            listValues: list.map((c) => c.value),
        });
        this.choices.setChoices(list, "value", "label", true);
    }

    /**
     * Return the currently-selected item's value, or `null`
     * when nothing is selected.
     *
     * Choices.js's `getValue(true)` returns the raw value for
     * select-one; we normalise `undefined` / empty-string to
     * `null` so callers can compare with a strict equality.
     *
     * @returns {string|null}
     */
    currentValue() {
        if (!this.choices) {
            return null;
        }
        const raw = this.choices.getValue(true);
        if (raw === null || raw === undefined || raw === "") {
            return null;
        }
        return String(raw);
    }
}
