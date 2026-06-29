import { Controller } from "@hotwired/stimulus";

/*
 * Inline role-mutation dropdown on /admin/users.
 *
 * Mounted per-row on the wrapper around the <select> + role label +
 * aria-live feedback span. When the user picks an option, fires a
 * POST against the role endpoint, swaps the visible role label on
 * success, and shows an inline message either way.
 *
 * Values:
 *   url            — endpoint URL for this row (`/admin/users/{id}/role`).
 *   csrfToken      — CSRF token for the `admin-user-action` intent,
 *                    forwarded in the JSON body so the controller can
 *                    validate it without depending on form-data shape.
 *   successMessage — translated success message shown in the feedback
 *                    span (the actual role label comes back from the
 *                    server response).
 *
 * Targets:
 *   select   — the <select> the user manipulates.
 *   label    — the inline role label shown above the select.
 *   feedback — aria-live="polite" span used for success / error copy.
 */
export default class extends Controller {
    static targets = ["select", "label", "feedback"];
    static values = {
        url: String,
        csrfToken: String,
        successMessage: String,
    };

    async submit() {
        const role = this.selectTarget.value;
        if (!role) {
            return;
        }

        this.selectTarget.disabled = true;
        this.feedbackTarget.textContent = "";
        this.feedbackTarget.classList.remove("text-red-600");
        this.feedbackTarget.classList.add("text-text-muted");

        try {
            const response = await fetch(this.urlValue, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    role,
                    _token: this.csrfTokenValue,
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.showError(payload.message || "");
                return;
            }

            this.labelTarget.textContent = payload.label || "";
            this.feedbackTarget.textContent = this.successMessageValue;
            this.selectTarget.value = "";
        } catch (error) {
            this.showError(error?.message || "");
        } finally {
            this.selectTarget.disabled = false;
        }
    }

    showError(message) {
        this.feedbackTarget.classList.remove("text-text-muted");
        this.feedbackTarget.classList.add("text-red-600");
        this.feedbackTarget.textContent = message;
        // Restore the dropdown to its placeholder so the failed value
        // doesn't look like the new state.
        this.selectTarget.value = "";
    }
}
