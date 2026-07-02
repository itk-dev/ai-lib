import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";

/*
 * Choices.js on the wizard's language-model field.
 *
 * The Symfony form renders `languageModel` as a plain
 * `<input type="text">`. Choices.js in text-input mode
 * (`maxItemCount: 1` for single value, `addItems: true` for
 * free-typing, `removeItemButton: true` so the operator can
 * clear a pick with one click) turns it into a pill-style
 * combobox that:
 *
 * - Shows the SUPPORTED_LANGUAGE_MODELS ∪ previously-persisted-
 *   values shortlist as dropdown suggestions (seeded via
 *   `setChoices()` after init — constructor `choices` is
 *   ignored for text inputs).
 * - Lets the operator commit a suggestion in one click.
 * - Lets the operator free-type a value not on the shortlist
 *   and commit it via Enter (Choices.js shows a
 *   "+ Tilføj: '…'" affordance controlled by `addItemText`).
 *
 * On JS-off the untouched `<input>` is a plain text field —
 * the picker degrades gracefully.
 */
export default class extends Controller {
    static values = {
        knownOptions: String,
        addText: String,
        searchPlaceholder: String,
        noResultsText: String,
        noChoicesText: String,
        itemSelectText: String,
    };

    connect() {
        const input = this.element.querySelector('input[type="text"]');
        if (!input) {
            return;
        }

        let known;
        try {
            const parsed = JSON.parse(this.knownOptionsValue || "[]");
            known = Array.isArray(parsed) ? parsed : [];
        } catch {
            known = [];
        }

        const initial = String(input.value || "").trim();

        this.choices = new Choices(input, {
            allowHTML: false,
            editItems: true,
            maxItemCount: 1,
            removeItemButton: true,
            addItems: true,
            addItemText: (value) => this.addTextValue.replace("%s", value),
            duplicateItemsAllowed: false,
            searchEnabled: true,
            searchResultLimit: 50,
            shouldSort: false,
            placeholder: true,
            placeholderValue: this.searchPlaceholderValue,
            searchPlaceholderValue: this.searchPlaceholderValue,
            noResultsText: this.noResultsTextValue,
            noChoicesText: this.noChoicesTextValue,
            itemSelectText: this.itemSelectTextValue,
        });

        // Seed the dropdown suggestions. `choices` in the
        // constructor is ignored for text inputs, so we call
        // `setChoices` explicitly.
        if (known.length > 0) {
            this.choices.setChoices(
                known.map((v) => ({ value: v, label: v })),
                "value",
                "label",
                false,
            );
        }

        // Pre-select the extractor's suggestion, if any.
        if ("" !== initial) {
            this.choices.setValue([initial]);
        }
    }

    disconnect() {
        if (this.choices) {
            this.choices.destroy();
            this.choices = null;
        }
    }
}
