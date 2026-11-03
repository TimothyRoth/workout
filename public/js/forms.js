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
};

export const initUnitSelects = () => {
    document.querySelectorAll("[data-unit-select]").forEach(applyUnit);
    document.addEventListener("change", event => {
        if (event.target.matches("[data-unit-select]")) applyUnit(event.target);
    });
};
