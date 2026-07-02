import { Controller } from "@hotwired/stimulus";

/*
 * Free-tagging language-model picker on the wizard's metadata
 * step.
 *
 * Enhances a plain `<input type="text" list="…">` +
 * `<datalist>` pairing (rendered by
 * `templates/assistant/_new_step_metadata.html.twig`) with an
 * "Add new" hint that fades in when the currently-typed value
 * matches nothing in the datalist. The server accepts free-typed
 * values as-is, so this controller is purely a UX aid — turning
 * off JS leaves a fully-usable native combobox behind.
 *
 * Wiring:
 *
 * - The wrapper `<div>` carries `data-controller="language-model-picker"`.
 * - The wrapper's `data-language-model-picker-existing-value`
 *   attribute is a JSON array of known option strings, used as
 *   the source of truth for "does the typed value match a
 *   known option?". Reading it once at connect time avoids a
 *   DOM query per keystroke.
 * - The `<p data-language-model-picker-target="hint">` element
 *   inside the wrapper carries the aria-live announcement and
 *   the visible "Add new: …" label. Its `data-add-template`
 *   holds the localised string with an `__VALUE__` placeholder
 *   we substitute at render time; keeping the template in the
 *   DOM keeps the localised copy out of the JS bundle.
 *
 * Behaviour:
 *
 * - When the input matches nothing in the known set (and is
 *   non-empty), the hint is un-hidden and the placeholder
 *   substituted with the typed value.
 * - When the input matches a known option (or is empty), the
 *   hint hides again.
 * - Matching is case-insensitive so `GPT-4o` and `gpt-4o` share
 *   the same hint state.
 */
export default class extends Controller {
    static targets = ["hint"];

    connect() {
        this.input = this.element.querySelector('input[type="text"]');
        if (!this.input) {
            return;
        }

        const raw =
            this.element.dataset.languageModelPickerExistingValue || "[]";
        let parsed;
        try {
            parsed = JSON.parse(raw);
        } catch {
            parsed = [];
        }
        this.knownLower = new Set(
            (Array.isArray(parsed) ? parsed : []).map((v) =>
                String(v).toLowerCase(),
            ),
        );

        this.template = this.hasHintTarget
            ? this.hintTarget.dataset.addTemplate || ""
            : "";

        this.onInput = this.onInput.bind(this);
        this.input.addEventListener("input", this.onInput);
        // Run once so a pre-filled value (extractor's suggestion)
        // gets the hint state correct on first paint.
        this.onInput();
    }

    disconnect() {
        if (this.input) {
            this.input.removeEventListener("input", this.onInput);
        }
    }

    onInput() {
        if (!this.hasHintTarget) {
            return;
        }
        const value = (this.input.value || "").trim();
        if (value === "" || this.knownLower.has(value.toLowerCase())) {
            this.hintTarget.classList.add("hidden");
            this.hintTarget.textContent = "";
            return;
        }
        this.hintTarget.textContent = this.template.replace("__VALUE__", value);
        this.hintTarget.classList.remove("hidden");
    }
}
