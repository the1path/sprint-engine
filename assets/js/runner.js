( function () {
	'use strict';
	var button = document.getElementById( 'se-runner-action' );
	var status = document.getElementById( 'se-runner-status' );
	var config = window.sprintEngineRunner;
	var pending = false;
	if ( ! button || ! status || ! config ) {
		return;
	}
	button.addEventListener( 'click', async function () {
		if ( pending ) {
			return;
		}
		if ( config.confirm && ! window.confirm( config.confirm ) ) {
			return;
		}
		pending = true;
		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );
		status.textContent = config.busy;
		try {
			var response = await fetch( config.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': config.nonce, 'Accept': 'application/json' }
			} );
			var data = await response.json();
			if ( ! response.ok || ! data || data.success !== true ||
				! [ 'in_progress', 'completed' ].includes( data.state ) ||
				data.runner_url !== config.runnerUrl ) {
				throw new Error( 'Unconfirmed response' );
			}
			window.location.assign( config.runnerUrl );
		} catch ( error ) {
			status.textContent = config.error;
			pending = false;
			button.disabled = false;
			button.removeAttribute( 'aria-busy' );
		}
	} );
}() );
