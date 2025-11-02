document.addEventListener("DOMContentLoaded", () => {
    editContainer();
});

const editContainer = () => {
    const buttons = document.querySelectorAll(".editButton");
    buttons.forEach(button => {
        button.addEventListener("click", () => {
            const target = button.dataset.target;
            const container = document.getElementById(target);

            if (container) {
                container.classList.add("active");
                container.querySelector(".close").addEventListener("click", () => {
                    container.classList.remove("active");
                })
            }
        })
    })
}