// Checkout Validation — Immediate delivery-field feedback and accessible submission checks.
// PHP independently validates every submission; browser checks can be bypassed or unavailable.
document.querySelectorAll('[data-checkout-form]').forEach(function (form) {
    var inputs = Array.from(form.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]), textarea'));
    // Track interacted/server-invalid fields so typing does not expose errors in untouched fields.
    var touched = {};
    form.noValidate = true;

    // --- Checkout Validation — Shared inline feedback ---
    // Error IDs match PHP-rendered fields; return validity while updating text, visibility and ARIA state.
    function showError(input, error) {
        var output = document.getElementById(input.name + '-error');
        output.textContent = error;
        output.hidden = !error;
        input.setAttribute('aria-invalid', error ? 'true' : 'false');
        return !error;
    }

    // --- Checkout Validation — Delivery field rules ---
    // Each field check returns an error string or empty string; optional notes are handled by validate().
    function validateName(value) {
        if (value === '') { return 'Please enter your full name.'; }
        if (Array.from(value).length > 120 || /[\x00-\x1F\x7F]/.test(value)) {
            return 'Please use up to 120 characters on one line for your full name.';
        }
        return '';
    }

    function validateEmail(value) {
        // Accept common addresses including plus tags, apostrophes and subdomains.
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return value.length <= 190 && emailPattern.test(value) ? '' : 'Please enter a valid email address.';
    }

    function validatePhone(value) {
        var phonePattern = /^\+?[0-9][0-9 ()-]*$/;
        var digits = value.replace(/[^0-9]/g, '');
        return value.length <= 30 && phonePattern.test(value) && digits.length >= 8 && digits.length <= 15
            ? '' : 'Please enter a valid contact number.';
    }

    function validateAddress(value) {
        if (value === '') { return 'Please enter your delivery address.'; }
        return Array.from(value).length <= 1000 ? '' : 'Please use up to 1000 characters for your delivery address.';
    }

    function validatePostalCode(value) {
        return /^[0-9]{6}$/.test(value) ? '' : 'Please enter a valid 6-digit Singapore postal code.';
    }

    // Dispatch by field name, then reject control characters and update that field's feedback.
    function validate(input) {
        var value = input.value.trim();
        var error = '';
        if (input.name === 'full_name') { error = validateName(value); }
        if (input.name === 'email') { error = validateEmail(value); }
        if (input.name === 'phone') { error = validatePhone(value); }
        if (input.name === 'address') { error = validateAddress(value); }
        if (input.name === 'postal_code') { error = validatePostalCode(value); }
        if (input.name === 'notes' && Array.from(value).length > 500) { error = 'Please use up to 500 characters for delivery notes.'; }
        if (/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/.test(value)) { error = 'Please check the text entered in this field.'; }
        return showError(input, error);
    }

    // --- Checkout Validation — Blur, correction and submit interactions ---
    // PHP-rendered errors count as touched so corrections update their feedback immediately.
    inputs.forEach(function (input) {
        touched[input.name] = input.getAttribute('aria-invalid') === 'true';
        input.addEventListener('blur', function () { touched[input.name] = true; validate(input); });
        input.addEventListener('input', function () { if (touched[input.name]) { validate(input); } });
    });
    // Check every field before ordinary POST; focus the first error so keyboard users can correct it.
    form.addEventListener('submit', function (event) {
        var firstInvalid = null;
        inputs.forEach(function (input) {
            touched[input.name] = true;
            if (!validate(input) && !firstInvalid) { firstInvalid = input; }
        });
        if (firstInvalid) { event.preventDefault(); firstInvalid.focus(); }
    });
});
