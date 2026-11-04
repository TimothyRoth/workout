/**
 * Installable app plumbing:
 *
 *   - registers the service worker (/sw.js, rendered per deploy) – Chrome
 *     only offers "install" when one with a fetch handler is registered
 *   - keeps the browser's `beforeinstallprompt` event so the install card
 *     can show a real "Installieren" button; without one the card explains
 *     the platform's manual way (iOS Safari has no install API, Chrome only
 *     fires the event while the site isn't installed and its heuristics allow)
 *   - shows the offline banner while the browser reports no connection
 *
 *   <section data-install-card hidden>            the card on the front page
 *       <p data-install-hint="ios|firefox|android" hidden>
 *       <button data-install-button hidden>
 *       <button data-install-dismiss>
 *   <div data-offline-banner hidden>
 */

const DISMISS_KEY = "installDismissed";

let installPrompt = null;

const isInstalled = () => matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;

const isIos = () => {
    const ua = navigator.userAgent;
    const iPadOs = navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1;
    return (/iPhone|iPad|iPod/.test(ua) || iPadOs) && /Safari/.test(ua) && !/CriOS|FxiOS/.test(ua);
};

const isFirefox = () => /Firefox|FxiOS/.test(navigator.userAgent);

const isMobileDevice = () => {
    if (navigator.userAgentData?.mobile) return true;
    const touch = matchMedia("(pointer: coarse)").matches && navigator.maxTouchPoints > 0;
    const mobileUa = /Android|iPhone|iPad|iPod|Mobile|Tablet/i.test(navigator.userAgent);
    return mobileUa || isIos() || (touch && innerWidth < 1100);
};

const isDismissed = () => {
    try {
        return localStorage.getItem(DISMISS_KEY) === "1";
    } catch {
        return false;
    }
};

const registerServiceWorker = () => {
    if (!("serviceWorker" in navigator) || location.protocol !== "https:") return;
    addEventListener("load", () => navigator.serviceWorker.register("/sw.js").catch(() => {}));
};

const initInstallCard = () => {
    const card = document.querySelector("[data-install-card]");
    if (!card || !isMobileDevice() || isInstalled() || isDismissed()) return;

    const button = card.querySelector("[data-install-button]");
    const hints = card.querySelectorAll("[data-install-hint]");

    const showPrompt = () => {
        button.hidden = false;
        hints.forEach(hint => hint.hidden = true);
        card.hidden = false;
    };

    const showHint = name => {
        hints.forEach(hint => hint.hidden = hint.dataset.installHint !== name);
        card.hidden = false;
    };

    addEventListener("beforeinstallprompt", event => {
        event.preventDefault();
        installPrompt = event;
        showPrompt();
    });

    addEventListener("appinstalled", () => {
        installPrompt = null;
        card.hidden = true;
    });

    button.addEventListener("click", async () => {
        if (!installPrompt) return;
        installPrompt.prompt();
        const {outcome} = await installPrompt.userChoice;
        installPrompt = null;
        if (outcome === "accepted") card.hidden = true;
    });

    card.querySelector("[data-install-dismiss]").addEventListener("click", () => {
        try {
            localStorage.setItem(DISMISS_KEY, "1");
        } catch {
        }
        card.hidden = true;
    });

    if (isIos()) showHint("ios");
    else if (isFirefox()) showHint("firefox");
    else showHint("android");
};

const initOfflineBanner = () => {
    const banner = document.querySelector("[data-offline-banner]");
    if (!banner) return;
    const update = () => banner.hidden = navigator.onLine;
    addEventListener("online", update);
    addEventListener("offline", update);
    update();
};

export const initPwa = () => {
    registerServiceWorker();
    initInstallCard();
    initOfflineBanner();
};
