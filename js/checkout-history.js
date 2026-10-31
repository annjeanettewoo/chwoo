// Recheck the PHP session if Back/Forward restores a frozen checkout page.
// Cache-Control: no-store alone does not exclude every browser's page cache.
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});
