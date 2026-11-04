/**
 * Plate calculator: which plates go on each side of the bar for a weight.
 *
 *   <button data-dialog="plate-calculator" data-plates-for="cur-load">
 *
 * dialogs.js opens the sheet; this module fills it from the referenced input,
 * keeps the chosen bar in localStorage and recalculates on every change.
 */

const PLATES = [25, 20, 15, 10, 5, 2.5, 1.25, 0.5];
const BAR_KEY = "plateBar";

const $ = id => document.getElementById(id);

const formatNumber = value => String(Math.round(value * 100) / 100).replace(".", ",");

const perSide = (total, bar) => {
    let remaining = Math.max(0, (total - bar) / 2);
    const plates = [];
    for (const plate of PLATES) {
        while (remaining >= plate - 1e-9) {
            plates.push(plate);
            remaining -= plate;
        }
    }
    return {plates, rest: Math.round(remaining * 100) / 100};
};

const render = () => {
    const total = parseFloat(String($("plate-weight").value).replace(",", ".")) || 0;
    const bar = parseFloat($("plate-bar").value) || 0;
    const result = $("plate-result");
    const rest = $("plate-rest");
    result.textContent = "";
    rest.textContent = "";

    if (total <= 0) return;
    if (total < bar) {
        rest.textContent = `Weniger als die Stange (${formatNumber(bar)} kg).`;
        return;
    }
    const {plates, rest: leftover} = perSide(total, bar);
    if (plates.length === 0) {
        rest.textContent = "Nur die Stange.";
        return;
    }
    plates.forEach(plate => {
        const chip = document.createElement("span");
        chip.className = `plate plate-${String(plate).replace(".", "-")}`;
        chip.textContent = formatNumber(plate);
        result.append(chip);
    });
    const perSideTotal = plates.reduce((sum, plate) => sum + plate, 0);
    rest.textContent = `Pro Seite ${formatNumber(perSideTotal)} kg` + (leftover > 0 ? ` · ${formatNumber(leftover * 2)} kg nicht abbildbar` : "");
};

export const initPlateCalculator = () => {
    const dialog = $("plate-calculator");
    if (!dialog) return;

    try {
        const stored = localStorage.getItem(BAR_KEY);
        if (stored) $("plate-bar").value = stored;
    } catch {
    }

    document.addEventListener("click", event => {
        const opener = event.target.closest("[data-plates-for]");
        if (!opener) return;
        const source = $(opener.dataset.platesFor);
        if (source) $("plate-weight").value = source.value;
        render();
    });

    $("plate-weight").addEventListener("input", render);
    $("plate-bar").addEventListener("change", () => {
        try {
            localStorage.setItem(BAR_KEY, $("plate-bar").value);
        } catch {
        }
        render();
    });
};
