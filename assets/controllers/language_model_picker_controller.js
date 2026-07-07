import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";

/*
 * Choices.js on the wizard's language-model `<input type="text">`.
 *
 * Runs Choices.js in text-input mode with `maxItemCount: 1`, so
 * the field behaves like a single-choice tagger: the pill shows
 * whatever the curator picked or typed, and the dropdown offers
 * the union of canonical models (config/model_map.yaml) and
 * previously-persisted values seeded from the server.
 *
 * Aliases from the map (e.g. `openai/gpt-4o`, `gpt-4o-2024-08-06`)
 * are exposed as a space-joined string on each choice's
 * customProperties.aliases so Choices.js's Fuse.js search matches
 * them — typing `openai/gpt` filters the dropdown to the canonical
 * gpt-4o row.
 *
 * The pill preserves the curator's typed casing. Server-side, the
 * AssistantCreator folds aliases back to their canonical id so the
 * catalogue facet stays deduplicated even when curators submit
 * variant spellings.
 *
 * Turning JS off leaves the plain `<input list="…">` + `<datalist>`
 * behind — the datalist still offers the seeded values as browser-
 * native autocomplete suggestions.
 */
export default class extends Controller {
    static values = {
        choices: Array,
        placeholder: String,
        addItemText: String,
    };

    connect() {
        this.instance = new Choices(this.element, {
            allowHTML: false,
            maxItemCount: 1,
            addItems: true,
            duplicateItemsAllowed: false,
            editItems: true,
            removeItemButton: true,
            searchFields: ["label", "value", "customProperties.aliases"],
            searchResultLimit: 20,
            placeholder: true,
            placeholderValue: this.placeholderValue || "",
            addItemText: (value) =>
                (this.addItemTextValue || 'Add "__QUERY__"').replace(
                    "__QUERY__",
                    String(value),
                ),
        });

        // Seed the dropdown with the union list the server rendered.
        // Each entry carries its aliases as a space-joined string so
        // the fuzzy-search index matches by any known spelling.
        const seeded = (this.choicesValue || []).map((choice) => ({
            value: String(choice.value),
            label: String(choice.label ?? choice.value),
            customProperties: {
                aliases: Array.isArray(choice.aliases)
                    ? choice.aliases.join(" ")
                    : "",
            },
        }));
        this.instance.setChoices(seeded, "value", "label", true);
    }

    disconnect() {
        if (this.instance) {
            this.instance.destroy();
            this.instance = null;
        }
    }
}
