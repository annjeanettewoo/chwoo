// Small DOM substitute tests the actual validation script without dependencies.
// This is a JavaScript unit check, not a browser layout/interaction test.
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

var fields = ['full_name', 'email', 'phone', 'address', 'postal_code', 'notes'];
var outputs = {};
var inputs = fields.map(function (name) {
    outputs[name + '-error'] = { textContent: '', hidden: true };
    return { name: name, value: '', attributes: {}, listeners: {},
        getAttribute: function (key) { return this.attributes[key]; },
        setAttribute: function (key, value) { this.attributes[key] = value; },
        addEventListener: function (event, handler) { this.listeners[event] = handler; },
        focus: function () { focused = this.name; }
    };
});
var focused = '';
var handlers = {};
var form = { noValidate: false, querySelectorAll: function () { return inputs; },
    addEventListener: function (event, handler) { handlers[event] = handler; }
};
var document = { querySelectorAll: function () { return [form]; },
    getElementById: function (id) { return outputs[id]; }
};
vm.runInNewContext(readFileSync(new URL('../js/checkout-validation.js', import.meta.url), 'utf8'), { document: document });
var checks = 0;
function check(condition, description) { assert.ok(condition, description); checks++; console.log('PASS: ' + description); }
function submit() {
    var prevented = false;
    handlers.submit({ preventDefault: function () { prevented = true; } });
    return prevented;
}
check(form.noValidate, 'JavaScript enables custom inline validation while HTML5 attributes remain available without JavaScript');
check(submit() && focused === 'full_name', 'Empty submit is prevented and focuses the first invalid field');
check(inputs.slice(0, 5).every(function (input) { return input.attributes['aria-invalid'] === 'true' && !outputs[input.name + '-error'].hidden; }),
    'Required fields expose accessible inline errors');
check(outputs['notes-error'].hidden, 'Delivery notes remain optional');
var values = ['李 A/L O’Neil', "o'neil+fashion@example.com", '+65 (8123) 4567', '10 Demo Street\n#01-02', '012345', ''];
inputs.forEach(function (input, i) { input.value = values[i]; input.listeners.input(); });
check(!submit(), 'Common valid names, email addresses and formatted phone numbers proceed');
check(inputs.every(function (input) { return input.attributes['aria-invalid'] === 'false'; }), 'Correcting fields clears their errors');
inputs[4].value = '12345';
inputs[4].listeners.blur();
check(outputs['postal_code-error'].textContent.includes('6-digit') && submit(), 'Singapore postal code requires exactly six digits');
inputs[4].value = '012345';
inputs[2].value = '1234567';
check(submit() && outputs['phone-error'].textContent.includes('valid contact'), 'Short phone numbers are rejected');
inputs[2].value = '+44 20 1234 5678';
inputs[1].value = 'missing-at';
check(submit() && outputs['email-error'].textContent.includes('valid email'), 'Malformed email addresses are rejected');
inputs[1].value = 'name+tag@sub.example.com';
inputs[0].value = 'A';
check(!submit(), 'A one-character name and international contact number are not arbitrarily rejected');
inputs[5].value = 'x'.repeat(501);
check(submit() && outputs['notes-error'].textContent.includes('500'), 'Oversized notes are rejected');
inputs[5].value = '';
inputs[0].value = 'Name\nInjected';
check(submit(), 'Control characters in a name are rejected');
console.log(checks + ' JavaScript checks passed.');
