/* global wp, sprintEngineTutorial */
wp.domReady(function () {
	wp.data.dispatch('core/notices').createInfoNotice(sprintEngineTutorial.notice, {
		id: 'sprint-engine-tutorial-admin-only',
		isDismissible: false
	});
});
