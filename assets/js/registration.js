/* Optional visibility only. Never read, copy, store or transmit a password. */
(function () {
    'use strict';
    document.querySelectorAll('.se-runner__registration').forEach(form => {
        const input = form.querySelector('input[autocomplete="new-password"]');
        const button = form.querySelector('[data-password-toggle]');
        if (!input || !button || !button.dataset.showLabel || !button.dataset.hideLabel) return;
        function visibility(show) {
            input.type = show ? 'text' : 'password';
            const label = show ? button.dataset.hideLabel : button.dataset.showLabel;
            button.textContent = label;
            button.setAttribute('aria-label', label);
        }
        visibility(false);
        button.addEventListener('click', () => visibility(input.type === 'password'));
        form.addEventListener('submit', () => visibility(false));
        window.addEventListener('pagehide', () => visibility(false));
        button.hidden = false;
    });
})();
