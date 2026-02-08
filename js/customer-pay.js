// js/customer-pay.js

// Global variables
let realAmount = 0.00;
let realOrderRef = "BALANCE-PAYMENT"; 

// 1. INITIALIZE ON LOAD
document.addEventListener("DOMContentLoaded", async () => {
    const dueLabel = document.getElementById('totalDue');
    const bankLabel = document.getElementById('bank-amt-display');
    const orderLabel = document.getElementById('orderLabel');

    if(dueLabel) dueLabel.textContent = "Loading...";

    try {
        // Fetch Real Due Amount
        const res = await fetch('../php/due.php');
        const data = await res.json();

        if (data.error) {
            console.error("API Error:", data.error);
            if(dueLabel) dueLabel.textContent = "$0.00";
            return;
        }

        realAmount = parseFloat(data.due) || 0.00;
        
        // Update UI
        const formattedMoney = "Rs" + realAmount.toFixed(2);
        if(dueLabel) dueLabel.textContent = formattedMoney;
        if(bankLabel) bankLabel.textContent = formattedMoney;

        if(orderLabel) {
            orderLabel.textContent = (realAmount > 0) ? "Outstanding Balance" : "No Payment Due";
        }

    } catch (err) {
        console.error("Fetch Network Error:", err);
        if(dueLabel) dueLabel.textContent = "$0.00";
    }
});

// 2. STRIPE SETUP
// Replace with your actual Publishable Key
const stripe = Stripe('pk_test_51SwQ9tKAOybYHcfojCv2vIiuvN367rrNwz9Qg0by0PdjGWFu0ZqYcHobA7gdZhvRabPKb3EeeQXCnx67icThR7Q3000lrAioHH'); 
const elements = stripe.elements();

const style = {
    base: { color: '#0f172a', fontFamily: 'Inter, sans-serif', fontSize: '16px', '::placeholder': { color: '#94a3b8' } },
    invalid: { color: '#dc2626', iconColor: '#dc2626' }
};

const card = elements.create('card', {style: style, hidePostalCode: true});
card.mount('#card-element');

const wrapper = document.getElementById('stripe-wrapper');
card.on('focus', () => wrapper.classList.add('focused'));
card.on('blur', () => wrapper.classList.remove('focused'));
card.on('change', (event) => {
    const displayError = document.getElementById('card-errors');
    displayError.innerHTML = event.error ? `<i class="fa-solid fa-circle-exclamation"></i> ${event.error.message}` : '';
});

// 3. UI TOGGLE
let currentMethod = 'card';
window.toggleMethod = function(method) {
    currentMethod = method;
    document.querySelectorAll('.payment-details').forEach(el => el.classList.remove('active'));
    document.getElementById(`section-${method}`).classList.add('active');
};

// 4. SUBMIT FORM
const form = document.getElementById('payment-form');

form.addEventListener('submit', async function(event) {
    event.preventDefault();
    
    if (realAmount <= 0) {
        alert("You have no pending payments due!");
        return;
    }

    const submitBtn = document.getElementById('submit-btn');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

    const autoUserEmail = "nimnakgls980@gmail.com"; 
    let transactionRef = '';

    try {
        if (currentMethod === 'card') {
            // A. CARD
            const result = await stripe.createToken(card);
            if (result.error) throw new Error(result.error.message);
            transactionRef = result.token.id; 
        } else {
            // B. BANK UPLOAD
            const name = document.getElementById('sender-name').value;
            const fileInput = document.getElementById('receipt-upload');
            
            if(!name) throw new Error("Please enter Sender Name");
            if(fileInput.files.length === 0) throw new Error("Please upload the receipt slip");

            submitBtn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up fa-bounce"></i> Uploading...';
            
            const uploadData = new FormData();
            uploadData.append('receiptFile', fileInput.files[0]);

            const uploadRes = await fetch('../php/upload_receipt.php', { method: 'POST', body: uploadData });
            const uploadResult = await uploadRes.json();
            
            if(!uploadResult.success) throw new Error(uploadResult.error || "Upload Failed");
            transactionRef = uploadResult.link; 
        }

        // C. SAVE TO DB (Calls your process_payment.php)
        submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk fa-bounce"></i> Saving...';
        
        const response = await fetch('../php/process_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: realOrderRef,
                amount: realAmount,
                method: currentMethod === 'card' ? 'Credit Card' : 'Bank Transfer',
                reference: transactionRef,
                email: autoUserEmail 
            })
        });

        const data = await response.json();

        if(data.success) {
            alert("Payment Successful! Receipt sent.");
            window.location.href = 'payment-history.html';
        } else {
            throw new Error(data.error || "Database Save Failed");
        }

    } catch (error) {
        console.error(error);
        alert("Error: " + error.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});

// 5. File Input Visuals
const fileInput = document.getElementById('receipt-upload');
if(fileInput) {
    fileInput.addEventListener('change', function(e) {
        const text = document.querySelector('.upload-placeholder span');
        const icon = document.querySelector('.upload-placeholder i');
        const box = document.querySelector('.upload-placeholder');
        
        if (this.files && this.files[0]) {
            text.textContent = this.files[0].name;
            text.style.color = "#15803d"; 
            icon.className = "fa-solid fa-circle-check";
            icon.style.color = "#15803d";
            box.style.borderColor = "#22c55e";
            box.style.backgroundColor = "#f0fdf4";
        }
    });
}