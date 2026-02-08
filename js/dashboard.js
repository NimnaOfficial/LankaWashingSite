document.addEventListener('DOMContentLoaded', async () => {
    // Select elements
    const els = {
        name: document.getElementById('customerName'),
        total: document.getElementById('totalOrders'),
        pending: document.getElementById('pendingOrders'),
        completed: document.getElementById('completedOrders'),
        due: document.getElementById('paymentDue'),
        alertPanel: document.getElementById('alertPanel'),
        alertText: document.getElementById('alertText')
    };

    try {
        // Fetch data
        const res = await fetch('/AFinal/php/dashboard-data.php');
        const data = await res.json();

        if (data.error) {
            console.error("Dashboard API Error:", data.error);
            return;
        }

        // Update UI
        if(els.name) els.name.textContent = "Welcome, " + data.name;
        if(els.total) els.total.textContent = data.totalOrders;
        if(els.pending) els.pending.textContent = data.pendingOrders;
        if(els.completed) els.completed.textContent = data.completedOrders;
        
        // Format Money
        if(els.due) {
            els.due.textContent = "Rs" + parseFloat(data.paymentDue).toFixed(2);
            // Change color if due > 0
            if (parseFloat(data.paymentDue) > 0) {
                els.due.style.color = "#dc2626"; // Red
            } else {
                els.due.style.color = "#16a34a"; // Green
            }
        }

        // Update Alert Panel
        if (data.alertText) {
            els.alertText.innerHTML = `<i class="fa-solid fa-circle-info"></i> Rs{data.alertText}`;
            els.alertPanel.style.display = 'flex'; // Show panel
        } else {
            els.alertPanel.style.display = 'none';
        }

    } catch (err) {
        console.error("Network Error:", err);
    }
});