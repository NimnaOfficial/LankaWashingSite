document.addEventListener("DOMContentLoaded", () => {
    loadDashboardData();
});

async function loadDashboardData() {
    // Select elements by ID
    const els = {
        name: document.getElementById("supplierName"),
        totalPOs: document.getElementById("totalPOs"),
        newRequests: document.getElementById("newRequests"),
        invoicesSent: document.getElementById("invoicesSent"),
        pendingPayments: document.getElementById("pendingPayments"),
        pendingNote: document.getElementById("pendingNote")
    };

    try {
        // Fetch data from your backend
        const res = await fetch("../php/supplier-dashboard-data.php");
        
        // Handle Not Logged In
        if (res.status === 401) {
            window.location.href = "../../index.html"; 
            return;
        }

        const data = await res.json();

        if (data.error) {
            console.error("Dashboard API Error:", data.error);
            return;
        }

        // --- UPDATE UI ---
        
        // 1. Name
        if (els.name) els.name.textContent = data.supplierName || "Supplier";

        // 2. Stats
        if (els.totalPOs) els.totalPOs.textContent = data.totalPOs;
        if (els.newRequests) els.newRequests.textContent = data.newRequests;
        if (els.invoicesSent) els.invoicesSent.textContent = data.invoicesSent;

        // 3. Money (Format nicely)
        if (els.pendingPayments) {
            const amount = parseFloat(data.pendingPayments) || 0;
            els.pendingPayments.textContent = "Rs" + amount.toLocaleString('en-US', {minimumFractionDigits: 2});
            
            // Change color if money is owed (Red = Owed, Green = Settled)
            if (amount > 0) {
                els.pendingPayments.style.color = "#dc2626"; // Red
            } else {
                els.pendingPayments.style.color = "#16a34a"; // Green
            }
        }

        // 4. Note
        if (els.pendingNote) els.pendingNote.textContent = data.pendingNote;

    } catch (err) {
        console.error("Network Error:", err);
        if (els.name) els.name.textContent = "Connection Error";
    }
}
