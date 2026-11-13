# Upgrade integrations to Sprint Engine Core 0.3.0

Update custom Complete Step and Restart clients before upgrading from Core
0.2.2. Core 0.3.0 binds both writes to the attempt the participant saw so an
action from an older tab cannot change a replacement attempt. The built-in
Runner and My Sprints Dashboard send the required identity. Refresh tabs that
were open before the upgrade so they load the updated interface and scripts.

## REST requests

The POST paths under `/wp-json/sprint-engine/v1` are unchanged. Use WordPress's
configured REST URL, the signed-in participant's session and the `X-WP-Nonce`
header with a `wp_rest` nonce for these requests.

| Path | Core 0.2.2 | Core 0.3.0 |
| --- | --- | --- |
| `/sprints/{id}/start` | Accepts a bodyless request and returns canonical state. | Same request and response contract. |
| `/steps/{id}/complete` | Accepts a bodyless request against the current attempt. | Requires `expected_attempt_id` for the displayed attempt. |
| `/sprints/{id}/restart` | Accepts a bodyless request. Replaces a completed attempt. For an active attempt it returns the existing state. | Requires `expected_attempt_id`. A valid restart of an active or completed attempt creates a replacement. |

Send JSON with `Content-Type: application/json` on Complete Step and Restart:

```json
{"expected_attempt_id":123}
```

Use the positive `attempt_id` from the authorised state used to render the
participant's view, or from the successful Start, Complete or Restart response
used to render the next view. For server-rendered custom PHP views, capture
`attempt_id` from `ProgressService::get_state()` at render time after checking
the participant's identity and Sprint access. Keep the captured ID with the
specific view and its controls. `attempt_number` is the sequential run number.
Use `attempt_id` in the request. Core exposes no public member progress GET.

An authorised request for an available resource receives HTTP **400**
(`sprint_engine_write_400`) if the required identity is missing or invalid.
Send a positive JSON integer or its decimal string representation. An identity
that no longer matches the participant's current attempt receives HTTP **409**
(`sprint_engine_write_409`). A valid current identity can still receive 409
if progress conflicts, for example after the Sprint's Steps are reordered.

On 409, reload the canonical Runner or fetch fresh state on your authorised
server and re-render the custom view. Let the participant review the current
Step or attempt and choose the action again. Never look up the latest attempt
ID at write time to fill a missing ID or retry a stale action. Doing so would
apply the old action to a run the participant may never have seen.

## Direct PHP calls and attempt history

Pass the captured ID as the last argument to `ProgressService`. Member-facing
PHP callers must enforce authentication, bind `$user_id` to the signed-in
participant, and check Sprint availability and `AccessManager` authorisation.
See the [progress service guide](development.md#internal-progress-service)
for the guard used to repeat member checks inside the transaction.

```php
$progress = new \ThePath\SprintEngine\Progress\ProgressService();
$result = $progress->complete_step( $user_id, $sprint_id, $step_id, $displayed_attempt_id );
// For a separate Restart action:
$result = $progress->restart_sprint( $user_id, $sprint_id, $displayed_attempt_id );
```

Check `is_wp_error()` before using the returned state. The old three-argument
Complete and two-argument Restart calls now return
`sprint_engine_progress_attempt`. An invalid ID returns the same error, and a
stale ID returns `sprint_engine_progress_stale_attempt`. Re-render on a stale
attempt or a progress conflict.

The `get_attempt_history()` payload gains the `restarted` status for an
unfinished attempt superseded by Restart. Keep that attempt in History as a
read-only record and retain its stored Step progress. Completed attempts keep
their `completed` status after Restart. Core 0.3.0 uses the existing database
schema **2** and requires no new data migration from 0.2.2.
