// SweetAlert library inclusion
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("weddingForm");

    form.addEventListener("submit", function(event) {
        event.preventDefault();

        // Gather form data
        const formData = new FormData(form);

        // Send data via AJAX
        fetch("php/process_form.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Display SweetAlert success message
                Swal.fire({
                    title: "Success",
                    text: "Your registration was successfully submitted. We will contact you shortly.",
                    icon: "success",
                    confirmButtonText: "OK"
                });
                form.reset();
            } else {
                // Handle failure (display error message)
                Swal.fire({
                    title: "Error",
                    text: "There was an issue submitting your registration. Please try again.",
                    icon: "error",
                    confirmButtonText: "OK"
                });
            }
        })
        .catch(error => {
            console.error("Error:", error);
            Swal.fire({
                title: "Error",
                text: "An unexpected error occurred. Please try again later.",
                icon: "error",
                confirmButtonText: "OK"
            });
        });
    });
});
    