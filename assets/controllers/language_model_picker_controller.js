import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";

/*
 * Choices.js on the wizard's language-model `<select>`.
 *
 * Base shape: select-one search combobox seeded from the
 * `<option>` list Symfony pre-renders (SUPPORTED_LANGUAGE_MODELS
 * ∪ previously-persisted values).
 *
 * Free-tag on the fly: every keystroke, if the typed query
 * isn't already a known option, we append it to the choice
 * list as a regular option. That way Choices.js's own search
 * filter surfaces the typed value as a pickable row alongside
 * any matching known options — Enter or click commits it, and
 * the committed option is a plain one (no "+ Add" prefix, no
 * `data-custom-properties` marker). Once picked, the newly-
 * committed value gets promoted to the known-options list so
 * subsequent keystrokes don't re-add it.
 *
 * Turning JS off leaves a plain `<select>` behind.
 */
export default class extends Controller {
    static values = {
        placeholder: String,
    };

    connect() {
        this.select = this.element.querySelector("select");
        if (!this.select) {
            return;
        }
        // Read the known options straight off the pre-rendered
        // `<option>` list — Symfony already wrote them into the
        // DOM from the SupportedLanguageModels service.
        this.knownOriginals = Array.from(this.select.options)
            .map((o) => String(o.value))
            .filter((v) => "" !== v);
        this.knownLower = new Set(
            this.knownOriginals.map((v) => v.toLowerCase()),
        );

        this.choices = new Choices(this.select, {
            allowHTML: false,
            searchEnabled: true,
            // We filter the choice list ourselves in `onSearch` so
            // we can (a) keep the filter in sync with the typed
            // value round-trip below and (b) inject the free-typed
            // value as a pickable option without Choices.js's own
            // filter fighting our `setChoices` call. Every keystroke
            // rebuilds the choice list from a pre-filtered known
            // set — one source of truth, no flicker.
            searchChoices: false,
            searchResultLimit: 50,
            shouldSort: false,
            removeItemButton: false,
            placeholder: true,
            placeholderValue: this.placeholderValue,
            searchPlaceholderValue: this.placeholderValue,
        });

        this.onSearch = this.onSearch.bind(this);
        this.onChoice = this.onChoice.bind(this);
        this.select.addEventListener("search", this.onSearch);
        this.select.addEventListener("choice", this.onChoice);
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
        const queryLower = query.toLowerCase();

        // Filter the known set to case-insensitive substring
        // matches on the query. Empty query → whole set.
        const filtered =
            "" === query
                ? this.knownOriginals
                : this.knownOriginals.filter((v) =>
                      v.toLowerCase().includes(queryLower),
                  );

        const list = filtered.map((v) => ({ value: v, label: v }));

        // If the typed value isn't already in the known set,
        // append it as a pickable row so Enter / click commits
        // it as-is.
        if ("" !== query && !this.knownLower.has(queryLower)) {
            list.push({ value: query, label: query });
        }

        this.choices.setChoices(list, "value", "label", true);
    }

    onChoice(event) {
        const value = event.detail?.choice?.value;
        if (!value) {
            return;
        }
        const lower = String(value).toLowerCase();
        if (this.knownLower.has(lower)) {
            return;
        }
        // The picked value was a just-added free-tag. Promote
        // it to the known-options list so future keystrokes
        // don't re-add it, and rebuild the choice list to the
        // canonical shape (no per-search injection lingering).
        this.knownOriginals.push(value);
        this.knownLower.add(lower);
        this.choices.setChoices(
            this.knownOriginals.map((v) => ({ value: v, label: v })),
            "value",
            "label",
            true,
        );
    }
}
