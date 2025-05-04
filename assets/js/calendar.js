document.addEventListener('DOMContentLoaded', function () {
    // Handle calendar switcher
    const calendarSwitch = document.getElementById('calendarSwitch');
    if (calendarSwitch) {
        calendarSwitch.addEventListener('change', function () {
            // Add loading indicator
            const spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm me-2';
            spinner.setAttribute('role', 'status');
            spinner.setAttribute('aria-hidden', 'true');
            this.parentNode.insertBefore(spinner, this);

            // Submit the form when the switch is toggled
            this.form.submit();
        });
    }

    // Initialize Ethiopian calendar display if enabled
    const ethiopianCalendarEnabled = document.body.classList.contains('ethiopian-calendar');

    if (ethiopianCalendarEnabled) {
        // Convert dates on the page
        convertDatesToEthiopian();
    }
});

function convertDatesToEthiopian() {
    // Find all date elements and convert them
    document.querySelectorAll('.date-display').forEach(el => {
        const gregorianDate = el.getAttribute('data-date');
        if (gregorianDate) {
            // Send AJAX request to convert date
            fetch(`${BASE_URL}/api/convert-date?date=${gregorianDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        el.textContent = data.ethiopianDate;
                    }
                })
                .catch(error => {
                    console.error('Error converting date:', error);
                });
        }
    });
}