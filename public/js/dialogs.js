/**
 * Bottom sheets built on the native <dialog> element.
 *
 *   <button data-dialog="edit-set-3">   opens #edit-set-3 as a modal
 *   <button data-close>                 closes the enclosing dialog
 *   <form data-confirm="...">           asks before submitting
 *
 * The phone keyboard overlays the page (see the viewport meta tag); it never
 * resizes the layout. Instead the visible keyboard height is published as
 * --keyboard on <html>, and the sheets, the session overlay and the rest bar
 * lift themselves above it in CSS.
 */

const LOCK_CLASS = "is-locked";

// Touch devices get no auto-focus: the keyboard would pop up before the
// sheet is even visible. The user taps the field they want to change.
const touchScreen = matchMedia("(hover: none)").matches;

const openDialog = dialog => {
    if (dialog.open) return;
    dialog.showModal();
    document.body.classList.add(LOCK_CLASS);
    if (!touchScreen) dialog.querySelector("input:not([type=hidden])")?.focus({preventScroll: true});
};

const initKeyboardTracking = () => {
    const viewport = window.visualViewport;
    if (!viewport) return;

    let keyboard = 0;
    const update = () => {
        // Part of the layout viewport hidden under the keyboard (0 when closed).
        const hidden = Math.round(window.innerHeight - viewport.height - viewport.offsetTop);
        const next = hidden > 80 ? hidden : 0; // ignore browser-chrome jitter
        if (next === keyboard) return;
        keyboard = next;
        document.documentElement.style.setProperty("--keyboard", `${keyboard}px`);

        const focused = document.activeElement;
        if (keyboard > 0 && focused instanceof HTMLInputElement) {
            // Wait for the lift transition, then make sure the field is visible.
            setTimeout(() => focused.scrollIntoView({block: "center", behavior: "smooth"}), 220);
        }
    };

    viewport.addEventListener("resize", update);
    viewport.addEventListener("scroll", update);
};

export const initDialogs = () => {
    initKeyboardTracking();

    document.addEventListener("click", event => {
        const opener = event.target.closest("[data-dialog]");
        if (opener) {
            const dialog = document.getElementById(opener.dataset.dialog);
            if (dialog instanceof HTMLDialogElement) openDialog(dialog);
            return;
        }

        const closer = event.target.closest("[data-close]");
        if (closer) {
            closer.closest("dialog")?.close();
            return;
        }

        // A click on the dialog element itself (not its content) is the backdrop.
        if (event.target instanceof HTMLDialogElement && event.target.open) {
            event.target.close();
        }
    });

    document.querySelectorAll("dialog.sheet").forEach(dialog => {
        dialog.addEventListener("close", () => {
            if (!document.querySelector("dialog[open]")) {
                document.body.classList.remove(LOCK_CLASS);
            }
        });
    });
};

export const initConfirmations = () => {
    document.addEventListener("submit", event => {
        const message = event.target.dataset?.confirm;
        if (message && !confirm(message)) {
            event.preventDefault();
        }
    });
};
