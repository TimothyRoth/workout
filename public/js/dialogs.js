/**
 * Bottom sheets built on the native <dialog> element.
 *
 *   <button data-dialog="edit-set-3">   opens #edit-set-3 as a modal
 *   <button data-close>                 closes the enclosing dialog
 *   <form data-confirm="...">           asks before submitting
 */

const LOCK_CLASS = "is-locked";

const openDialog = dialog => {
    if (dialog.open) return;
    dialog.showModal();
    document.body.classList.add(LOCK_CLASS);
    dialog.querySelector("input:not([type=hidden])")?.focus({preventScroll: true});
};

export const initDialogs = () => {
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
