/** Training calendar: tapping a day prints its date and workouts into the caption. */
export const initCalendar = () => {
    const caption = document.querySelector("[data-cal-caption]");
    if (!caption) return;
    document.querySelectorAll("[data-cal-day]").forEach(day => day.addEventListener("click", () => {
        document.querySelectorAll(".cal-day.is-selected").forEach(other => other.classList.remove("is-selected"));
        day.classList.add("is-selected");
        caption.textContent = day.dataset.calDay;
    }));
};
