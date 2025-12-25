document.addEventListener("DOMContentLoaded", function () {

  const form = document.getElementById("paymentForm");
  const payBtn = document.getElementById("payBtn");
  const dummyPayBtn = document.getElementById("dummyPayBtn");

  function setPayButtonLoading(on) {
    if (!payBtn) return;
    if (on) {
      payBtn.disabled = true;
      payBtn.dataset.orig = payBtn.innerHTML;
      payBtn.innerHTML = "Processing...";
    } else {
      payBtn.disabled = false;
      payBtn.innerHTML = payBtn.dataset.orig || "Proceed to Pay";
    }
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    const username = document.getElementById("username").value.trim();
    const email = document.getElementById("email").value.trim();
    const phone = document.getElementById("phone").value.trim();
    const amount = document.getElementById("amount").value.trim();

    if (!username || !email || !amount || isNaN(amount)) {
      alert("Please fill valid details");
      return;
    }

    setPayButtonLoading(true);

    fetch("/paypal/CreateOrder.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ username, email, phone, amount })
    })
    .then(res => res.json())
    .then(data => {
      setPayButtonLoading(false);

      if (!data.id) {
        console.error("Create order failed:", data);
        alert("Order creation failed");
        return;
      }

      showConfirmation(data.id, username, amount);
    })
    .catch(err => {
      setPayButtonLoading(false);
      console.error(err);
      alert("Network error");
    });
  });

  if (dummyPayBtn) {
    dummyPayBtn.onclick = () => form.requestSubmit();
  }

  function showConfirmation(orderID, username, amount) {
    document.getElementById("confirmationSection").style.display = "block";
    document.getElementById("payment-container").style.display = "none";
    document.getElementById("confirmSummary").innerText =
      `${username} — $ ${parseFloat(amount).toFixed(2)}`;

    renderPayPalButton(orderID);
  }

  function renderPayPalButton(orderID) {
    const container = "#confirm-paypal-button";
    document.querySelector(container).innerHTML = "";

    paypal.Buttons({
      createOrder: () => orderID,

      onApprove: (data) => {
        return fetch("/paypal/VerifyPayment.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ orderID: data.orderID })
        })
        .then(res => res.json())
        .then(details => {
          if (details.status === "COMPLETED") {
            window.location.href = "/paypal/success.html?orderID=" + data.orderID;
          } else {
            console.error(details);
            alert("Payment not completed");
          }
        });
      },

      onError: err => {
        console.error("PayPal error:", err);
        alert("PayPal checkout error");
      }
    }).render(container);
  }

});