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
        this.knownOriginals = known.slice();
        // Case-insensitive membership check for the "+ Tilføj"
        // affordance so `GPT-4o` and `gpt-4o` share the same
        // is-known state.
        this.knownLower = new Set(known.map((v) => String(v).toLowerCase()));
        // Track whether the choice list currently carries the
        // ephemeral "+ Tilføj" row, so we only rebuild via
        // `setChoices` when the state has to change.
        this.injectionActive = false;

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
        const matches =
            "" === query || this.knownLower.has(query.toLowerCase());

        if (matches) {
            // Query is empty or already known — the dropdown
            // must not carry a "+ Tilføj" row. Rebuild the choice
            // list to the clean known set only when we currently
            // have an injection to remove; otherwise this is a
            // no-op that would just thrash the DOM.
            if (this.injectionActive) {
                this.rebuildChoices(null);
                this.injectionActive = false;
            }
            return;
        }

        // Query doesn't match anything known — surface the
        // "+ Tilføj" affordance. Rebuild the choice list with a
        // fresh injected row so the label tracks the current
        // query verbatim (an earlier keystroke's injection is
        // replaced by this call).
        this.rebuildChoices(query);
        this.injectionActive = true;
    }

    onChoice(event) {
        const props = event.detail?.choice?.customProperties;
        if (!props || !props.injected) {
            return;
        }
        // The user picked the "+ Tilføj" virtual option. Promote
        // the typed value to a regular known option and drop the
        // ephemeral injection — the pill that renders for the
        // committed value should show the raw value, not the
        // "+ Tilføj: '…'" prefix, and the dropdown must not
        // continue to offer to re-add it.
        const value = event.detail.choice.value;
        this.knownLower.add(value.toLowerCase());
        this.knownOriginals.push(value);
        this.rebuildChoices(null);
        this.injectionActive = false;
    }

    /**
     * Replace the Choices.js choice list with the full known
     * set, optionally appending a single "+ Tilføj" row.
     *
     * Passing `injectionQuery` as a non-empty string emits the
     * injected row after the known options; passing `null` or
     * an empty string emits just the known options.
     *
     * @param {string|null} injectionQuery
     */
    rebuildChoices(injectionQuery) {
        const clean = this.knownOriginals.map((value) => ({
            value,
            label: value,
        }));
        const list =
            injectionQuery && injectionQuery.length > 0
                ? [
                      ...clean,
                      {
                          value: injectionQuery,
                          label: this.addTextValue.replace(
                              "%s",
                              injectionQuery,
                          ),
                          customProperties: { injected: true },
                      },
                  ]
                : clean;
        this.choices.setChoices(list, "value", "label", true);
    }
}
