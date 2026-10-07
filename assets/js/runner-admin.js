(function () {
	'use strict';
	document.addEventListener('change', function (event) {
		if (event.target.id === 'post_name' && document.getElementById('sprint_engine_runner_url') && window.wp && wp.data && wp.data.select('core/editor')) {
			wp.data.dispatch('core/editor').editPost({ slug: event.target.value });
		}
	});
	document.addEventListener('click', async function (event) {
		if (event.target.id !== 'se-copy-runner-url') { return; }
		var field = document.getElementById('se-runner-url');
		var status = document.getElementById('se-copy-runner-status');
		try {
			if (!navigator.clipboard) { throw new Error('Clipboard unavailable'); }
			await navigator.clipboard.writeText(field.value);
			status.textContent = sprintEngineRunnerAdmin.copied;
		} catch (error) {
			field.focus();
			field.select();
			status.textContent = sprintEngineRunnerAdmin.fallback;
		}
	});
}());
