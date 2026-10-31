// Unit test of the actual shared notification script, without a browser.
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
var clock = 0, nextId = 1, timers = new Map(), checks = 0;
function setTimer(callback, delay) { var id = nextId++; timers.set(id, { at: clock + delay, callback }); return id; }
function advance(delay) {
    var end = clock + delay;
    while (true) {
        var next = Array.from(timers).filter(function (entry) { return entry[1].at <= end; })
            .sort(function (a, b) { return a[1].at - b[1].at; })[0];
        if (!next) { break; }
        clock = next[1].at; timers.delete(next[0]); next[1].callback();
    }
    clock = end;
}
function notice() {
    var classes = new Set();
    return { hidden: false, focused: false, listeners: {},
        classList: { add: function (value) { classes.add(value); }, remove: function (value) { classes.delete(value); }, contains: function (value) { return classes.has(value); } },
        contains: function (node) { return node === this; },
        addEventListener: function (name, callback) { this.listeners[name] = callback; }
    };
}
function check(value, label) { assert.ok(value, label); checks++; console.log('PASS: ' + label); }
var normal = notice(), focused = notice(), lateFocus = notice(), error = notice(), confirmation = notice();
var document = { activeElement: null, querySelectorAll: function (selector) {
    check(selector === '[data-auto-dismiss]', 'Only explicitly marked success notices are selected');
    return [normal, focused, lateFocus];
} };
vm.runInNewContext(readFileSync(new URL('../js/main.js', import.meta.url), 'utf8'), { document, setTimeout: setTimer, clearTimeout: function (id) { timers.delete(id); } });
document.activeElement = focused; focused.listeners.focusin();
advance(4999);
check(!normal.hidden && !normal.classList.contains('is-dismissing'), 'Success remains visible for the first five seconds');
advance(1);
check(normal.classList.contains('is-dismissing') && !normal.hidden, 'Success begins a fade after five seconds');
document.activeElement = lateFocus; lateFocus.listeners.focusin();
advance(250);
check(normal.hidden, 'Success hides after the 250ms fade');
check(!focused.hidden && !lateFocus.hidden, 'Keyboard focus pauses dismissal, including focus arriving during the fade');
document.activeElement = null; focused.listeners.focusout({ relatedTarget: null }); lateFocus.listeners.focusout({ relatedTarget: null });
advance(5000);
check(!focused.hidden && focused.classList.contains('is-dismissing'), 'Dismissal restarts when keyboard focus leaves the notice');
advance(250);
check(focused.hidden && lateFocus.hidden, 'Previously focused notices dismiss after focus leaves');
check(!error.hidden && !confirmation.hidden && Object.keys(error.listeners).length === 0 && Object.keys(confirmation.listeners).length === 0,
    'Unmarked errors and permanent order-confirmation content are never scheduled for dismissal');
advance(10000);
check(normal.hidden, 'A dismissed notice stays hidden');
console.log(checks + ' notification checks passed.');
