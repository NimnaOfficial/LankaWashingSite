// Global variable to store orders
let allOrders = [];

document.addEventListener("DOMContentLoaded", () => {
    loadOrders();

    // Attach Event Listener to the Form
    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', submitEdit);
    }
    
    // Filter Listener
    document.getElementById("statusFilter").addEventListener('change', loadOrders);
});

// 1. FETCH ORDERS
window.loadOrders = async function() {
    const status = document.getElementById("statusFilter").value;
    const tbody = document.getElementById("orderTableBody");
    
    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">Loading...</td></tr>';

    try {
        const res = await fetch(`../php/customer-all-orders.php?status=${status}`);
        const data = await res.json();

        if (!data.success) throw new Error(data.error || "Failed to load");

        allOrders = data.rows; 
        tbody.innerHTML = "";

        if (data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No orders found.</td></tr>';
            return;
        }

        data.rows.forEach(row => {
            let badgeClass = "badge-neutral";
            const s = (row.status || "").toLowerCase();
            
            if (s.includes("process")) badgeClass = "badge-info";
            if (s.includes("complete") || s.includes("deliver") || s.includes("approv")) badgeClass = "badge-success";
            if (s.includes("cancel") || s.includes("reject")) badgeClass = "badge-danger";
            if (s.includes("pending")) badgeClass = "badge-warning";
            
            let prioClass = row.priority === "High" ? "badge-high" : "badge-neutral";

            let actionsHtml = `<span style="color:#94a3b8; font-size:0.85rem;"><i class="fa-solid fa-lock"></i> Locked</span>`;
            
            if (s === "pending") {
                actionsHtml = `
                    <div class="action-group">
                        <button class="btn-icon btn-edit" type="button"
                            onclick="openEditModal(${row.id})" 
                            title="Edit">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn-icon btn-delete" type="button"
                            onclick="cancelOrder(${row.id})" 
                            title="Cancel">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                `;
            }

            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td class="fw-bold">${row.code}</td>
                <td>
                    <div style="font-weight:600;">${row.productName || "No Name"}</div>
                    <div style="font-size:0.85rem; color:#64748b;">Qty: ${row.quantity || 0}</div>
                </td>
                <td>${row.expectedDate ? row.expectedDate.split(' ')[0] : '-'}</td>
                <td><span class="badge ${prioClass}">${row.priority}</span></td>
                <td><span class="badge ${badgeClass}">${row.status}</span></td>
                <td class="text-right">${actionsHtml}</td>
            `;
            tbody.appendChild(tr);
        });

    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="6" style="color: red; text-align: center;">Error: ${err.message}</td></tr>`;
    }
};

// 2. OPEN MODAL
window.openEditModal = function(id) {
    const order = allOrders.find(o => Number(o.id) === Number(id));
    
    if (!order) {
        alert("Error: Order data not found in memory.");
        return;
    }

    // Populate Fields
    document.getElementById('edit-id').value = order.id;
    document.getElementById('edit-product').value = order.productName || "";
    document.getElementById('edit-qty').value = order.quantity || 0;
    document.getElementById('edit-priority').value = order.priority || "Medium";
    
    const dateVal = order.expectedDate ? order.expectedDate.split(' ')[0] : "";
    document.getElementById('edit-date').value = dateVal;
    
    if(document.getElementById('edit-desc')) {
        document.getElementById('edit-desc').value = order.description || "";
    }

    // Show Modal
    const modal = document.getElementById('editModal');
    modal.style.display = 'flex'; // Requires CSS .modal-overlay { display: flex; ... }
};

// 3. CLOSE MODAL
window.closeModal = function() {
    document.getElementById('editModal').style.display = 'none';
};

// 4. SUBMIT EDIT
async function submitEdit(e) {
    e.preventDefault();
    
    const btn = document.querySelector('#editForm button[type="submit"]');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = "Saving...";

    const formData = new FormData();
    formData.append('requestId', document.getElementById('edit-id').value);
    formData.append('productName', document.getElementById('edit-product').value);
    formData.append('quantity', document.getElementById('edit-qty').value);
    formData.append('priority', document.getElementById('edit-priority').value);
    formData.append('expectedDate', document.getElementById('edit-date').value);
    
    if(document.getElementById('edit-desc')) {
        formData.append('description', document.getElementById('edit-desc').value);
    }

    try {
        const res = await fetch("../php/update-request.php", {
            method: "POST",
            body: formData
        });
        const data = await res.json();

        if(data.success) {
            alert("Updated Successfully!");
            closeModal();
            loadOrders(); 
        } else {
            alert("Error: " + (data.error || "Update failed"));
        }
    } catch(err) {
        alert("System Error: " + err.message);
    } finally {
        btn.disabled = false;
        btn.innerText = originalText;
    }
}

// 5. CANCEL ORDER
window.cancelOrder = async function(requestId) {
    if(!confirm("Cancel this order? This cannot be undone.")) return;
    try {
        const formData = new FormData();
        formData.append("requestId", requestId);
        
        const res = await fetch("../php/cancel-request.php", { method: "POST", body: formData });
        const data = await res.json();
        
        if (data.success) {
            alert("Order Cancelled");
            loadOrders();
        } else {
            alert(data.error);
        }
    } catch (err) { alert("Error: " + err.message); }
};