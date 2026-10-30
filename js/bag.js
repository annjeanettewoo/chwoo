// Progressive enhancement: BAG remains an ordinary link without JavaScript.
(function () {
    const drawer = document.getElementById('chwoo-bag-drawer');
    const overlay = document.getElementById('chwoo-bag-overlay');
    if (!drawer || !overlay) { return; }
    const triggers = document.querySelectorAll('[data-bag-trigger]');
    const closeButton = drawer.querySelector('.chwoo-bag-close');
    let previousFocus = null;
    let previousOverflow = '';
    let background = [];
    let hideTimer;
    let isOpen = false;

    function openBag() {
        if (isOpen) { return; }
        isOpen = true;
        clearTimeout(hideTimer);
        previousFocus = document.activeElement;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        background = Array.from(document.body.children)
            .filter(function (node) { return node !== drawer && node !== overlay && node.tagName !== 'SCRIPT'; })
            .map(function (node) { const wasInert = node.inert; node.inert = true; return { node: node, wasInert: wasInert }; });
        drawer.inert = false;
        drawer.setAttribute('aria-hidden', 'false');
        overlay.hidden = false;
        requestAnimationFrame(function () {
            overlay.classList.add('is-open');
            closeButton.focus();
        });
        drawer.classList.add('is-open');
        triggers.forEach(function (trigger) { trigger.setAttribute('aria-expanded', 'true'); });
    }

    function closeBag() {
        if (!isOpen) { return; }
        isOpen = false;
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        drawer.inert = true;
        background.forEach(function (entry) { entry.node.inert = entry.wasInert; });
        document.body.style.overflow = previousOverflow;
        triggers.forEach(function (trigger) { trigger.setAttribute('aria-expanded', 'false'); });
        if (previousFocus && previousFocus !== document.body) { previousFocus.focus(); }
        else if (triggers[0]) { triggers[0].focus(); }
        hideTimer = setTimeout(function () { overlay.hidden = true; }, 300);
    }

    triggers.forEach(function (trigger) {
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-controls', drawer.id);
        trigger.setAttribute('aria-expanded', 'false');
        trigger.addEventListener('click', function (event) {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) { return; }
            event.preventDefault();
            openBag();
        });
    });
    closeButton.addEventListener('click', closeBag);
    overlay.addEventListener('click', closeBag);
    document.addEventListener('keydown', function (event) {
        if (!drawer.classList.contains('is-open')) { return; }
        if (event.key === 'Escape') { closeBag(); }
        if (event.key === 'Tab') {
            const controls = Array.from(drawer.querySelectorAll('a[href], button:not([disabled])'));
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (!drawer.contains(document.activeElement)) { event.preventDefault(); first.focus(); }
            else if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    if (drawer.dataset.resume === 'true') {
        // PHP rendered the updated drawer open. Initialise focus/inert immediately,
        // then restore transitions only after that open state has been painted.
        openBag();
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                drawer.classList.remove('is-restoring');
                overlay.classList.remove('is-restoring');
            });
        });
    } else if (drawer.dataset.open === 'true') {
        if (document.readyState === 'complete') { openBag(); }
        else { window.addEventListener('load', openBag, { once: true }); }
    }
}());
