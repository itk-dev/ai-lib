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
 * a click on it commits the typed value as a real option.
 *
 * Choices.js's text-input mode ignores the constructor `choices`
 * option — that's why the picker uses `<select>` here rather
 * than the more obvious `<input type="text">`. Server-side
 * stays a plain `TextType` because the `<select>` on the form
 * carries the full form-field name, so a submitted `<option>`
 * value (whether from the shortlist or the "+ Tilføj" injection)
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
 *    query doesn't already match a known option — no matter how
 *    Choices.js's fuse.js scores partial-string matches on the
 *    injected row's label.
 *
 * Wiring
 * ------
 * - The wrapper `<div>` carries
 *   `data-controller="language-model-picker"`.
 * - `data-language-model-picker-existing-value` is a JSON array
 *   of known option strings — the server-side union built by
 *   `App\Model\SupportedLanguageModels::list()`.
 * - The other `data-...-value` attributes hand the controller
 *   the localised copy so the JS bundle stays
 *   language-agnostic.
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
        const select = this.element.querySelector("select");
        if (!select) {
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

        this.choices = new Choices(select, {
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
        select.addEventListener("search", this.onSearch);
        select.addEventListener("choice", this.onChoice);
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
        this.render(query);
    }

    onChoice(event) {
        const props = event.detail?.choice?.customProperties;
        if (!props || !props.injected) {
            return;
        }
        // The user picked the "+ Tilføj" virtual option. Promote
        // the typed value to a regular known option so the pill
        // renders as the raw value (not "+ Tilføj: '…'") and the
        // dropdown stops offering to re-add it.
        const value = event.detail.choice.value;
        const valueLower = value.toLowerCase();
        if (!this.knownLower.has(valueLower)) {
            this.knownOriginals.push(value);
            this.knownLower.add(valueLower);
        }
        // Rebuild without the injected row so the newly-promoted
        // value replaces its placeholder representation.
        this.render("");
    }

    /**
     * Rebuild the dropdown's choice list for the given search
     * query.
     *
     * Emits every known option whose value contains the query
     * (case-insensitive substring). When the query is non-empty
     * and doesn't exactly match any known value, appends a single
     * "+ Tilføj: '<query>'" row so the operator can commit the
     * free-typed value in one click.
     *
     * @param {string} query the current search-input contents
     */
    render(query) {
        const queryLower = query.toLowerCase();
        const filtered =
            "" === query
                ? this.knownOriginals
                : this.knownOriginals.filter((v) =>
                      v.toLowerCase().includes(queryLower),
                  );

        const list = filtered.map((v) => ({ value: v, label: v }));

        if ("" !== query && !this.knownLower.has(queryLower)) {
            list.push({
                value: query,
                label: this.addTextValue.replace("%s", query),
                customProperties: { injected: true },
            });
        }

        this.choices.setChoices(list, "value", "label", true);
    }
}
