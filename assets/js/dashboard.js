( function () {
	'use strict';
	var config = window.sprintEngineDashboard;
	if ( ! config ) {
		return;
	}
	document.addEventListener( 'click', async function ( event ) {
		var button = event.target.closest( '[data-sprint-engine-restart]' );
		if ( ! button || button.disabled ) {
			return;
		}
		var card = button.closest( '.se-dashboard__card' );
		var status = card && card.querySelector( '[role="status"]' );
		if ( ! status || ! window.confirm( config.confirm ) ) {
			return;
		}
		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );
		status.textContent = config.busy;
		try {
			var endpoint = new URL( button.dataset.endpoint, window.location.href );
			var runner = new URL( button.dataset.runnerUrl, window.location.href );
			if ( endpoint.origin !== window.location.origin || runner.origin !== window.location.origin ) {
				throw new Error( 'Invalid destination' );
			}
			var response = await fetch( endpoint.href, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': config.nonce, 'Accept': 'application/json' }
			} );
			var data = await response.json();
			if ( ! response.ok || ! data || data.success !== true || data.state !== 'in_progress' ||
				data.runner_url !== button.dataset.runnerUrl ) {
				throw new Error( 'Unconfirmed response' );
			}
			window.location.assign( runner.href );
		} catch ( error ) {
			status.textContent = config.error;
			button.disabled = false;
			button.removeAttribute( 'aria-busy' );
		}
	} );
}() );
