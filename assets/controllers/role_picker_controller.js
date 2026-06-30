import { Controller } from "@hotwired/stimulus";

/*
 * Inline role-mutation dropdown on /admin/users.
 *
 * Mounted per-row on the wrapper around the <select> + aria-live
 * feedback span (in the "Skift rolle" column). The role label
 * itself lives in the sibling "Rolle" cell, identified by a
 * `data-role-picker-row-label` attribute on the same <tr>. The
 * controller walks up to the row and back down to find that span
 * so it can swap its text on success — Stimulus targets can't
 * cross cell boundaries within a <tr>, but a single DOM lookup
 * scoped to `closest('tr')` works fine.
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
 *   feedback — aria-live="polite" span used for success / error copy.
 */
export default class extends Controller {
    static targets = ["select", "feedback"];
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

            const labelEl = this.rowLabelElement();
            if (labelEl) {
                labelEl.textContent = payload.label || "";
            }
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

    // Look up the label cell in the sibling "Rolle" column of the
    // same row. Returns null if the row markup ever drifts and the
    // attribute hook is missing — the controller stays defensive
    // rather than throwing.
    rowLabelElement() {
        const row = this.element.closest("tr");
        if (!row) {
            return null;
        }
        return row.querySelector("[data-role-picker-row-label]");
    }
}
