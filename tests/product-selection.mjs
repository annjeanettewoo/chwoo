// Focused DOM simulation of the actual Product Details script; no browser or dependencies.
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

function classes(initial = []) {
    const values = new Set(initial);
    return { add: value => values.add(value), remove: value => values.delete(value),
        contains: value => values.has(value), toggle: (value, enabled) => enabled ? values.add(value) : values.delete(value) };
}
function setup(selectedSize = '', invalid = false) {
    const sizes = ['XS', 'S', 'M', 'L'].map(value => ({ value, checked: value === selectedSize,
        attributes: {}, listeners: {}, label: { classList: classes() },
        closest() { return this.label; }, setAttribute(key, value) { this.attributes[key] = value; },
        addEventListener(key, handler) { this.listeners[key] = handler; }, focus() { focused = this.value; } }));
    const colours = [[5,2,0,0],[1,0,1,2]].map((stock, index) => ({ value: String(index + 8), checked: index === 0,
        dataset: { name: index === 0 ? 'Midnight' : 'Ivory', price: index === 0 ? 'SGD $46.00' : 'SGD $44.00' }, listeners: {},
        getAttribute(key) { return String(stock[['XS','S','M','L'].indexOf(key.replace('data-stock-', '').toUpperCase())]); },
        addEventListener(key, handler) { this.listeners[key] = handler; } }));
    const message = {}, button = {}, area = { classList: classes(invalid ? ['is-invalid'] : []), attributes: {},
        setAttribute(key, value) { this.attributes[key] = value; }, removeAttribute(key) { delete this.attributes[key]; } };
    const outputs = { 'stock-message': message, 'selected-colour-name': {}, 'selected-price': {} };
    const galleries = colours.map(colour => ({ dataset: { colourGallery: colour.value } }));
    const form = { listeners: {}, querySelectorAll(selector) { return selector.includes('colour') ? colours : sizes; },
        querySelector(selector) {
            if (selector.includes(':checked')) { return (selector.includes('colour') ? colours : sizes).find(input => input.checked); }
            return selector === '.product-sizes' ? area : button;
        }, addEventListener(key, handler) { this.listeners[key] = handler; } };
    let focused = '';
    const document = { querySelector: () => form, getElementById: id => outputs[id],
        querySelectorAll: selector => selector === '[data-colour-gallery]' ? galleries : [] };
    vm.runInNewContext(readFileSync(new URL('../js/product-selection.js', import.meta.url), 'utf8'), { document, URLSearchParams });
    return { message, button, area, outputs, galleries,
        size(value) { sizes.forEach(input => { input.checked = input.value === value; }); sizes[0].listeners.change(); },
        colour(index) { colours.forEach((input, i) => { input.checked = i === index; }); colours[index].listeners.change(); },
        submit() { let prevented = false; form.listeners.submit({ preventDefault() { prevented = true; } }); return prevented; },
        focused: () => focused };
}
let checks = 0;
function check(condition, label) { assert.ok(condition, label); checks++; console.log('PASS: ' + label); }
const page = setup();
check(page.message.textContent === 'Please choose a size.' && !page.button.disabled, 'No-size prompt and existing Add validation behaviour remain');
check(page.submit() && page.focused() === 'XS' && page.area.classList.contains('is-invalid'), 'Missing size blocks submission and focuses the size controls');
page.size('XS');
check(page.message.textContent === 'Size XS · 5 available' && !page.button.disabled, 'Selected XS shows remaining five');
check(!page.area.classList.contains('is-invalid') && page.message.className.endsWith('success'), 'Choosing a valid size clears the inline validation state');
page.size('S');
check(page.message.textContent === 'Size S · 2 available' && !page.submit(), 'Size change immediately shows remaining two and permits submission');
page.colour(1);
check(page.message.textContent === 'Size S is unavailable' && page.button.disabled && page.submit(), 'Colour change retains selected size and blocks its zero availability');
check(page.outputs['selected-price'].textContent === 'SGD $44.00' && page.outputs['selected-colour-name'].textContent === 'Ivory'
    && page.galleries[0].hidden && !page.galleries[1].hidden, 'Colour change keeps its matching price and gallery');
page.size('M');
check(page.message.textContent === 'Size M · 1 available' && !page.button.disabled, 'Single remaining unit uses the same wording');
page.colour(0);
check(page.message.textContent === 'Size M is unavailable' && page.button.disabled, 'Changing back immediately rechecks the same size in its colour');
page.size('L');
check(page.message.textContent === 'Size L is unavailable' && page.message.className.endsWith('error'), 'Zero availability has no numeric success message');
page.colour(1);
check(page.message.textContent === 'Size L · 2 available' && !page.button.disabled, 'Available replacement colour re-enables Add');
check(setup('XS').message.textContent === 'Size XS · 5 available', 'A PHP-selected size initializes to the matching quantity');
const invalid = setup('', true);
check(invalid.area.classList.contains('is-invalid') && invalid.message.className.endsWith('error')
    && invalid.message.textContent === 'Please choose a size.', 'Server-rendered missing-size validation survives script initialization');
console.log(checks + ' Product Details checks passed (DOM simulation).');
