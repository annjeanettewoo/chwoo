import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const events = new EventTarget();
let reloads = 0;
runInNewContext(readFileSync(new URL('../js/checkout-history.js', import.meta.url), 'utf8'), {
    window: {
        addEventListener: events.addEventListener.bind(events),
        location: { reload() { reloads++; } }
    }
});

function show(persisted) {
    const event = new Event('pageshow');
    Object.defineProperty(event, 'persisted', { value: persisted });
    events.dispatchEvent(event);
}
show(false);
assert.equal(reloads, 0, 'Normal navigation leaves the checkout untouched');
show(true);
assert.equal(reloads, 1, 'A frozen history page reloads current server state');
show(false);
assert.equal(reloads, 1, 'The resulting fresh page does not enter a reload loop');
console.log('3 checkout history checks passed (event simulation; no real browser).');
