<?php
/**
 * Disposable fixture helper: simulate rendering the latest attempt before a call.
 * Stale-write/concurrency tests must capture and reuse an explicit prior identity.
 * Never use a server-resolved latest identity as a member transport fallback.
 */
function test_attempt_id( $user, $sprint ) {
	return ( new \ThePath\SprintEngine\Progress\EnrolmentRepository() )->find( $user, $sprint )['id'] ?? 1;
}
