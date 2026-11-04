/**
 * Progress charts on the logs page: one single-series line chart per
 * <div class="chart" data-chart='{"title","unit","points":[{"d","v","label"}]}'>,
 * rendered as inline SVG when its <details> opens (the page can hold many).
 * Hover/touch snaps a crosshair to the nearest session and shows the value,
 * the set line and the date; the history table next to it is the table view.
 */

const W = 320;
const H = 140;
const PAD = {top: 14, right: 14, bottom: 22, left: 40};

const svgEl = (name, attrs = {}) => {
    const el = document.createElementNS("http://www.w3.org/2000/svg", name);
    Object.entries(attrs).forEach(([key, value]) => el.setAttribute(key, String(value)));
    return el;
};

const formatValue = value => (Number.isInteger(value) ? String(value) : String(Math.round(value * 10) / 10).replace(".", ","));

const formatDate = utc => {
    const date = new Date(utc.replace(" ", "T") + "Z");
    return Number.isNaN(date.getTime()) ? utc : date.toLocaleDateString("de-DE", {day: "2-digit", month: "2-digit", year: "2-digit"});
};

// 3 clean ticks between a padded min and max (a non-zero baseline keeps small changes visible)
const niceTicks = (min, max) => {
    const span = Math.max(max - min, 1) || 1;
    const rawStep = span / 2;
    const magnitude = 10 ** Math.floor(Math.log10(rawStep));
    const step = [1, 2, 2.5, 5, 10].map(f => f * magnitude).find(s => s >= rawStep) ?? magnitude * 10;
    const lo = Math.floor(min / step) * step;
    const hi = Math.ceil(max / step) * step;
    const ticks = [];
    for (let v = lo; v <= hi + step / 2; v += step) ticks.push(Math.round(v * 1000) / 1000);
    return {lo, hi: Math.max(hi, lo + step), ticks};
};

const render = container => {
    if (container.dataset.rendered) return;
    container.dataset.rendered = "1";

    let spec;
    try {
        spec = JSON.parse(container.dataset.chart);
    } catch {
        return;
    }
    const points = (spec.points ?? []).filter(p => Number.isFinite(Number(p.v)));
    if (points.length < 2) return;

    const values = points.map(p => Number(p.v));
    const {lo, hi, ticks} = niceTicks(Math.min(...values) * 0.9, Math.max(...values) * 1.05);
    const plotW = W - PAD.left - PAD.right;
    const plotH = H - PAD.top - PAD.bottom;
    const x = i => PAD.left + (points.length === 1 ? plotW / 2 : (i / (points.length - 1)) * plotW);
    const y = v => PAD.top + plotH - ((v - lo) / (hi - lo)) * plotH;
    const unit = spec.unit ? ` ${spec.unit}` : "";

    const title = document.createElement("div");
    title.className = "chart-title";
    title.textContent = spec.title ?? "";
    container.append(title);

    const svg = svgEl("svg", {viewBox: `0 0 ${W} ${H}`, role: "img", "aria-label": `${spec.title}: ${points.length} Trainings, zuletzt ${formatValue(values.at(-1))}${unit}`});

    ticks.forEach(tick => {
        svg.append(svgEl("line", {class: "grid", x1: PAD.left, x2: W - PAD.right, y1: y(tick), y2: y(tick)}));
        const label = svgEl("text", {class: "axis-text", x: PAD.left - 6, y: y(tick) + 4, "text-anchor": "end"});
        label.textContent = formatValue(tick);
        svg.append(label);
    });

    const first = svgEl("text", {class: "axis-text", x: PAD.left, y: H - 6});
    first.textContent = formatDate(points[0].d);
    const last = svgEl("text", {class: "axis-text", x: W - PAD.right, y: H - 6, "text-anchor": "end"});
    last.textContent = formatDate(points.at(-1).d);
    svg.append(first, last);

    const path = points.map((p, i) => `${i === 0 ? "M" : "L"}${x(i).toFixed(1)},${y(Number(p.v)).toFixed(1)}`).join(" ");
    svg.append(svgEl("path", {class: "area", d: `${path} L${x(points.length - 1).toFixed(1)},${(PAD.top + plotH).toFixed(1)} L${x(0).toFixed(1)},${(PAD.top + plotH).toFixed(1)} Z`}));
    svg.append(svgEl("path", {class: "line", d: path}));

    const crosshair = svgEl("line", {class: "crosshair", y1: PAD.top, y2: PAD.top + plotH, visibility: "hidden"});
    svg.append(crosshair);

    const markers = points.map((p, i) => svgEl("circle", {class: "marker", cx: x(i), cy: y(Number(p.v)), r: 4}));
    markers.forEach(marker => svg.append(marker));

    // direct label on the endpoint only
    const endLabel = svgEl("text", {class: "end-label", x: x(points.length - 1), y: y(values.at(-1)) - 9, "text-anchor": "end"});
    endLabel.textContent = formatValue(values.at(-1)) + unit;
    svg.append(endLabel);

    const tooltip = document.createElement("div");
    tooltip.className = "chart-tooltip";
    tooltip.hidden = true;
    const tooltipValue = document.createElement("b");
    const tooltipText = document.createTextNode("");
    tooltip.append(tooltipValue, tooltipText);

    container.append(svg, tooltip);

    let active = -1;
    const show = index => {
        if (index === active) return;
        active = index;
        markers.forEach((marker, i) => marker.classList.toggle("is-active", i === index));
        const p = points[index];
        crosshair.setAttribute("x1", x(index));
        crosshair.setAttribute("x2", x(index));
        crosshair.setAttribute("visibility", "visible");
        tooltipValue.textContent = formatValue(Number(p.v)) + unit;
        tooltipText.textContent = `${p.label ? `${p.label} · ` : ""}${formatDate(p.d)}`;
        tooltip.hidden = false;
        const ratio = index / (points.length - 1);
        tooltip.style.left = ratio > 0.6 ? "auto" : `${(x(index) / W) * 100}%`;
        tooltip.style.right = ratio > 0.6 ? `${((W - x(index)) / W) * 100}%` : "auto";
    };
    const hide = () => {
        active = -1;
        markers.forEach(marker => marker.classList.remove("is-active"));
        crosshair.setAttribute("visibility", "hidden");
        tooltip.hidden = true;
    };

    svg.addEventListener("pointermove", event => {
        const rect = svg.getBoundingClientRect();
        const px = ((event.clientX - rect.left) / rect.width) * W;
        let nearest = 0;
        points.forEach((_, i) => { if (Math.abs(x(i) - px) < Math.abs(x(nearest) - px)) nearest = i; });
        show(nearest);
    });
    svg.addEventListener("pointerleave", hide);
    svg.addEventListener("pointerdown", event => event.pointerType === "touch" && setTimeout(hide, 2500));
};

export const initCharts = () => {
    const charts = document.querySelectorAll("[data-chart]");
    if (charts.length === 0) return;

    const renderOpen = () => charts.forEach(chart => {
        if (!chart.closest("details:not([open])")) render(chart);
    });
    document.querySelectorAll("details").forEach(details => details.addEventListener("toggle", renderOpen));
    renderOpen();
};
