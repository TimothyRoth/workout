/**
 * Set forms: the load input follows the chosen unit. Bodyweight needs no
 * number, so its field is hidden and disabled (disabled inputs are not
 * submitted, and the server ignores load for bodyweight anyway).
 */

const applyUnit = select => {
    const form = select.closest("form");
    const field = form?.querySelector("[data-load-field]");
    if (!field) return;

    const option = select.selectedOptions[0];
    const label = option?.dataset.loadLabel || "";
    const input = field.querySelector("input");

    field.hidden = label === "";
    input.disabled = label === "";
    input.required = label !== "";
    if (label !== "") field.querySelector("[data-load-label]").textContent = label;
    input.dataset.step = option?.dataset.loadStep || "1";
};

export const initUnitSelects = () => {
    document.querySelectorAll("[data-unit-select]").forEach(applyUnit);
    document.addEventListener("change", event => {
        if (event.target.matches("[data-unit-select]")) applyUnit(event.target);
    });
};

/**
 * "Passwort anzeigen" button next to a password field:
 *   <button data-password-toggle="<input id>" aria-pressed="false">
 */
export const initPasswordToggles = () => {
    document.querySelectorAll("[data-password-toggle]").forEach(button => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        button.addEventListener("click", () => {
            const show = input.type === "password";
            input.type = show ? "text" : "password";
            button.setAttribute("aria-pressed", String(show));
            button.setAttribute("aria-label", show ? "Passwort verbergen" : "Passwort anzeigen");
        });
    });
};
