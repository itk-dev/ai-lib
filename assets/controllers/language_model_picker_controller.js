import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";

/*
 * Choices.js on the wizard's language-model `<select>`.
 *
 * Bare-bones single-value combobox: search-as-you-type across
 * the `<option>` list the template renders from
 * SUPPORTED_LANGUAGE_MODELS ∪ previously-persisted values. No
 * injection, no ad-hoc "add new" affordance — Choices.js's own
 * search + selection UI handles the whole flow, and turning JS
 * off leaves a plain `<select>` behind.
 */
export default class extends Controller {
    static values = {
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

        this.choices = new Choices(select, {
            allowHTML: false,
            searchEnabled: true,
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
    }

    disconnect() {
        if (this.choices) {
            this.choices.destroy();
            this.choices = null;
        }
    }
}
