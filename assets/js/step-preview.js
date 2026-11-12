/* global wp, sprintEngineStepPreview */
( function () {
	'use strict';

	const config = sprintEngineStepPreview;
	const noticeId = 'sprint-engine-step-preview';
	const { createElement: el, useState } = wp.element;
	const MoreMenuItem = wp.editor.PluginMoreMenuItem || wp.editPost.PluginMoreMenuItem;

	function PreviewStep() {
		const [ preparing, setPreparing ] = useState( false );
		const narrowEditor = wp.compose.useViewportMatch( 'medium', '<' );
		const MenuItem = ( ! narrowEditor && wp.editor.PluginPreviewMenuItem ) || MoreMenuItem;
		const notices = wp.data.useDispatch( 'core/notices' );
		const editorActions = wp.data.useDispatch( 'core/editor' );
		const disabled = wp.data.useSelect( ( select ) => {
			const editor = select( 'core/editor' );
			return editor.isSavingPost() || editor.isPostLocked() ||
				( 'auto-draft' === editor.getCurrentPostAttribute( 'status' ) && ! editor.isEditedPostSaveable() );
		}, [] );

		function showError( message ) {
			notices.createErrorNotice( message, { id: noticeId } );
		}

		async function openPreview() {
			const editor = wp.data.select( 'core/editor' );
			const title = editor.getEditedPostAttribute( 'title' );
			const content = editor.getEditedPostContent();
			const changed = editor.isEditedPostDirty() &&
				( title !== editor.getCurrentPostAttribute( 'title' ) || content !== editor.getCurrentPostAttribute( 'content' ) );
			if ( changed && editor.isPostAutosavingLocked() ) {
				showError( config.saveError );
				return;
			}

			const tab = window.open( 'about:blank', '_blank' );
			if ( ! tab ) {
				showError( config.popupBlocked );
				return;
			}
			tab.opener = null;
			tab.document.title = config.loading;
			tab.document.body.textContent = config.loading;
			notices.removeNotice( noticeId );
			setPreparing( true );

			try {
				const url = new URL( config.url );
				if ( changed ) {
					editorActions.lockPostAutosaving( noticeId );
					// Use the native autosave endpoint for content. Step settings follow their own save workflow.
					const data = { title, content };
					if ( 'auto-draft' === editor.getCurrentPostAttribute( 'status' ) ) {
						data.status = 'draft';
					}
					const saved = await wp.apiFetch( { path: config.autosavePath, method: 'POST', data } );
					if ( ! saved?.id ) {
						throw new Error( config.saveError );
					}
					// WordPress can autosave the original draft or return an autosave revision.
					if ( saved.id !== editor.getCurrentPostId() ) {
						url.searchParams.set( config.autosaveParam, saved.id );
					}
				}
				tab.location.replace( url.href );
			} catch {
				tab.close();
				showError( config.saveError );
			} finally {
				if ( changed ) {
					editorActions.unlockPostAutosaving( noticeId );
				}
				setPreparing( false );
			}
		}

		return el( MenuItem, {
			disabled: disabled || preparing,
			'aria-label': config.newTab,
			onClick: openPreview,
		}, config.label );
	}

	wp.plugins.registerPlugin( 'sprint-engine-step-preview', { render: PreviewStep } );
}() );
