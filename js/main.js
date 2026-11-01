// Shared Notifications — Dismiss only feedback explicitly marked for automatic expiry.
// PHP supplies message text and status/alert roles; this script does not create announcements.
document.querySelectorAll('[data-auto-dismiss]').forEach(function (notice) {
    var timer;
    var fadeTimer;
    // Fade after five seconds, then hide after the matching CSS transition without removing focused content.
    function dismissNotice() {
        if (notice.contains(document.activeElement)) { return; }
        notice.classList.add('is-dismissing');
        fadeTimer = setTimeout(function () {
            if (!notice.contains(document.activeElement)) { notice.hidden = true; }
        }, 250);
    }
    function scheduleDismissal() { timer = setTimeout(dismissNotice, 5000); }
    // Pause both timers while focus is inside a notice; leaving starts a fresh reading interval.
    notice.addEventListener('focusin', function () {
        clearTimeout(timer); clearTimeout(fadeTimer); notice.classList.remove('is-dismissing');
    });
    notice.addEventListener('focusout', function (event) {
        if (!notice.contains(event.relatedTarget)) { scheduleDismissal(); }
    });
    scheduleDismissal();
});
