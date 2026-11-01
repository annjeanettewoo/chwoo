// Product Details — Colour/size selection, image/price updates and inline Add to Bag validation.
// PHP renders colour IDs and remaining quantities from the database and session bag, then rechecks POSTs.
(function () {
    const form = document.querySelector('.product-form');
    if (!form) { return; }
    // Selected radios are the current UI state; colour attributes hold PHP's per-size stock snapshot.
    const colours = form.querySelectorAll('input[name="colour"]');
    const sizes = form.querySelectorAll('input[name="size"]');
    const sizeArea = form.querySelector('.product-sizes');
    const message = document.getElementById('stock-message');
    const addButton = form.querySelector('.add-to-bag');

    // --- Product Details — Size guide navigation ---
    // Open the native details panel before scrolling; Enter activation follows the same link handler.
    const guideLink = form.querySelector('a[href="#size-and-fit"]');
    const guide = document.getElementById('size-and-fit');
    if (guideLink && guide) {
        guideLink.addEventListener('click', function (event) {
            event.preventDefault();
            guide.open = true;
            guide.querySelector('summary').focus({ preventScroll: true });
            guide.scrollIntoView({ block: 'center' });
        });
    }

    // --- Product Details — Update colour gallery, price and size availability ---
    // Runs at startup and on either radio change. Keep the chosen size when changing colour,
    // but recheck its allowance and block Add if that size is unavailable in the new colour.
    function updateSelection() {
        const colour = form.querySelector('input[name="colour"]:checked');
        const size = form.querySelector('input[name="size"]:checked');
        if (!colour) { return; }
        document.getElementById('selected-colour-name').textContent = colour.dataset.name;
        document.getElementById('selected-price').textContent = colour.dataset.price;
        // Galleries are already rendered by PHP; changing colour requires no image request endpoint.
        document.querySelectorAll('[data-colour-gallery]').forEach(function (gallery) {
            gallery.hidden = gallery.dataset.colourGallery !== colour.value;
        });
        sizes.forEach(function (input) {
            const available = Number(colour.getAttribute('data-stock-' + input.value.toLowerCase())) > 0;
            const label = input.closest('.size-choice');
            label.classList.toggle('is-unavailable', !available);
            input.setAttribute('aria-label', 'Size ' + input.value + (available ? ', available' : ', unavailable'));
        });
        const quantity = size ? Number(colour.getAttribute('data-stock-' + size.value.toLowerCase())) : 0;
        const available = quantity > 0;
        // Keep Add usable with no size so submission can show the focused inline missing-size error.
        addButton.disabled = !!size && !available;
        sizeArea.classList.remove('is-invalid');
        sizeArea.removeAttribute('aria-invalid');
        message.className = 'product-stock-message' + (size ? (available ? ' success' : ' error') : '');
        message.textContent = !size ? 'Please choose a size.'
            : available ? 'Size ' + size.value + ' · ' + quantity + ' available'
            : 'Size ' + size.value + ' is unavailable';

        // Drawer POSTs return to the colour/size currently visible, even before Add to Bag.
        document.querySelectorAll('.chwoo-bag-controls input[name="return_to"]').forEach(function (input) {
            const parts = input.value.split('?');
            const query = new URLSearchParams(parts[1] || '');
            query.set('colour', colour.value);
            if (size) { query.set('size', size.value); } else { query.delete('size'); }
            input.value = parts[0] + '?' + query.toString();
        });
    }
    colours.forEach(function (input) { input.addEventListener('change', updateSelection); });
    sizes.forEach(function (input) { input.addEventListener('change', updateSelection); });
    // --- Product Details — Validate Add to Bag without replacing PHP checks ---
    // Keep native radio keyboard behaviour; show our inline missing-size treatment on Add.
    form.noValidate = true;
    form.addEventListener('submit', function (event) {
        // Wishlist saves the product independently of size and uses its own server-side handler.
        if (event.submitter && event.submitter.classList.contains('chwoo-wishlist-button')) { return; }
        const size = form.querySelector('input[name="size"]:checked');
        if (!size) {
            event.preventDefault();
            sizeArea.classList.add('is-invalid');
            sizeArea.setAttribute('aria-invalid', 'true');
            message.className = 'product-stock-message error';
            message.textContent = 'Please choose a size.';
            sizes[0].focus();
        } else if (addButton.disabled) { event.preventDefault(); }
    });
    // Preserve a server-rendered missing-size error while initialising the current gallery and allowance.
    const hadSizeError = sizeArea.classList.contains('is-invalid');
    updateSelection();
    if (hadSizeError) {
        sizeArea.classList.add('is-invalid');
        sizeArea.setAttribute('aria-invalid', 'true');
        message.className = 'product-stock-message error';
    }
}());
