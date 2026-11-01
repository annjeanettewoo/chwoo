// Checkout History — Refresh delivery/review/payment pages restored from the browser's page cache.
// Recheck the PHP session so a completed or edited purchase is not shown as a stale checkout.
// Cache-Control: no-store alone does not exclude every browser's page cache.
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});
