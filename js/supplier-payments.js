document.addEventListener('DOMContentLoaded', () => {
    loadPayments('all');

    const filterSelect = document.querySelector('.filter-select');
    if(filterSelect) {
        filterSelect.addEventListener('change', (e) => {
            loadPayments(e.target.value);
        });
    }
});

async function loadPayments(filter) {
    const tbody = document.getElementById('paymentTable');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px;">Loading payments...</td></tr>';

    try {
        const res = await fetch(`../php/supplier-get-payments.php?filter=${filter}`);
        const data = await res.json();

        if (data.error) {
            console.error(data.error);
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:red;">Error loading data</td></tr>`;
            return;
        }

        // 1. Update Summaries
        document.getElementById('pendingTotal').textContent = formatMoney(data.summary.pendingClearing);
        document.getElementById('settledTotal').textContent = formatMoney(data.summary.settledThisMonth);

        // 2. Build Table
        if (data.rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:2rem;">No invoices found.</td></tr>`;
            return;
        }

        tbody.innerHTML = data.rows.map(row => {
            const amount = parseFloat(row.totalAmount);
            // Capitalize first letter
            const status = row.status.charAt(0).toUpperCase() + row.status.slice(1).toLowerCase();
            
            // Badge Logic
            let badgeClass = 'badge-neutral';
            if (['Paid', 'Success'].includes(status)) badgeClass = 'badge-success';
            if (['Pending', 'Verifying', 'Approved'].includes(status)) badgeClass = 'badge-warning';
            if (['Cancelled', 'Rejected'].includes(status)) badgeClass = 'badge-danger';

            // Action Buttons (Only allow Cancel if strictly Pending)
            let actions = '<span class="text-muted">-</span>';
            if(status === 'Pending') {
                actions = `
                    <button class="btn-sm btn-danger-outline" onclick="cancelInvoice(${row.invoiceId})" title="Cancel Invoice">
                        <i class="fa-solid fa-xmark"></i> Cancel
                    </button>
                `;
            }

            return `
                <tr id="row-${row.invoiceId}">
                    <td class="fw-bold">${row.invoice_number || 'INV-???'}</td>
                    <td>${row.po_reference}</td>
                    <td>${row.invoice_date}</td>
                    <td class="fw-amount">${formatMoney(amount)}</td>
                    <td><span class="badge ${badgeClass}">${status}</span></td>
                    <td class="text-muted">${row.estimatedPayDate ? row.estimatedPayDate.split(' ')[0] : '-'}</td>
                    <td class="text-right">${actions}</td>
                </tr>
            `;
        }).join('');

    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:red;">Connection Error</td></tr>`;
    }
}

async function cancelInvoice(id) {
    if(!confirm("Are you sure you want to cancel this invoice?")) return;

    try {
        const formData = new FormData();
        formData.append('invoiceId', id);
        formData.append('action', 'cancel');

        // Note: You might need to create 'supplier-update-invoice.php' if it doesn't exist
        const res = await fetch('../php/supplier-update-invoice.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await res.json();
        
        if(data.ok || data.success) {
            alert("Invoice cancelled.");
            loadPayments(document.querySelector('.filter-select').value); 
        } else {
            alert("Error: " + (data.error || "Could not cancel"));
        }
    } catch(err) {
        alert("Network Error");
    }
}

function formatMoney(amount) {
    return 'Rs' + Number(amount).toLocaleString('en-US', {minimumFractionDigits: 2});
}