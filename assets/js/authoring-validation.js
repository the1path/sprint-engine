/* Immediate author feedback. PHP remains authoritative for every write. */
(function () {
	'use strict';
	wp.domReady(function () {
		const config = window.sprintEngineAuthoringValidation;
		const root = document.getElementById('sprint_engine_structure');
		if (!config || !root) return;
		const fields = Array.from(root.querySelectorAll('[data-se-error]'));
		const lock = 'sprint-engine-authoring-validation';
		const editor = config.blockEditor ? wp.data.dispatch('core/editor') : null;
		const notices = config.blockEditor ? wp.data.dispatch('core/notices') : null;
		let blocked = null;

		function show(controls, message) {
			const output = document.getElementById(controls[0].dataset.seError);
			const text = output.querySelector('strong');
			if (text.textContent !== message) text.textContent = message;
			output.hidden = !message;
			controls.forEach(function (field) {
				const descriptions = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(id => id && id !== output.id);
				if (message) {
					field.setAttribute('aria-invalid', 'true');
					descriptions.push(output.id);
				} else {
					field.removeAttribute('aria-invalid');
				}
				if (descriptions.length) field.setAttribute('aria-describedby', descriptions.join(' '));
				else field.removeAttribute('aria-describedby');
			});
		}

		function sync() {
			const invalid = fields.some(field => field.getAttribute('aria-invalid') === 'true');
			if (invalid === blocked) return invalid;
			blocked = invalid;
			if (editor) {
				if (invalid) {
					editor.lockPostSaving(lock);
					notices.createErrorNotice(config.notice, {id: lock, isDismissible: false});
				} else {
					editor.unlockPostSaving(lock);
					notices.removeNotice(lock);
				}
			}
			if (!invalid) document.querySelectorAll('.se-authoring-server-notice[data-field-error="true"]').forEach(el => { el.hidden = true; });
			return invalid;
		}

		function validUrl(value) {
			// Check raw input: URL() alone would repair spaces, slashes and missing schemes.
			if (!/^https?:\/\//i.test(value) || /[\s<>\\]/.test(value) || /[^\x21-\x7e]/.test(value)) return false;
			try {
				const url = new URL(value);
				const authority = value.match(/^https?:\/\/([^/?#]+)/i);
				if (!authority || !url.hostname) return false;
				const host = authority[1].replace(/^.*@/, '').replace(/:\d*$/, '');
				return /^\[[0-9a-f:]+\]$/i.test(host) || host.split('.').every((label, i, labels) =>
					(label === '' && i === labels.length - 1) || /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i.test(label));
			} catch (error) { return false; }
		}

		function validate(field) {
			const value = field.value;
			let message = '';
			let controls = [field];
			if (field.id === '_sprint_engine_estimated_duration_value') {
				if (value !== '' && (!/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/.test(value) || /\s/.test(value) || Number(value) <= 0)) message = config.duration;
			} else if (field.id === '_sprint_engine_estimated_minutes') {
				if (value !== '' && (!/^[1-9][0-9]*$/.test(value) || /\s/.test(value) || value.length > config.maxInteger.length ||
					(value.length === config.maxInteger.length && value > config.maxInteger))) message = config.minutes;
			} else if (field.id.startsWith('_sprint_engine_completion_cta_')) {
				controls = fields.filter(item => item.id.startsWith('_sprint_engine_completion_cta_'));
				// Match the meaningful plain-text label after WordPress text sanitization.
				const label = document.getElementById('_sprint_engine_completion_cta_label').value.replace(/<[^>]*>/g, '').replace(/%[a-f0-9]{2}/gi, '').trim();
				const url = document.getElementById('_sprint_engine_completion_cta_url').value;
				if ((label || url) && (!label || !validUrl(url))) message = config.cta;
			}
			show(controls, message);
		}

		fields.forEach(function (field) {
			// Keep server fallback visible until the author interacts with its control.
			if (!fields.some(item => item.dataset.seError === field.dataset.seError && item.getAttribute('aria-invalid') === 'true')) validate(field);
			['input', 'change', 'blur'].forEach(event => field.addEventListener(event, function () {
				validate(field);
				sync();
			}));
		});
		sync();
		if (!config.blockEditor) {
			const form = document.getElementById('post');
			if (form) form.addEventListener('submit', function (event) {
				fields.forEach(validate);
				if (sync()) {
					event.preventDefault();
					fields.find(field => field.getAttribute('aria-invalid') === 'true').focus();
				}
			});
		}
	});
}());
