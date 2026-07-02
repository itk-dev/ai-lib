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
 * Wiring:
 *
 * - The wrapper `<div>` carries `data-controller="language-model-picker"`.
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
        // Case-insensitive membership check for the "+ Tilføj"
        // affordance so `GPT-4o` and `gpt-4o` share the same
        // is-known state.
        this.knownLower = new Set(known.map((v) => String(v).toLowerCase()));

        this.choices = new Choices(select, {
            allowHTML: false,
            searchEnabled: true,
            searchResultLimit: 20,
            shouldSort: false,
            removeItemButton: false,
            placeholder: true,
            placeholderValue: this.searchPlaceholderValue,
            searchPlaceholderValue: this.searchPlaceholderValue,
            noResultsText: this.noResultsTextValue,
            noChoicesText: this.noChoicesTextValue,
            itemSelectText: this.itemSelectTextValue,
        });

        // On every keystroke, inject a "+ Tilføj: '<query>'"
        // choice when the query doesn't match any known option
        // (and isn't already the value on a previous injection).
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
        if ("" === query || this.knownLower.has(query.toLowerCase())) {
            // Match — Choices.js's own search filter handles the
            // dropdown. Nothing to inject.
            return;
        }
        // Inject a virtual "+ Tilføj" option. `setChoices` with
        // `replaceChoices: false` appends, but we want a single
        // ephemeral row that follows the typed query — clear the
        // last inject first via `clearChoices` isn't a public API,
        // so we replace the choice list with the full known set
        // plus the injected row. Ordering keeps the known matches
        // first so a real match ranks above the inject.
        const injected = [
            {
                value: query,
                label: this.addTextValue.replace("%s", query),
                customProperties: { injected: true },
            },
        ];
        // `setChoices` on a select input replaces the full choice
        // list. Include the known set so real matches still show.
        // Casing comes from the initial JSON payload (via
        // `originalKnown()`) so the picker shows what the user
        // typed to reach a match rather than the lowercased key.
        const originals = this.originalKnown();
        this.choices.setChoices(
            [...originals.map((v) => ({ value: v, label: v })), ...injected],
            "value",
            "label",
            true,
        );
    }

    onChoice(event) {
        const props = event.detail?.choice?.customProperties;
        if (!props || !props.injected) {
            return;
        }
        // The user picked the "+ Tilføj" virtual option. The
        // committed value is the typed query; rebase the choice
        // set so the value sits alongside the known options
        // rather than as an ephemeral inject.
        const value = event.detail.choice.value;
        this.knownLower.add(value.toLowerCase());
        this._extraKnown = this._extraKnown || [];
        this._extraKnown.push(value);
    }

    /**
     * Return the currently-known option list in original casing.
     * Kept as a helper so the search handler can seed
     * `setChoices()` with the same casing the user sees in the
     * initial dropdown.
     */
    originalKnown() {
        try {
            const parsed = JSON.parse(this.existingValueValue || "[]");
            const base = Array.isArray(parsed) ? parsed : [];
            if (this._extraKnown && this._extraKnown.length) {
                return [...base, ...this._extraKnown];
            }
            return base;
        } catch {
            return [];
        }
    }
}
