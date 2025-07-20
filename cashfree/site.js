document.addEventListener('DOMContentLoaded', function () {
    const originalAmount = 100;
    const discountedAmount = 20;
    const validCouponCode = '20OFF';

    const couponCheckbox = document.getElementById('coupon');
    const couponFieldContainer = document.getElementById('coupon-field-container');
    const couponCodeInput = document.getElementById('coupon-code');
    const couponStatus = document.getElementById('coupon-status');
    const amountDisplay = document.getElementById('amount-display');
    const userName = document.getElementById('user-name');
    const phoneNumber = document.getElementById('phone-number');
    const pyloader = document.getElementById('loading');
    const paybutton = document.getElementById('pay-button');
    const paymentStatus = document.getElementById('paymentStatus');
    const paymentContainer = document.getElementsByClassName('payment-container')[0];
    const successContainer = document.getElementsByClassName('success-container')[0];

    let couponApplied = false;

    couponCheckbox.addEventListener('change', function () {
        couponFieldContainer.classList.toggle('visible');
        if (!couponCheckbox.checked) {
            resetCoupon();
        }
    });

    couponCodeInput.addEventListener('input', function () {
        const code = couponCodeInput.value.trim().toUpperCase();

        if (code === validCouponCode && !couponApplied) {
            applyCoupon();
        } else if (code !== validCouponCode && couponApplied) {
            resetCoupon();
        }
    });

    function applyCoupon() {
        couponApplied = true;
        amountDisplay.innerHTML = `<span class="original-amount">₹ ${originalAmount}.00</span>₹ ${discountedAmount}.00`;
        couponStatus.innerHTML = 'Coupon applied successfully!';
        couponStatus.className = 'coupon-status success-message';
    }

    function resetCoupon() {
        couponApplied = false;
        amountDisplay.textContent = `₹ ${originalAmount}.00`;
        couponStatus.innerHTML = '';
        couponStatus.className = 'coupon-status';
        couponCodeInput.value = '';
    }

    function paymentClosed(){
        paybutton.disabled = false;
        pyloader.style.display = 'none';
    }

    function StatusChecked(paymentid) {
        paymentContainer.classList.add('d-none');
        successContainer.classList.remove('d-none');
        userName.value = '';
        phoneNumber.value = '';
        couponCodeInput.value = '';
        pyloader.style.display = 'none';
        paybutton.disabled = false;
        paymentStatus.classList.add('d-none');

        const loaderOverlay = document.createElement('div');
        loaderOverlay.className = 'loader-overlay';
        const spinner = document.createElement('div');
        spinner.className = 'spinner';
        loaderOverlay.appendChild(spinner);
        successContainer.appendChild(loaderOverlay);

        setTimeout(function () {
            loaderOverlay.classList.add('d-none');
            paymentStatus.classList.remove('d-none');
            document.getElementById('txn-id').textContent = paymentid || 'N/A'
        }, 1000);
    }
    document.getElementById("pay-button").addEventListener("click", function (e) {
        var error = false;
        if (userName.value == '') {
            error = true;
            userName.focus();
            alert('please enter valid name');
        }
        if (phoneNumber.value == '') {
            error = true;
            phoneNumber.focus();
            alert('please enter valid mobile number');
        }
        if (!error) {
            //pyloader.style.display = 'block';
            //paybutton.disabled = true;
            var amount = 0;
            if (!couponApplied) {
                amount = originalAmount;
            } else {
                amount = discountedAmount;
            }
            fetch("CreateOrder.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    amount: amount * 100,
                    username: userName.value,
                    phone: phoneNumber.value,
                    email: email.value,
                    orderid: orderid
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.payment_session_id) {
                    alert("Failed to create order. " + (data.error_message || "Try again later."));
                    return;
                }
        
            
                let checkoutOptions = {
                paymentSessionId: data.payment_session_id,
                redirectTarget: "_modal",
                appearance: {
                    theme: "light",
                    layout: "POPUP", // or "SIDEBAR", "CENTER"
                    width: "425px",
                    height: "700px",
                },
            };

            const cashfree = Cashfree({
                mode: "sandbox",
            });
            cashfree.checkout(checkoutOptions).then((result) => {
                if (result.error) {
                    alert("There is some payment error.");
                    document.body.innerHTML = '';
                }

                if (result.redirect) {
                    console.log("Redirecting to Cashfree hosted page...");
                }

                if (result.paymentDetails) {
                    window.location.href = "https://localhost:8080/verify?txnId=" + data.order_id;
                }
            });
            })
            .catch(error => console.error("Error:", error));
            e.preventDefault();
        }
    });
});