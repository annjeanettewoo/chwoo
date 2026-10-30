// Progressive enhancement: ordinary POST forms still work without JavaScript.
document.querySelectorAll('[data-auth-form]').forEach(function (form) {
    var register = form.dataset.authForm === 'register';
    var inputs = Array.from(form.querySelectorAll('input:not([type="hidden"])'));
    var touched = {};
    form.noValidate = true;

    function message(input) {
        var value = input.type === 'password' ? input.value : input.value.trim();
        if (!value) {
            return {full_name: 'Please enter your name.', email: 'Please enter your email address.',
                password: 'Please enter your password.', confirm_password: 'Please confirm your password.'}[input.name];
        }
        if (input.name === 'password') {
            if (register && Array.from(value).length < 8) { return 'Your password must contain at least 8 characters.'; }
            // Match PHP's bcrypt limit without changing the entered value.
            if (new TextEncoder().encode(value).length > 72) { return 'This password is too long. Please use a shorter password.'; }
            if (value.includes('\0')) { return 'Please choose a different password.'; }
        }
        if (input.name === 'confirm_password' && value !== form.elements.password.value) { return 'The passwords do not match.'; }
        return '';
    }

    // Case Study style: named checks, pattern.test(), string checks and booleans.
    function validateName(input) {
        var value = input.value;
        var namePattern = /^\p{L}[\p{L}\p{M} .'’\-]*$/u;
        var error = '';
        if (value.trim() === '') { error = 'Please enter your full name.'; }
        else if (value.charAt(0) === ' ' || value.charAt(value.length - 1) === ' ' || value.indexOf('  ') !== -1) {
            error = 'Please remove the extra spaces in your name.';
        } else if (namePattern.test(value) === false) { error = 'Please check the characters in your name.'; }
        else if (Array.from(value).length < 2 || Array.from(value).length > 120) { error = 'Please use 2 to 120 characters for your full name.'; }
        return showError(input, error);
    }

    function validateEmail(input) {
        var value = input.value;
        var emailPattern = /^[A-Za-z0-9!#$%&'*+\/=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&'*+\/=?^_`{|}~-]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/;
        var error = '';
        if (value === '') { error = 'Please enter your email address.'; }
        else if (value.length > 190 || value.indexOf('..') !== -1 || emailPattern.test(value) === false) {
            error = 'Please enter a valid email address, such as name@example.com.';
        }
        return showError(input, error);
    }

    function validate(input) {
        if (input.name === 'full_name') { return validateName(input); }
        if (input.name === 'email') { return validateEmail(input); }
        return showError(input, message(input));
    }

    function showError(input, error) {
        var output = document.getElementById(input.name + '-error');
        output.textContent = error;
        output.hidden = !error;
        input.setAttribute('aria-invalid', error ? 'true' : 'false');
        return !error;
    }

    inputs.forEach(function (input) {
        touched[input.name] = input.getAttribute('aria-invalid') === 'true';
        input.addEventListener('blur', function () { touched[input.name] = true; validate(input); });
        input.addEventListener('input', function () {
            if (touched[input.name]) { validate(input); }
            if (input.name === 'password' && register && touched.confirm_password) { validate(form.elements.confirm_password); }
        });
    });
    form.addEventListener('submit', function (event) {
        var firstInvalid = null;
        inputs.forEach(function (input) {
            touched[input.name] = true;
            if (!validate(input) && !firstInvalid) { firstInvalid = input; }
        });
        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.focus();
        }
    });
});
