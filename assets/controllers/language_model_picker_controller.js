import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";

/*
 * Free-tagging language-model picker on the wizard's metadata
 * step, powered by Choices.js.
 *
 * Enhances a plain `<input type="text">` (rendered by
 * `templates/assistant/_new_step_metadata.html.twig`) into a
 * pill-style combobox that offers the deploy-time-defaults ∪
 * previously-persisted-values shortlist as suggestions and lets
 * the user free-tag any value not on the list via an explicit
 * "+ Tilføj: '…'" affordance.
 *
 * Server-side is unchanged — the field stays a plain `TextType`
 * that accepts any string. Choices.js is configured in text
 * mode with `maxItemCount: 1` so the picker behaves as a
 * single-value selector rather than the multi-tag input Choices
 * defaults to. On form submit the underlying input receives the
 * committed value (Choices.js writes it back before submit
 * fires), so the flow's DTO round-trips as before.
 *
 * Wiring:
 *
 * - The wrapper `<div>` carries `data-controller="language-model-picker"`.
 * - `data-language-model-picker-existing-value` is a JSON array
 *   of known option strings — the union the server-side service
 *   built (`SupportedLanguageModels::list()`).
 * - `data-language-model-picker-add-text` is the localised
 *   "+ Tilføj: '…'" template with a `%s` placeholder Choices.js
 *   fills at render time.
 * - `data-language-model-picker-search-placeholder` /
 *   `no-results-text` / `no-choices-text` supply the remaining
 *   localised copy so the JS stays language-agnostic.
 *
 * On JS-off the untouched native `<input>` is a fully-usable
 * plain text field — the picker degrades gracefully.
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
        this.input = this.element.querySelector('input[type="text"]');
        if (!this.input) {
            return;
        }

        let known;
        try {
            const parsed = JSON.parse(this.existingValueValue || "[]");
            known = Array.isArray(parsed) ? parsed : [];
        } catch {
            known = [];
        }

        const initialValue = (this.input.value || "").trim();

        this.choices = new Choices(this.input, {
            allowHTML: false,
            removeItemButton: true,
            duplicateItemsAllowed: false,
            editItems: true,
            maxItemCount: 1,
            searchEnabled: true,
            searchResultLimit: 20,
            addItems: true,
            addItemText: (value) => this.addTextValue.replace("%s", value),
            placeholder: true,
            placeholderValue: this.searchPlaceholderValue,
            searchPlaceholderValue: this.searchPlaceholderValue,
            noResultsText: this.noResultsTextValue,
            noChoicesText: this.noChoicesTextValue,
            itemSelectText: this.itemSelectTextValue,
            shouldSort: false,
            choices: known.map((value) => ({
                value,
                label: value,
                selected: value === initialValue,
            })),
        });
    }

    disconnect() {
        if (this.choices) {
            this.choices.destroy();
            this.choices = null;
        }
    }
}
