( function () {
	'use strict';
	var button = document.getElementById( 'se-runner-action' );
	var status = document.getElementById( 'se-runner-status' );
	var config = window.sprintEngineRunner;
	var pending = false;
	var restart = document.getElementById( 'se-runner-restart' );
	var dialog = document.getElementById( 'se-restart-dialog' );
	if ( ! button || ! status || ! config ) {
		return;
	}
	async function write( restarting ) {
		if ( pending ) {
			return;
		}
		if ( ! restarting && config.confirm && ! window.confirm( config.confirm ) ) {
			return;
		}
		pending = true;
		button.disabled = true;
		if ( restart ) {
			restart.disabled = true;
		}
		button.setAttribute( 'aria-busy', 'true' );
		status.textContent = restarting ? config.restartBusy : config.busy;
		try {
			var response = await fetch( restarting ? config.restartEndpoint : config.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': config.nonce, 'Accept': 'application/json', 'Content-Type': 'application/json' },
				body: JSON.stringify( { expected_attempt_id: config.attemptId } )
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
			if ( restart ) {
				restart.disabled = false;
			}
			button.removeAttribute( 'aria-busy' );
		}
	}
	button.addEventListener( 'click', function () { return write( false ); } );
	if ( restart && dialog && typeof dialog.showModal === 'function' ) {
		restart.addEventListener( 'click', function () {
			if ( ! pending ) {
				dialog.returnValue = 'cancel';
				dialog.showModal();
			}
		} );
		dialog.addEventListener( 'cancel', function () { dialog.returnValue = 'cancel'; } );
		dialog.addEventListener( 'close', function () {
			if ( dialog.returnValue === 'restart' ) {
				return write( true );
			}
		} );
	}
}() );
