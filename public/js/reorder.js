/**
 * Drag-and-drop ordering with grip handles, on Pointer Events (no library).
 *
 *   <div data-sortable="exercises" data-parent-id="7">
 *       <details data-sortable-item data-exercise-id="3">
 *           <summary>… <span data-drag-handle>⋮⋮</span> …</summary>
 *   <table data-sortable="sets" data-parent-id="3">
 *       <tr data-sortable-item data-set-id="12"><td data-drag-handle>…
 *
 * Only the handle starts a drag (the rest of a row keeps scrolling and
 * tapping). Open exercise cards collapse while an exercise is dragged. The dragged item follows the finger with a transform and is
 * moved in the DOM whenever it passes the middle of a neighbour. On drop
 * the new id order is posted to /api/reorder; if that fails the page
 * reloads so the screen never lies about the saved order.
 */

const EDGE = 72;          // px from the viewport edge where auto-scroll kicks in
const SCROLL_SPEED = 14;  // px per frame at the edge

const itemsOf = container => [...container.querySelectorAll("[data-sortable-item]")].filter(item => item.closest("[data-sortable]") === container);

const idOf = item => item.dataset.exerciseId ?? item.dataset.setId;

const save = async (container, ids) => {
    try {
        const response = await fetch("/api/reorder", {
            method: "POST",
            headers: {"Content-Type": "application/json", "Accept": "application/json"},
            body: JSON.stringify({type: container.dataset.sortable, parent_id: Number(container.dataset.parentId), ids: ids.map(Number)}),
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
    } catch {
        alert("Die Reihenfolge konnte nicht gespeichert werden. Die Seite wird neu geladen.");
        location.reload();
    }
};

/** Set rows carry their number and the "copy last set" form points at the last row. */
const renumberSets = container => {
    itemsOf(container).forEach((row, i) => {
        const number = row.querySelector(".num span");
        if (number) number.textContent = String(i + 1);
    });
    const last = itemsOf(container).at(-1);
    const copyForm = container.closest(".exercise")?.querySelector('.exercise-actions form[action="/duplicateSet"] input[name="set_id"]');
    if (last && copyForm) copyForm.value = idOf(last);
};

export const initReorder = () => {
    // The handle is never a tap target: swallow the click so a <summary> does not toggle after a drag.
    document.addEventListener("click", event => {
        if (event.target.closest("[data-drag-handle]")) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    document.addEventListener("pointerdown", event => {
        const handle = event.target.closest("[data-drag-handle]");
        if (!handle || !event.isPrimary || event.button !== 0) return;

        const item = handle.closest("[data-sortable-item]");
        const container = item?.closest("[data-sortable]");
        if (!item || !container || itemsOf(container).length < 2) return;

        event.preventDefault();
        try {
            handle.setPointerCapture(event.pointerId);
        } catch {
        }

        const before = itemsOf(container).map(idOf);
        const startPointerY = event.clientY + scrollY;
        const startTop = item.getBoundingClientRect().top + scrollY;

        // Exercises are dragged as compact rows: collapse every open card for the
        // duration of the drag (the item keeps floating under the finger) and
        // reopen them afterwards.
        const collapsed = itemsOf(container).filter(other => other instanceof HTMLDetailsElement && other.open);
        collapsed.forEach(other => other.open = false);
        let pointerY = startPointerY;
        let lastClientY = event.clientY;
        let frame = 0;

        item.classList.add("is-dragging");

        const naturalTop = () => {
            const transform = item.style.transform;
            item.style.transform = "";
            const top = item.getBoundingClientRect().top + scrollY;
            item.style.transform = transform;
            return top;
        };

        const update = () => {
            const base = naturalTop();
            item.style.transform = `translateY(${pointerY - startPointerY - (base + 0 - startTop)}px)`;

            const items = itemsOf(container);
            const index = items.indexOf(item);
            const height = item.offsetHeight;
            const center = base + (pointerY - startPointerY - (base - startTop)) + height / 2;

            const prev = items[index - 1];
            const next = items[index + 1];
            if (prev) {
                const rect = prev.getBoundingClientRect();
                if (center < rect.top + scrollY + rect.height / 2) {
                    prev.before(item);
                    return update();
                }
            }
            if (next) {
                const rect = next.getBoundingClientRect();
                if (center > rect.top + scrollY + rect.height / 2) {
                    next.after(item);
                    return update();
                }
            }
        };

        const autoScroll = () => {
            const top = lastClientY - EDGE;
            const bottom = innerHeight - EDGE - lastClientY;
            let delta = 0;
            if (top < 0) delta = Math.max(-SCROLL_SPEED, top / EDGE * SCROLL_SPEED);
            else if (bottom < 0) delta = Math.min(SCROLL_SPEED, -bottom / EDGE * SCROLL_SPEED);
            if (delta !== 0) {
                const beforeScroll = scrollY;
                scrollBy(0, delta);
                pointerY += scrollY - beforeScroll;
                update();
            }
            frame = requestAnimationFrame(autoScroll);
        };

        const move = moveEvent => {
            if (moveEvent.pointerId !== event.pointerId) return;
            lastClientY = moveEvent.clientY;
            pointerY = moveEvent.clientY + scrollY;
            update();
        };

        const finish = endEvent => {
            if (endEvent.pointerId !== event.pointerId) return;
            cancelAnimationFrame(frame);
            handle.removeEventListener("pointermove", move);
            handle.removeEventListener("pointerup", finish);
            handle.removeEventListener("pointercancel", finish);
            item.style.transform = "";
            item.classList.remove("is-dragging");
            collapsed.forEach(other => other.open = true);

            const after = itemsOf(container).map(idOf);
            if (after.join() === before.join()) return;
            if (container.dataset.sortable === "sets") renumberSets(container);
            save(container, after);
        };

        handle.addEventListener("pointermove", move);
        handle.addEventListener("pointerup", finish);
        handle.addEventListener("pointercancel", finish);
        frame = requestAnimationFrame(autoScroll);
    });
};
