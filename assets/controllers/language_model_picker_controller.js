import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";

/*
 * Choices.js on the wizard's language-model `<input>`.
 *
 * Matches the "Text inputs" demo on
 * https://choices-js.github.io/Choices/ — tag-style single
 * value input with:
 *
 *   - `maxItemCount: 1` — one committed value at a time.
 *   - `addItems: true` — the operator can type a new value and
 *     commit it via Enter. `addItemText` supplies the
 *     "+ Tilføj: '…'" prompt Choices.js surfaces while typing.
 *   - `removeItemButton: true` — one-click clear on the pill.
 *
 * Known models (SUPPORTED_LANGUAGE_MODELS ∪ persisted values)
 * are surfaced as help text on the field label — Choices.js's
 * text-input mode has no "known-choices dropdown" as a feature
 * (that's a select-mode capability), so the help text is where
 * operators see what's common and copy-paste if they want.
 *
 * Turning JS off leaves a plain `<input>` behind.
 */
export default class extends Controller {
    static values = {
        addText: String,
        placeholder: String,
    };

    connect() {
        const input = this.element.querySelector('input[type="text"]');
        if (!input) {
            return;
        }

        this.choices = new Choices(input, {
            allowHTML: false,
            editItems: true,
            maxItemCount: 1,
            removeItemButton: true,
            addItems: true,
            addItemText: (value) => this.addTextValue.replace("%s", value),
            duplicateItemsAllowed: false,
            placeholder: true,
            placeholderValue: this.placeholderValue,
        });
    }

    disconnect() {
        if (this.choices) {
            this.choices.destroy();
            this.choices = null;
        }
    }

    /**
     * Commit a suggested value from the help-text pill row into
     * the widget. Click handler for the buttons the template
     * renders under the input; each carries the model name on
     * its `data-value` attribute.
     */
    pick(event) {
        const value = event.currentTarget?.dataset?.value;
        if (!value || !this.choices) {
            return;
        }
        this.choices.removeActiveItems();
        this.choices.setValue([value]);
    }
}
