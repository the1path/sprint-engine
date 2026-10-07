/* global jQuery, sprintEngineStructure */
( function ( $ ) {
	'use strict';

	function refresh( manager ) {
		const rows = manager.find( '.se-linear-row' );
		rows.each( function ( index ) {
			const row = $( this );
			row.find( '.se-position' ).text( index + 1 );
			row.find( '.se-boundary' ).text( [ index === 0 ? sprintEngineStructure.start : '', index === rows.length - 1 ? sprintEngineStructure.final : '' ].filter( Boolean ).join( ' ' ) );
			row.find( '.se-next-title' ).text( index + 1 < rows.length ? rows.eq( index + 1 ).find( '.se-step-title' ).text() : sprintEngineStructure.end );
			row.find( '[data-direction="up"]' ).prop( 'disabled', index === 0 );
			row.find( '[data-direction="down"]' ).prop( 'disabled', index === rows.length - 1 );
		} );
		manager.find( '.se-start-title' ).text( rows.length ? rows.first().find( '.se-step-title' ).text() : sprintEngineStructure.none );
	}

	function changed( manager ) {
		manager.data( 'dirty', true );
		manager.find( '.se-add-step,.se-remove-step' ).prop( 'disabled', true );
		manager.find( '.se-structure-status' ).removeClass( 'se-error' ).text( sprintEngineStructure.unsaved );
		refresh( manager );
	}

	function initialize( manager ) {
		manager.find( '.se-linear-list' ).sortable( {
			items: '.se-linear-row',
			handle: '.se-drag',
			cancel: 'input,textarea,select,a,.se-move',
			axis: 'y',
			update: function () { changed( manager ); }
		} );
		refresh( manager );
	}

	function save( manager, operation, step ) {
		if ( manager.data( 'busy' ) ) { return; }
		const title = manager.find( '.se-new-step-title' ).val().trim();
		if ( operation === 'remove' && manager.data( 'dirty' ) ) {
			manager.find( '.se-structure-status' ).text( sprintEngineStructure.unsaved );
			return;
		}
		if ( operation === 'add' && ( manager.data( 'dirty' ) || ! title ) ) {
			manager.find( '.se-structure-status' ).text( manager.data( 'dirty' ) ? sprintEngineStructure.unsaved : sprintEngineStructure.title );
			return;
		}
		manager.data( 'busy', true );
		manager.find( 'button,input' ).prop( 'disabled', true );
		manager.find( '.se-linear-list' ).sortable( 'disable' );
		manager.find( '.se-structure-status' ).removeClass( 'se-error' ).text( sprintEngineStructure.saving );
		const order = manager.find( '.se-linear-row' ).map( function () { return $( this ).attr( 'data-step' ); } ).get();
		const data = { action: 'sprint_engine_structure', sprint: manager.attr( 'data-sprint' ), nonce: manager.attr( 'data-nonce' ), revision: manager.attr( 'data-revision' ), operation: operation };
		if ( operation === 'remove' ) { data.step = step; }
		if ( operation === 'add' ) { data.title = title; }
		if ( operation === 'order' ) { data.order = JSON.stringify( order ); }
		$.ajax( {
			url: sprintEngineStructure.url,
			method: 'POST',
			dataType: 'json',
			data: data
		} ).done( function ( response ) {
			if ( ! response || ! response.success || ! response.data || ! response.data.html ) {
				failed( response );
				return;
			}
			const replacement = $( response.data.html );
			manager.replaceWith( replacement );
			initialize( replacement );
			replacement.find( '.se-structure-status' ).text( operation === 'remove' ? sprintEngineStructure.removed : sprintEngineStructure.saved );
			replacement.find( operation === 'order' ? '.se-save-order' : '.se-new-step-title' ).trigger( 'focus' );
		} ).fail( function ( xhr ) { failed( xhr.responseJSON ); } );

		function failed( response ) {
			// Preserve the proposed order, but require a reload after uncertain failures.
			manager.data( 'busy', false );
			manager.find( '.se-structure-status' ).addClass( 'se-error' ).text( response && response.data && response.data.message ? response.data.message + ' ' + sprintEngineStructure.reload : sprintEngineStructure.error );
		}
	}

	function activateAfterSave( placeholder ) {
		const data = window.wp && window.wp.data;
		if ( ! data || typeof data.select !== 'function' || typeof data.subscribe !== 'function' ) { return; }
		const editor = data.select( 'core/editor' );
		const selectors = [ 'isSavingPost', 'isAutosavingPost', 'didPostSaveRequestSucceed', 'getCurrentPostAttribute', 'getCurrentPostId' ];
		if ( ! editor || ! selectors.every( function ( name ) { return typeof editor[ name ] === 'function'; } ) ) { return; }
		let explicitSave = false;
		let busy = false;
		const unsubscribe = data.subscribe( function () {
			const current = data.select( 'core/editor' );
			if ( current.isSavingPost() ) {
				if ( ! current.isAutosavingPost() ) { explicitSave = true; }
				return;
			}
			if ( ! explicitSave ) { return; }
			explicitSave = false;
			if ( busy || ! current.didPostSaveRequestSucceed() || current.getCurrentPostAttribute( 'status' ) === 'auto-draft' || String( current.getCurrentPostId() ) !== placeholder.attr( 'data-sprint' ) ) { return; }
			busy = true;
			$.ajax( {
				url: sprintEngineStructure.url,
				method: 'POST',
				dataType: 'json',
				data: { action: 'sprint_engine_structure', operation: 'refresh', sprint: placeholder.attr( 'data-sprint' ), nonce: placeholder.attr( 'data-nonce' ) }
			} ).done( function ( response ) {
				if ( ! response || ! response.success || ! response.data || ! response.data.html ) { failed(); return; }
				const replacement = $( response.data.html );
				placeholder.replaceWith( replacement );
				initialize( replacement );
				unsubscribe();
			} ).fail( failed );
			function failed() {
				busy = false;
				placeholder.find( '.se-structure-status' ).addClass( 'se-error' ).text( sprintEngineStructure.refreshError );
			}
		} );
	}

	$( function () {
		$( '.se-linear-manager' ).each( function () { initialize( $( this ) ); } );
		$( '.se-structure-locked' ).each( function () { activateAfterSave( $( this ) ); } );
	} );
	$( document ).on( 'click', '.se-move', function () {
		const row = $( this ).closest( '.se-linear-row' );
		const manager = row.closest( '.se-linear-manager' );
		if ( manager.data( 'busy' ) ) { return; }
		if ( $( this ).attr( 'data-direction' ) === 'up' ) { row.insertBefore( row.prev() ); } else { row.insertAfter( row.next() ); }
		changed( manager );
		$( this ).trigger( 'focus' );
	} ).on( 'click', '.se-save-order,.se-add-step', function () {
		save( $( this ).closest( '.se-linear-manager' ), $( this ).hasClass( 'se-add-step' ) ? 'add' : 'order' );
	} ).on( 'click', '.se-remove-step', function () {
		const row = $( this ).closest( '.se-linear-row' );
		const manager = row.closest( '.se-linear-manager' );
		if ( manager.data( 'busy' ) ) { return; }
		if ( manager.data( 'dirty' ) ) {
			manager.find( '.se-structure-status' ).text( sprintEngineStructure.unsaved );
			return;
		}
		if ( window.confirm( sprintEngineStructure.confirm.replace( '%s', row.find( '.se-step-title' ).text() ) ) ) {
			save( manager, 'remove', row.attr( 'data-step' ) );
		}
	} ).on( 'keydown', '.se-new-step-title', function ( event ) {
		if ( event.key === 'Enter' ) {
			event.preventDefault();
			save( $( this ).closest( '.se-linear-manager' ), 'add' );
		}
	} );
	$( window ).on( 'beforeunload', function ( event ) {
		if ( $( '.se-linear-manager' ).toArray().some( function ( node ) { return $( node ).data( 'dirty' ) || $( node ).data( 'busy' ); } ) ) {
			event.preventDefault();
			event.originalEvent.returnValue = '';
		}
	} );
}( jQuery ) );
