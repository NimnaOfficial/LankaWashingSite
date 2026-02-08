        document.addEventListener("DOMContentLoaded", () => {
            // Set current month in filter
            const now = new Date();
            const monthStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
            document.getElementById('monthFilter').value = monthStr;

            loadHistory();
        });

        async function loadHistory() {
            const tbody = document.getElementById("historyBody");

            try {
                const res = await fetch('../php/payments.php');
                const data = await res.json();

                if (data.error) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:red;">${data.error}</td></tr>`;
                    return;
                }

                if (!data.data || data.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 2rem;">No payment history found.</td></tr>`;
                    return;
                }

                tbody.innerHTML = ""; // Clear loading message

                data.data.forEach(row => {
                    // 1. Format Date
                    const dateObj = new Date(row.date);
                    const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

                    // 2. Format Badge Color
                    let badgeClass = "badge-neutral";
                    const s = row.status.toLowerCase();
                    if (s === 'success' || s === 'paid' || s === 'completed') badgeClass = "badge-success";
                    else if (s === 'pending' || s === 'verifying') badgeClass = "badge-warning";
                    else if (s === 'failed') badgeClass = "badge-danger";

                    // 3. Format Method Icon
                    let methodHtml = `<div class="method-badge"><i class="fa-solid fa-credit-card"></i> ${row.method}</div>`;
                    if (row.method.toLowerCase().includes('bank')) {
                        methodHtml = `<div class="method-badge"><i class="fa-solid fa-building-columns text-gray"></i> ${row.method}</div>`;
                    } else if (row.method.toLowerCase().includes('card')) {
                        methodHtml = `<div class="method-badge"><i class="fa-brands fa-cc-visa text-blue"></i> ${row.method}</div>`;
                    }

                    // 4. Create Row
                    const tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td class="fw-bold">${row.transaction}</td>
                        <td class="text-muted">${dateStr}</td>
                        <td>${row.orderRef}</td>
                        <td>${methodHtml}</td>
                        <td class="fw-amount">$${row.amount.toFixed(2)}</td>
                        <td><span class="badge ${badgeClass}">${row.status}</span></td>
                    `;
                    tbody.appendChild(tr);
                });

            } catch (err) {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:red;">Connection Error</td></tr>`;
            }
        }