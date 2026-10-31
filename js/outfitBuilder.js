// Catalogue data is rendered once by PHP; additions use the shared session bag via POST.
(function () {
    'use strict';
    const root = document.getElementById('outfit-builder');
    if (!root) { return; }
    const products = Array.from(document.querySelectorAll('#outfit-catalogue [data-product-id]')).map(function (product) {
        return {
            id: Number(product.dataset.productId), name: product.dataset.name, category: product.dataset.category,
            colours: Array.from(product.querySelectorAll('[data-colour-id]')).map(function (colour) {
                return {
                    id: Number(colour.dataset.colourId), name: colour.dataset.name,
                    swatch: colour.dataset.swatch || null, image: colour.dataset.image,
                    priceCents: Number(colour.dataset.priceCents),
                    variants: Array.from(colour.querySelectorAll('[data-variant-id]')).map(function (variant) {
                        return { id: Number(variant.dataset.variantId), size: variant.dataset.size,
                            available: Number(variant.dataset.available) };
                    })
                };
            })
        };
    });
    const labels = { top: 'Top', bottom: 'Bottom', dress: 'Dress' };
    const categories = { top: 'Tops', bottom: 'Bottoms', dress: 'Dresses' };
    const state = {};
    let mode = root.querySelector('input[name="outfit_mode"]:checked').value;

    function money(cents) { return 'SGD $' + (cents / 100).toFixed(2); }
    function activeSlots() { return mode === 'dress' ? ['dress'] : ['top', 'bottom']; }
    function productFor(key) {
        return products.find(function (product) {
            return product.id === state[key].productId && product.category === categories[key];
        });
    }
    function colourFor(key) {
        const product = productFor(key);
        return product && product.colours.find(function (colour) { return colour.id === state[key].colourId; });
    }
    function variantFor(key) {
        const colour = colourFor(key);
        return colour && colour.variants.find(function (variant) { return variant.id === state[key].variantId; });
    }
    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined) { node.textContent = text; }
        return node;
    }

    function renderChoices(key) {
        const fieldset = root.querySelector('[data-slot="' + key + '"]');
        const product = productFor(key);
        const colourArea = fieldset.querySelector('[data-colours]');
        colourArea.replaceChildren();
        (product ? product.colours : []).forEach(function (colour) {
            const label = element('label', 'outfit-swatch');
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = key + '_colour';
            input.value = colour.id;
            input.checked = colour.id === state[key].colourId;
            input.setAttribute('aria-label', colour.name);
            const dot = element('span', 'outfit-swatch-dot' + (colour.swatch ? '' : ' is-unspecified'));
            dot.setAttribute('aria-hidden', 'true');
            if (colour.swatch) { dot.style.setProperty('--swatch', colour.swatch); }
            label.append(input, dot, element('span', 'outfit-sr-only', colour.name));
            colourArea.append(label);
        });
        renderSizes(key);
    }

    function renderSizes(key) {
        const area = root.querySelector('[data-slot="' + key + '"] [data-sizes]');
        const colour = colourFor(key);
        area.replaceChildren();
        (colour ? colour.variants : []).forEach(function (variant) {
            const label = element('label', 'outfit-size');
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = key + '_variant';
            input.value = variant.id;
            input.checked = variant.id === state[key].variantId;
            input.disabled = variant.available < 1;
            input.setAttribute('aria-label', 'Size ' + variant.size + (variant.available > 0
                ? ', ' + variant.available + ' available' : ', unavailable'));
            label.append(input, element('span', '', variant.size));
            area.append(label);
        });
    }

    function updateSlot(key) {
        const product = productFor(key);
        const colour = colourFor(key);
        const variant = variantFor(key);
        const fieldset = root.querySelector('[data-slot="' + key + '"]');
        const preview = root.querySelector('[data-preview="' + key + '"]');
        fieldset.querySelector('[data-colour-name]').textContent = colour ? colour.name : '—';
        const availability = fieldset.querySelector('[data-availability]');
        availability.classList.remove('is-available', 'is-unavailable');
        if (!product) {
            availability.textContent = 'Choose a ' + labels[key].toLowerCase() + ' to begin.';
        } else if (!colour || !colour.variants.length) {
            availability.textContent = 'No size variants are available for this piece.';
            availability.classList.add('is-unavailable');
        } else if (!colour.variants.some(function (item) { return item.available > 0; })) {
            availability.textContent = 'This colour is sold out. Try another colour or piece.';
            availability.classList.add('is-unavailable');
        } else if (variant) {
            availability.textContent = 'Size ' + variant.size + ' · ' + variant.available + ' available';
            availability.classList.add(variant.available > 0 ? 'is-available' : 'is-unavailable');
        } else {
            const count = colour.variants.filter(function (item) { return item.available > 0; }).length;
            availability.textContent = 'Choose a size · ' + count + (count === 1 ? ' size available' : ' sizes available');
        }
        preview.querySelector('[data-remove]').hidden = !product;
        preview.querySelector('[data-choose]').hidden = !!product;
        preview.querySelector('[data-preview-name]').textContent = product ? product.name : 'Your next favourite';
        preview.querySelector('[data-preview-detail]').textContent = colour
            ? colour.name + ' · ' + (variant ? 'Size ' + variant.size : 'Choose a size')
            : product ? 'No colour available' : 'Choose a piece to get started';
        const image = preview.querySelector('[data-preview-image]');
        const imageUrl = colour ? colour.image : '';
        if (image.getAttribute('src') !== imageUrl) { delete image.dataset.failedSrc; }
        const imageFailed = image.dataset.failedSrc === imageUrl;
        image.hidden = !imageUrl || imageFailed;
        image.alt = product && colour ? product.name + ' in ' + colour.name : '';
        if (imageUrl) {
            if (image.getAttribute('src') !== imageUrl) { image.src = imageUrl; }
        } else { image.removeAttribute('src'); }
        preview.querySelector('[data-photo-unavailable]').hidden = !product || (!!imageUrl && !imageFailed);
        fieldset.querySelector('.outfit-colours').hidden = !product || !product.colours.length;
        fieldset.querySelector('.outfit-sizes').hidden = !colour || !colour.variants.length;
    }

    function updateSummary(announce) {
        const list = document.getElementById('outfit-summary-items');
        list.replaceChildren();
        let total = 0;
        let complete = true;
        let pieceCount = 0;
        activeSlots().forEach(function (key) {
            const product = productFor(key);
            const colour = colourFor(key);
            const variant = variantFor(key);
            if (!product) { complete = false; return; }
            pieceCount++;
            complete = complete && !!colour && !!variant && variant.available > 0;
            const row = element('li');
            const description = element('span', '', product.name);
            description.append(element('small', '', (colour ? colour.name : 'No colour available')
                + ' · ' + (variant ? 'Size ' + variant.size : 'Size not selected')));
            row.append(description, element('strong', '', colour ? money(colour.priceCents) : 'Unavailable'));
            list.append(row);
            if (colour) { total += colour.priceCents; }
        });
        if (!pieceCount) { list.append(element('li', 'outfit-summary-empty', 'Choose a piece. See where it takes you.')); }
        document.getElementById('outfit-total').textContent = money(total);
        const message = complete ? 'Your look is complete. A little styling, all yours.'
            : !pieceCount ? 'Your next look starts with one piece.'
            : 'Choose ' + (mode === 'dress' ? 'a dress and size' : 'a top, a bottom and their sizes') + ' to complete your look.';
        document.getElementById('outfit-completeness').textContent = message;
        document.getElementById('outfit-completeness').classList.toggle('is-complete', complete);
        document.getElementById('outfit-add').disabled = !complete;
        if (announce) { document.getElementById('outfit-status').textContent = announce + ' Outfit subtotal ' + money(total) + '. ' + message; }
    }

    function clearSlot(key) {
        state[key] = { productId: null, colourId: null, variantId: null };
        document.getElementById('outfit-' + key).value = '';
        renderChoices(key);
        updateSlot(key);
    }

    Object.keys(labels).forEach(function (key) {
        const fieldset = root.querySelector('[data-slot="' + key + '"]');
        const select = fieldset.querySelector('[data-product]');
        const product = products.find(function (item) { return item.id === Number(select.value); });
        const selectedColour = fieldset.querySelector('input[name="' + key + '_colour"]:checked');
        const selectedVariant = fieldset.querySelector('input[name="' + key + '_variant"]:checked');
        state[key] = { productId: product ? product.id : null,
            colourId: selectedColour ? Number(selectedColour.value) : null,
            variantId: selectedVariant ? Number(selectedVariant.value) : null };
        select.addEventListener('change', function () {
            const product = products.find(function (item) {
                return item.id === Number(select.value) && item.category === categories[key];
            });
            state[key] = { productId: product ? product.id : null,
                colourId: product && product.colours.length ? product.colours[0].id : null, variantId: null };
            renderChoices(key);
            updateSlot(key);
            updateSummary(product ? product.name + ' selected.' : labels[key] + ' removed.');
        });
        fieldset.addEventListener('change', function (event) {
            if (event.target.name === key + '_colour') {
                state[key].colourId = Number(event.target.value);
                state[key].variantId = null;
                renderSizes(key);
                updateSlot(key);
                updateSummary(colourFor(key).name + ' selected for your ' + labels[key].toLowerCase() + '.');
            } else if (event.target.name === key + '_variant') {
                state[key].variantId = Number(event.target.value);
                updateSlot(key);
                updateSummary(labels[key] + ' size ' + variantFor(key).size + ' selected.');
            }
        });
        const preview = root.querySelector('[data-preview="' + key + '"]');
        preview.querySelector('[data-remove]').addEventListener('click', function () {
            clearSlot(key);
            updateSummary(labels[key] + ' removed.');
            select.focus();
        });
        preview.querySelector('[data-choose]').addEventListener('click', function () { select.focus(); });
        preview.querySelector('[data-preview-image]').addEventListener('error', function (event) {
            event.target.dataset.failedSrc = event.target.getAttribute('src');
            event.target.hidden = true;
            preview.querySelector('[data-photo-unavailable]').hidden = !productFor(key);
        });
        renderChoices(key);
        updateSlot(key);
    });

    function updateMode() {
        document.getElementById('styling-board').dataset.mode = mode;
        document.getElementById('board-mode-label').textContent = mode === 'dress' ? 'THE ONE-PIECE EDIT' : 'THE TWO-PIECE EDIT';
        root.querySelector('[data-board-lead]').textContent = mode === 'dress' ? 'a whole look' : 'a little better';
        root.querySelector('[data-board-emphasis]').textContent = mode === 'dress' ? 'in one.' : 'together.';
        Object.keys(labels).forEach(function (key) {
            const active = activeSlots().includes(key);
            const fieldset = root.querySelector('[data-slot="' + key + '"]');
            fieldset.hidden = !active;
            fieldset.disabled = !active;
            root.querySelector('[data-preview="' + key + '"]').hidden = !active;
        });
    }
    root.querySelectorAll('input[name="outfit_mode"]').forEach(function (input) {
        input.addEventListener('change', function () {
            mode = input.value;
            updateMode();
            updateSummary(mode === 'dress' ? 'Dress styling board.' : 'Top and bottom styling board.');
        });
    });
    document.getElementById('outfit-clear').addEventListener('click', function () {
        Object.keys(labels).forEach(clearSlot);
        updateSummary('Styling board cleared.');
    });
    document.getElementById('outfit-form').addEventListener('submit', function (event) {
        updateSummary();
        if (document.getElementById('outfit-add').disabled) { event.preventDefault(); }
    });
    // Recheck controls restored by browser history against the rendered availability.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) { window.location.reload(); }
    });
    updateMode();
    updateSummary();
}());
