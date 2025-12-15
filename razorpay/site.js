document.addEventListener('DOMContentLoaded', function () {
    const originalAmount = 1489;
    const discountedAmount = 997;
    const validCouponCode = 'DSCAGENT997';

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
            pyloader.style.display = 'block';
            paybutton.disabled = true;
            var amount = 0;
            if (couponCodeInput.value == '') {
                amount = originalAmount;
            } else {
                amount = discountedAmount;
            }
            var options = {
                "key": "rzp_test_XXXXXXXXXX",
                "amount": amount * 100,
                "currency": "INR",
                "name": "Guruji Gyan",
                "description": "Guruji Gyan Transaction",
                "image": "https://www.nuget.org/profiles/UNLEIN/avatar?imageSize=512",
                "handler": function (response) {
                    StatusChecked(response.razorpay_payment_id);
                },
                "prefill": {
                    "name": userName.value,
                    "contact": phoneNumber.value
                },
                "theme": {
                    "color": "#F37254"
                },
                "modal": {
                    "ondismiss": function () {
                        paymentClosed();
                    }
                }
            };
            var rzp1 = new Razorpay(options);
            rzp1.open();
            e.preventDefault();
        }
    });

});