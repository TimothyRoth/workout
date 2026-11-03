/**
 * Minus/plus steppers around numeric inputs, so the usual small adjustments
 * never open the phone keyboard. The input stays editable for direct entry.
 *
 *   <div class="stepper">
 *       <button type="button" data-step="-1">−</button>
 *       <input type="number" data-step="2.5" min="0" max="100">
 *       <button type="button" data-step="1">+</button>
 *   </div>
 *
 * The input's data-step is the increment (default 1); min/max clamp the
 * result. Holding a button repeats the step.
 */

const HOLD_DELAY_MS = 450;
const HOLD_REPEAT_MS = 110;

const decimalsOf = value => {
    const text = String(value);
    const index = text.indexOf(".");
    return index === -1 ? 0 : text.length - index - 1;
};

const step = (input, direction) => {
    const size = Number(input.dataset.step) || 1;
    const current = input.value.trim() === "" ? 0 : Number(input.value.replace(",", "."));
    const base = Number.isFinite(current) ? current : 0;
    const decimals = Math.max(decimalsOf(size), decimalsOf(base));
    let next = Number((base + direction * size).toFixed(decimals));

    if (input.min !== "" && next < Number(input.min)) next = Number(input.min);
    if (input.max !== "" && next > Number(input.max)) next = Number(input.max);

    input.value = String(next);
    input.dispatchEvent(new Event("input", {bubbles: true}));
    input.dispatchEvent(new Event("change", {bubbles: true}));
};

export const initSteppers = () => {
    let holdTimer = null;

    const stop = () => {
        clearTimeout(holdTimer);
        clearInterval(holdTimer);
        holdTimer = null;
    };

    document.addEventListener("pointerdown", event => {
        const button = event.target.closest(".stepper [data-step]");
        if (!button || event.button !== 0) return;
        const input = button.closest(".stepper")?.querySelector("input");
        if (!input || input.disabled) return;

        event.preventDefault(); // keep focus (and the keyboard) where it is
        const direction = Math.sign(Number(button.dataset.step)) || 1;
        step(input, direction);

        stop();
        holdTimer = setTimeout(() => {
            holdTimer = setInterval(() => step(input, direction), HOLD_REPEAT_MS);
        }, HOLD_DELAY_MS);
    });

    ["pointerup", "pointercancel", "pointerleave"].forEach(type => document.addEventListener(type, stop, true));
    document.addEventListener("contextmenu", event => {
        if (event.target.closest(".stepper [data-step]")) event.preventDefault();
    });
};
