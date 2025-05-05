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

    function convertToEthiopian(dateStr) {
        const date = new Date(dateStr);
        const ec = new EthiopianCalendar(date);
        return {
            date: ec.GetECDate('Y-m-d'),
            year: ec.EC_year,
            month: ec.EC_month,
            day: ec.EC_day
        };
    }

    function convertToGregorian(ethDateStr) {
        const parts = ethDateStr.split('-');
        const ecYear = parseInt(parts[0]);
        const ecMonth = parseInt(parts[1]);
        const ecDay = parseInt(parts[2]);

        const ec = new EthiopianCalendar(new Date());
        const gcDate = ec.ethiopianToGregorian(ecYear, ecMonth, ecDay);

        // Format as YYYY-MM-DD
        const gcDateStr = `${gcDate.year}-${gcDate.month.toString().padStart(2, '0')}-${gcDate.day.toString().padStart(2, '0')}`;
        return {
            date: gcDateStr,
            year: gcDate.year,
            month: gcDate.month,
            day: gcDate.day
        };
    }

    // Helper function to update all date displays on the page
    function updateDateDisplays() {
        document.querySelectorAll('[data-date]').forEach(element => {
            const dateStr = element.getAttribute('data-date');
            if (document.body.classList.contains('ethiopian-calendar')) {
                const ethDate = convertToEthiopian(dateStr);
                element.textContent = ethDate.date;
                element.classList.add('ethiopian-text');
            } else {
                // For Gregorian, just display as-is
                element.textContent = dateStr;
                element.classList.remove('ethiopian-text');
            }
        });
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function () {
        updateDateDisplays();

        // Update when calendar switch is toggled
        const calendarSwitch = document.getElementById('calendarSwitch');
        if (calendarSwitch) {
            calendarSwitch.addEventListener('change', function () {
                updateDateDisplays();
            });
        }
    });

});