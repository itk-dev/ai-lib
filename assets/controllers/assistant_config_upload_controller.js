import { Controller } from "@hotwired/stimulus";

/*
 * Upload + validate the OpenWebUI config JSON for the assistant
 * create form.
 *
 * Mounted on the form (`data-controller="assistant-config-upload"`).
 * The file input is the `file` target; selecting a file kicks off:
 *
 *   1. Read the file contents via FileReader.
 *   2. Show the `progress` bar and animate toward 90% over ~2s
 *      (matches the temporary server-side delay).
 *   3. POST the JSON content to the validate URL given by the
 *      `validate-url` value. Snap progress to 100% on response.
 *   4. Update the `status` text with the result and copy the raw
 *      JSON string into the `hidden` form field on success.
 *
 * The file itself is never submitted to the server — only its
 * parsed content lives in the hidden field. On invalid uploads the
 * hidden field is cleared so the server-side validator catches the
 * same problem on submit.
 */
export default class extends Controller {
    static targets = ["file", "hidden", "progress", "status"];
    static values = { validateUrl: String };

    connect() {
        this.progressTimer = null;
    }

    disconnect() {
        this.stopProgress();
    }

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

        this.startProgress();
        this.statusTarget.textContent =
            this.statusTarget.dataset.validatingText || "Validating…";

        try {
            const response = await fetch(this.validateUrlValue, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify({ json: content }),
            });

            const result = await response.json();
            this.stopProgress(true);

            if (result.valid) {
                this.hiddenTarget.value = content;
                this.statusTarget.textContent =
                    this.statusTarget.dataset.validText ||
                    "Configuration is valid.";
                this.statusTarget.classList.remove("text-red-600");
                this.statusTarget.classList.add("text-primary");
            } else {
                this.hiddenTarget.value = "";
                this.showError(result.errors || []);
            }
        } catch (err) {
            this.stopProgress(true);
            this.showError([err?.message || "Validation request failed."]);
        }
    }

    startProgress() {
        const progress = this.progressTarget;
        progress.classList.remove("hidden");
        progress.value = 0;

        const start = Date.now();
        const duration = 2000;
        this.progressTimer = window.setInterval(() => {
            const elapsed = Date.now() - start;
            const pct = Math.min(90, (elapsed / duration) * 90);
            progress.value = pct;
        }, 50);
    }

    stopProgress(complete = false) {
        if (this.progressTimer) {
            window.clearInterval(this.progressTimer);
            this.progressTimer = null;
        }
        if (complete) {
            this.progressTarget.value = 100;
        }
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
