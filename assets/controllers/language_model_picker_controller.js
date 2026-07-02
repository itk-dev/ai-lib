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
 *    query doesn't already match a known option — no matter how
 *    Choices.js's fuse.js scores partial-string matches on the
 *    injected row's label.
 *
 * Promoting a free-tag pick
 * -------------------------
 * When the user clicks "+ Tilføj: '…'", Choices.js commits it as
 * the current item and writes its underlying `<option>` into the
 * `<select>` with the `+ Tilføj: '…'` label + a
 * `data-custom-properties="{injected: true}"` marker. Neither
 * `setChoices(replaceChoices: true)` nor `removeActiveItemsByValue`
 * fully strips that residual `<option>` — the select's selected
 * option survives every rebuild by design (Choices.js protects
 * the current value). We work around that by destroying Choices.js,
 * rewriting the `<select>`'s option list to the clean known set
 * with the promoted value pre-selected, and re-initialising
 * Choices.js on the fresh DOM. Nuclear but reliable.
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

        this.onSearch = this.onSearch.bind(this);
        this.onChoice = this.onChoice.bind(this);
        this.initChoices();
    }

    disconnect() {
        this.teardownChoices();
    }

    /**
     * Instantiate Choices.js against the current `<select>` and
     * wire the `search` + `choice` event handlers.
     */
    initChoices() {
        this.choices = new Choices(this.select, this.choicesConfig());
        this.select.addEventListener("search", this.onSearch);
        this.select.addEventListener("choice", this.onChoice);
    }

    /**
     * Destroy the Choices.js instance and detach its event
     * handlers. Leaves the `<select>` in whatever state it
     * currently has (the caller reassigns its options before
     * re-init).
     */
    teardownChoices() {
        if (this.select) {
            this.select.removeEventListener("search", this.onSearch);
            this.select.removeEventListener("choice", this.onChoice);
        }
        if (this.choices) {
            this.choices.destroy();
            this.choices = null;
        }
    }

    /**
     * @returns {import("choices.js").Options}
     */
    choicesConfig() {
        return {
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
        };
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
        // Promote the typed value to a regular known option.
        const value = event.detail.choice.value;
        const valueLower = value.toLowerCase();
        if (!this.knownLower.has(valueLower)) {
            this.knownOriginals.push(value);
            this.knownLower.add(valueLower);
        }
        // Defer to the next tick so Choices.js has finished
        // committing the injected pick (writing its residual
        // `<option>`) before we tear it down.
        setTimeout(() => this.rebuildWithSelection(value), 0);
    }

    /**
     * Rebuild the `<select>` from scratch with the clean known
     * option set and the given value pre-selected, then
     * re-instantiate Choices.js on top of it.
     *
     * @param {string} selectedValue
     */
    rebuildWithSelection(selectedValue) {
        this.teardownChoices();
        this.select.innerHTML = "";
        for (const value of this.knownOriginals) {
            const opt = document.createElement("option");
            opt.value = value;
            opt.textContent = value;
            if (value === selectedValue) {
                opt.selected = true;
            }
            this.select.appendChild(opt);
        }
        this.initChoices();
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
