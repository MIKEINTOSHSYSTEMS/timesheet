document.addEventListener('DOMContentLoaded', function() {
    // Month/year selector change
    document.getElementById('month-selector')?.addEventListener('change', function() {
        const month = this.value;
        const year = document.getElementById('year-selector').value;
        window.location.href = `timesheet.php?month=${month}&year=${year}`;
    });
    
    document.getElementById('year-selector')?.addEventListener('change', function() {
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
                if (total > allocated) {
                    percentageCell.classList.add('text-danger');
                    percentageCell.classList.remove('text-success');
                } else {
                    percentageCell.classList.add('text-success');
                    percentageCell.classList.remove('text-danger');
                }
            }
        });
    }
});