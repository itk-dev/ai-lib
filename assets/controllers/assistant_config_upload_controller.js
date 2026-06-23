import { Controller } from "@hotwired/stimulus";

/*
 * Upload + validate the OpenWebUI config JSON for the assistant
 * create form.
 *
 * Mounted on the form (`data-controller="assistant-config-upload"`).
 * The file input is the `file` target; selecting a file kicks off:
 *
 *   1. Read the file contents via FileReader.
 *   2. For each check in the `checks` value, POST the JSON to the
 *      validate URL with that check's identifier and wait for the
 *      response. Each completed check moves the progress bar one
 *      notch forward — the progress is a count of finished checks,
 *      not a timer.
 *   3. If any check fails: show its errors, clear the hidden field,
 *      stop iterating.
 *   4. If every check passes: populate the hidden form field with
 *      the JSON content and mark the status as valid.
 *
 * The file itself is never submitted to the server — only its
 * parsed content lives in the hidden field. On invalid uploads the
 * hidden field is cleared so the server-side validator catches the
 * same problem on submit.
 */
export default class extends Controller {
    static targets = ["file", "hidden", "progress", "status"];
    static values = { validateUrl: String, checks: Array };

    async fileChanged() {
        const file = this.fileTarget.files?.[0];
        if (!file) {
            return;
        }

        let content;
        try {
            content = await file.text();
        } catch (err) {
            this.showError([err?.message || "Could not read file."]);
            return;
        }

        const total = this.checksValue.length;
        if (total === 0) {
            // No checks registered — nothing to validate; accept as-is.
            this.hiddenTarget.value = content;
            return;
        }

        this.beginProgress(total);
        this.statusTarget.textContent =
            this.statusTarget.dataset.validatingText || "Validating…";

        let passed = 0;
        for (const check of this.checksValue) {
            let result;
            try {
                const response = await fetch(this.validateUrlValue, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                    },
                    body: JSON.stringify({ json: content, check }),
                });
                result = await response.json();
            } catch (err) {
                this.endProgress();
                this.hiddenTarget.value = "";
                this.showError([err?.message || "Validation request failed."]);
                return;
            }

            if (!result.valid) {
                this.endProgress();
                this.hiddenTarget.value = "";
                this.showError(result.errors || []);
                return;
            }

            passed += 1;
            this.advanceProgress(passed, total);
        }

        // All checks passed.
        this.endProgress();
        this.hiddenTarget.value = content;
        this.statusTarget.textContent =
            this.statusTarget.dataset.validText || "Configuration is valid.";
        this.statusTarget.classList.remove("text-red-600");
        this.statusTarget.classList.add("text-primary");
    }

    beginProgress(total) {
        const progress = this.progressTarget;
        progress.classList.remove("hidden");
        progress.max = total;
        progress.value = 0;
    }

    advanceProgress(passed, total) {
        this.progressTarget.max = total;
        this.progressTarget.value = passed;
    }

    endProgress() {
        // Leave the bar at its final value; the value already
        // reflects the number of completed checks.
    }

    showError(errors) {
        const prefix =
            this.statusTarget.dataset.invalidText || "Invalid configuration:";
        const message =
            errors.length === 0 ? prefix : `${prefix} ${errors.join("; ")}`;
        this.statusTarget.textContent = message;
        this.statusTarget.classList.remove("text-primary");
        this.statusTarget.classList.add("text-red-600");
    }
}
