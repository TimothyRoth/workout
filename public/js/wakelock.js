/**
 * Keeps the screen on while a session is running. Re-acquired when the
 * page becomes visible again, because the lock is released on hide.
 */

let wakeLock = null;
let watching = false;

const request = async () => {
    if (!("wakeLock" in navigator) || wakeLock !== null) return;
    try {
        wakeLock = await navigator.wakeLock.request("screen");
        wakeLock.addEventListener("release", () => { wakeLock = null; });
    } catch {
        console.info("Wake lock not available.");
    }
};

export const keepScreenOn = async () => {
    if (!watching) {
        watching = true;
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "visible") request();
        });
    }
    await request();
};
