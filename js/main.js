// Only explicitly marked, non-essential success flashes expire.
document.querySelectorAll('[data-auto-dismiss]').forEach(function (notice) {
    var timer;
    var fadeTimer;
    function dismissNotice() {
        if (notice.contains(document.activeElement)) { return; }
        notice.classList.add('is-dismissing');
        fadeTimer = setTimeout(function () {
            if (!notice.contains(document.activeElement)) { notice.hidden = true; }
        }, 250);
    }
    function scheduleDismissal() { timer = setTimeout(dismissNotice, 5000); }
    // Preserve an interactive link inside a notice while it has keyboard focus.
    notice.addEventListener('focusin', function () {
        clearTimeout(timer); clearTimeout(fadeTimer); notice.classList.remove('is-dismissing');
    });
    notice.addEventListener('focusout', function (event) {
        if (!notice.contains(event.relatedTarget)) { scheduleDismissal(); }
    });
    scheduleDismissal();
});
