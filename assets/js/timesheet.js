document.addEventListener('DOMContentLoaded', function () {
    // Month/year selector change
    document.getElementById('month-selector')?.addEventListener('change', function () {
        const month = this.value;
        const year = document.getElementById('year-selector').value;
        window.location.href = `timesheet.php?month=${month}&year=${year}`;
    });

    document.getElementById('year-selector')?.addEventListener('change', function () {
        const year = this.value;
        const month = document.getElementById('month-selector').value;
        window.location.href = `timesheet.php?month=${month}&year=${year}`;
    });

    // Calculate totals when hours change
    document.querySelectorAll('.hours-input').forEach(input => {
        input.addEventListener('change', calculateTotals);
        input.addEventListener('keyup', calculateTotals);
    });

    // Initial calculation
    calculateTotals();

    // Handle Ethiopian calendar conversion if enabled
    if (document.body.classList.contains('ethiopian-calendar')) {
        convertDatesToEthiopian();
    }

    function calculateTotals() {
        document.querySelectorAll('tbody tr').forEach(row => {
            let total = 0;
            const inputs = row.querySelectorAll('.hours-input');

            inputs.forEach(input => {
                const value = parseFloat(input.value) || 0;
                total += value;
            });

            // Update total hours
            const totalCell = row.querySelector('.total-hours');
            if (totalCell) {
                totalCell.textContent = total.toFixed(1);
            }

            // Update percentage
            const allocated = parseFloat(row.querySelector('td:nth-last-child(2)')?.textContent) || 0;
            const percentageCell = row.querySelector('.percentage');

            if (percentageCell) {
                const percentage = allocated > 0 ? (total / allocated * 100).toFixed(1) : 0;
                percentageCell.textContent = `${percentage}%`;

                // Highlight if over allocated
                percentageCell.classList.toggle('text-danger', total > allocated);
                percentageCell.classList.toggle('text-success', total <= allocated);
            }
        });
    }

    function convertDatesToEthiopian() {
        // Convert all date displays to Ethiopian calendar
        document.querySelectorAll('.date-display').forEach(element => {
            const dateStr = element.textContent.trim();
            if (dateStr && window.EthiopianCalendar) {
                try {
                    const dateParts = dateStr.split(/[-/]/);
                    if (dateParts.length === 3) {
                        const date = new Date(`${dateParts[0]}-${dateParts[1]}-${dateParts[2]}`);
                        const ec = new EthiopianCalendar(date);
                        element.textContent = ec.GetECDate('Y-m-d');
                        element.classList.add('ethiopian-text');
                    }
                } catch (e) {
                    console.error('Date conversion error:', e);
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Month/year selector change
        document.getElementById('month-selector')?.addEventListener('change', function () {
            const month = this.value;
            const year = document.getElementById('year-selector').value;
            window.location.href = `timesheet.php?month=${month}&year=${year}`;
        });

        document.getElementById('year-selector')?.addEventListener('change', function () {
            const year = this.value;
            const month = document.getElementById('month-selector').value;
            window.location.href = `timesheet.php?month=${month}&year=${year}`;
        });

        // Calculate totals when hours change
        document.querySelectorAll('.hours-input').forEach(input => {
            input.addEventListener('change', calculateTotals);
            input.addEventListener('keyup', calculateTotals);
        });

        // Initial calculation
        calculateTotals();
    });

    function calculateTotals() {
        document.querySelectorAll('tbody tr').forEach(row => {
            let total = 0;
            const inputs = row.querySelectorAll('.hours-input');

            inputs.forEach(input => {
                const value = parseFloat(input.value) || 0;
                total += value;
            });

            // Update total hours
            const totalCell = row.querySelector('.total-hours');
            if (totalCell) {
                totalCell.textContent = total.toFixed(1);
            }

            // Update percentage
            const allocated = parseFloat(row.querySelector('td:nth-last-child(2)')?.textContent) || 0;
            const percentageCell = row.querySelector('.percentage');

            if (percentageCell) {
                const percentage = allocated > 0 ? (total / allocated * 100).toFixed(1) : 0;
                percentageCell.textContent = `${percentage}%`;

                // Highlight if over allocated
                percentageCell.classList.toggle('text-danger', total > allocated);
                percentageCell.classList.toggle('text-success', total <= allocated);
            }
        });
    }



});