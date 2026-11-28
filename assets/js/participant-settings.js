/* Mode-specific settings; no account requests or tab framework. */
(function () {
    'use strict';
    const form = document.getElementById('sprint-engine-participants-form');
    if (!form) return;
    function update() {
        const selected = form.querySelector('input[type="radio"]:checked');
        form.querySelectorAll('[data-participant-mode]').forEach(panel => {
            panel.hidden = !selected || panel.dataset.participantMode !== selected.value;
            if (!panel.hidden) panel.open = true;
        });
        const native = form.querySelector('[data-disable-native]');
        const warning = form.querySelector('[data-native-warning]');
        if (native && warning) warning.hidden = native.checked;
    }
    form.addEventListener('change', update);
    update();
})();
