// script.js

// Wait for the DOM to fully load
document.addEventListener("DOMContentLoaded", function () {
    console.log("Footer script loaded.");

    // Example: Smooth scroll to top functionality
    const scrollToTopButton = document.getElementById("scrollToTop");
    if (scrollToTopButton) {
        scrollToTopButton.addEventListener("click", function () {
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        });
    }

    // Example: Handle footer form submission
    const footerForm = document.getElementById("footerForm");
    if (footerForm) {
        footerForm.addEventListener("submit", function (event) {
            event.preventDefault();
            const formData = new FormData(footerForm);
            console.log("Form submitted:", Object.fromEntries(formData.entries()));
            alert("Thank you for your submission!");
        });
    }


    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Select2 for multi-select dropdown
        const userSelect = document.querySelector('select[name="user_ids[]"]');
        if (userSelect) {
            $(userSelect).select2({
                placeholder: "Select users",
                allowClear: true
            });
        }
    });

    // Initialize Select2 for dropdowns
    $(document).ready(function () {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: 'auto',
            dropdownAutoWidth: true
        });
    });

    // Better mobile menu handling
    document.addEventListener('DOMContentLoaded', function () {
        var navbarCollapse = document.getElementById('navbarContent');
        navbarCollapse.addEventListener('hidden.bs.collapse', function () {
            var openDropdowns = navbarCollapse.querySelectorAll('.dropdown-menu.show');
            openDropdowns.forEach(function (dropdown) {
                dropdown.classList.remove('show');
            });
        });
    });

});