import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";

/*
 * Choices.js on the wizard's language-model `<select>`.
 *
 * Same shape as the "Options from remote source" example on
 * https://choices-js.github.io/Choices/ — a `<select>` element
 * with the option list seeded (in our case synchronously from
 * the template's data attribute rather than fetched, but the
 * pattern is the same). Default single-select search UI: type
 * to filter, click / Enter to pick, one visible pill for the
 * selection. Turning JS off leaves the plain `<select>`.
 *
 * Free-tagging (typing a model that isn't on the list) is
 * intentionally not wired: Choices.js's select modes don't
 * support it, and mixing text-input mode (which does) with
 * choice seeding doesn't work — the two are separate widgets.
 * If free-tagging turns out to be a hard requirement, the
 * follow-up is a hand-rolled combobox rather than more layers
 * on top of Choices.js.
 */
export default class extends Controller {
    static values = {
        placeholder: String,
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
            placeholderValue: this.placeholderValue,
            searchPlaceholderValue: this.placeholderValue,
        });
    }

    disconnect() {
        if (this.choices) {
            this.choices.destroy();
            this.choices = null;
        }
    }
}
