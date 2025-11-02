document.addEventListener("DOMContentLoaded", () => {
    editContainer();
    deleteAction();
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

                const saveButton = container.querySelector(".saveButton");
                console.log(saveButton.classList.contains("active"))
                if(saveButton && !saveButton.classList.contains("active")) {
                    const inputFields = container.querySelectorAll("input");
                    inputFields.forEach(input => {
                        input.addEventListener("change", () => {
                            saveButton.classList.add("active");
                        })
                    })
                }
            }
        })
    })
}

const deleteAction = () => {
    const buttons = document.querySelectorAll(".deleteButton");

    buttons.forEach(button => {
        button.addEventListener("click", (event) => {
            event.preventDefault(); // stop the form from submitting immediately

            // Simple browser confirmation dialog
            const confirmed = confirm("Das Löschen ist unwiderruflich. Bist du sicher?");

            if (confirmed) {
                // If the button is inside a form, submit it programmatically
                const form = button.closest("form");
                if (form) {
                    form.submit();
                }
            }
        });
    });
};