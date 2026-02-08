 // 1. LOAD PROFILE
        async function loadProfile() {
            try {
                const res = await fetch("../php/get-profile.php");
                
                if (res.status === 401) {
                    window.location.href = "../index.html";
                    return;
                }

                const data = await res.json();

                if(data.error) {
                    alert(data.error);
                    return;
                }

                // Fill Inputs
                document.getElementById("fullName").value = data.name || "";
                document.getElementById("emailDisplay").value = data.email || "";
                document.getElementById("phone").value = data.phone || "";
                document.getElementById("address").value = data.address || "";
                document.getElementById("ordersCount").textContent = data.totalOrders ?? 0;
                
                // Update Sidebar
                const name = data.name || "Customer";
                document.getElementById("display-name").textContent = name;
                
                // Update Dynamic Avatar
                document.getElementById("profileAvatar").src = 
                    `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=2563eb&color=fff&size=150`;

            } catch(err) {
                console.error("Load error:", err);
            }
        }

        // 2. TOGGLE EDIT
        function toggleEdit() {
            const inputs = document.querySelectorAll('#profileForm input:not([type="email"]), #profileForm textarea');
            const actions = document.getElementById("profile-actions");
            const editBtn = document.getElementById("edit-toggle");

            inputs.forEach(input => {
                input.disabled = false;
                input.style.backgroundColor = "white";
                input.style.borderColor = "#3b82f6";
            });

            actions.classList.remove("hidden");
            editBtn.style.display = "none";
        }

        function cancelEdit() {
            window.location.reload();
        }

        // 3. UPDATE PROFILE
        async function updateProfile(e) {
            e.preventDefault();
            
            const btn = e.target.querySelector("button[type=submit]");
            const originalText = btn.innerText;
            btn.innerText = "Saving...";
            btn.disabled = true;

            const formData = new FormData();
            formData.append("name", document.getElementById("fullName").value.trim());
            formData.append("phone", document.getElementById("phone").value.trim());
            formData.append("address", document.getElementById("address").value.trim());

            try {
                const res = await fetch("../php/update-profile.php", {
                    method: "POST",
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    alert("Profile updated successfully!");
                    window.location.reload();
                } else {
                    alert(data.error || "Update failed");
                }
            } catch(err) {
                alert("Error: " + err.message);
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }

        // 4. UPDATE PASSWORD
        async function updatePassword(e) {
            e.preventDefault();
            
            const currentPass = document.getElementById("currentPassword").value;
            const newPass = document.getElementById("newPassword").value;

            if(!currentPass || !newPass) return;

            const formData = new FormData();
            formData.append("currentPassword", currentPass);
            formData.append("newPassword", newPass);

            try {
                const res = await fetch("../php/update-password.php", {
                    method: "POST",
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    alert("Password changed successfully!");
                    document.getElementById("securityForm").reset();
                } else {
                    alert(data.error || "Password update failed");
                }
            } catch(err) {
                alert("Error: " + err.message);
            }
        }

        // Initialize
        loadProfile();