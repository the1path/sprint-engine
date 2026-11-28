# Participant Accounts (SE-031.2 / SE-031.3, unreleased)

WordPress.org still serves Core **0.3.0**. Development source supports conditional
native participant registration, external-provider handoffs and invitation-only
access. See the [SE-031.3 issue](discovery/se-031-3-issue.md),
[security setup guide](participant-registration-security.md) and
[validation](validation/se-031-3-native-registration.md). Publication is separate.

## Administrator setup

Open **Sprints → Settings → Participant Accounts**, choose a mode and save.
Branding and Tutorial retain separate forms. Settings shows the selected mode;
without JavaScript, open its native disclosure. Switching modes retains saved
URLs and the native-form preference. Invalid submissions preserve the complete
previous configuration. Settings tabs are deferred.

| Mode | Behaviour |
| --- | --- |
| No saved choice | Existing native login redirect/public gate; scoped persistent setup notice |
| Sprint Engine registration | Four-field form on eligible known-open guest Sprints, only after explicit security confirmation and successful objective checks |
| Existing registration system | Validated same-site registration/login destinations; provider owns its forms and policy |
| Invitation only | Sign-in, native recovery and contact-administrator guidance; no Core signup offer |

Only administrators with `manage_options` see setup notices on Core admin screens
and WordPress Dashboard. Valid external/invitation setup resolves the notice.
Built-in remains unconfirmed until a deliberate save checks the operator
confirmation. **Old SE-031.2 pending preferences cannot activate registration or
native closure.** Core never changes WordPress's registration flag or roles.

Built-in requires single-site WordPress, real correctly configured HTTPS on home
and login, effective “Anyone can register”, default role Subscriber with `read`
and no granted role capabilities beyond `read`/`level_0`, and a supported atomic
store. Review Settings → General and the security guide. UI-dependent approval,
verification, CAPTCHA, SSO/passwordless or other incompatible registration policies
must use their existing provider; Core cannot reproduce provider-only form flows.
Operator confirmation is not automatic security certification.

Confirmation is a fresh 256-bit random generation plus a server HMAC bound to home/site URLs, network status,
registration flag, default role, Subscriber capabilities and the policy revision.
Changing these native options, detecting a changed configuration or structurally
unsupported/corrupt storage, switching modes or deactivating Core revokes it.
Repair/restoration/reactivation requires a new operator save. Each checked save
rotates the generation; old forms stay invalid after temporary state cleanup.
A non-HTTPS request is refused without letting arbitrary HTTP traffic erase the
site's confirmation or reopen its already-confirmed HTTPS native gate;
HTTPS/home configuration must be fixed at the host. Transient contention, rate
limits, capacity or database unavailability does not silently revoke consent or
open a native-registration bypass.

## Participant journey

An eligible guest follows a canonical Sprint URL and enters first name, last name,
email and chosen password. Names accept international Unicode, trim surrounding
whitespace and reject blank, control, markup, structured or overlong values
(100 Unicode characters / 400 bytes each). Email uses native WordPress validation
and the 100-byte users-table boundary. Passwords require 12 Unicode characters,
at most 4,096 bytes, valid UTF-8, no ASCII controls or leading/trailing native-trim
whitespace. Credentials are never silently truncated or altered. Password-manager
autocomplete and paste remain native; the form works without JavaScript.

Core generates a random hidden username, stores names in native WP fields and
creates a fixed Subscriber through `wp_insert_user()`. It honours
`user_registration_email`, `register_post`, `registration_errors` and native
insertion hooks, rechecks policy/final identity/uniqueness under its mutex and
verifies the resulting role/capabilities and credential. No browser ID, role,
metadata, Sprint selector or return target is accepted. Native admin-only
`wp_send_new_user_notifications( $id, 'admin' )` runs once after successful
insertion and lock release; Core sends no participant setup/welcome email.

Authentication uses **only `wp_signon()`** and native policy/cookies. An opaque
browser receipt records the expected identity, grants no access and requires a
fresh native-authenticated canonical request. Normal member AccessManager and
Availability checks then select Ready, denied or unavailable. **Registration and
Ready create no attempt. Start Sprint remains explicit.** Native MFA may
challenge/redirect/exit; Core does not retry with a weaker API or synthesize auth.

If an account persisted but mail/authentication/cookies or a callback failed,
retain it and use native sign-in/recovery and the original Sprint link. Do not
delete/recreate it. Existing members can sign in with email and use native reset.
No mandatory email verification is added: email ownership remains unverified,
names are not marketing consent, and squatting/recovery risk remains. Automatic
email/cross-device return or arbitrary provider/MFA compatibility is not promised.

Guest screens use existing Runner branding/controls and concise neutral Sign in
copy. They expose no private Sprint title/overview/image/Step, progress, attempt
or REST write configuration. Unknown/restricted/unavailable/tutorial content never
gets a signup promise; marker presence remains restrictive even when malformed.
Safe existing SE-017 information actions retain their placement.

## Native form closure

The suggested-checked **Disable the standard WordPress registration form** choice
activates only with explicitly confirmed, valid built-in operation. Opt-out stays
saved across mode switches and displays a warning. Its narrow
`login_form_register` hook refuses resolved GET/POST register dispatch with a
safe 403, native Sign in/recovery and an optional exact canonical, freshly eligible
Sprint link. It does not change `users_can_register`, native login/reset/logout,
admin-created users, WooCommerce or independent provider forms.

The native Register link remains: there is no supported narrowly scoped removal
that warrants brittle markup rewriting/global login takeover. Following it gives
clear blocked-route guidance. Providers using that endpoint are affected.
**Other account endpoints need their own protection.** With unchecked,
unconfigured/invalidated mode, structural failure or deactivation, native
registration follows existing WordPress policy. Protect that reopened route.
Temporary rate/lock/capacity rejection keeps an already-valid native gate closed.

## External destinations and canonical return

Use full exact-home-origin URLs within its subdirectory. HTTPS is required;
SE-031.2's local-loopback HTTP allowance applies only to delegated destinations,
never built-in signup. Published passwordless pages and simple provider routes
are supported, without fetching them. Reject user-info, fragments, controls,
backslashes, nested encoding/redirects, secret/reset parameters, mutation/admin
routes and Runner/Dashboard loops. Destinations are bounded to 2,048 bytes and
revalidated when rendered; invalid optional login independently falls back to
native login.

The provider receives only server-derived canonical `redirect_to`, encoded as one
query value like `wp_login_url()`. One query decode preserves international
percent-encoded slugs, subdirectory installations and existing provider queries.
Return hints are bounded to 2,048 bytes and final links to 4,096. Native login/
recovery filters remain authoritative. If a provider ignores the hint, use the
visible original Sprint link. Configure and test the provider's final return.

## Security and temporary lifecycle

A Secure HttpOnly **__Host-** cookie, Path=/, no Domain, SameSite=Strict holds a
signed random browser secret for 30 minutes. Each form has a random signed
register/action/Sprint/browser/confirmation token valid for 20 minutes. Both are mandatory;
supplied incompatible Origin/Referer/fetch metadata is rejected. Missing/null
provenance is not trust. Forms never put secrets/identifiers in URLs or browser
storage. Tabs share browser state but have independent form IDs. Success clears
the receipt and browser cookie on the fresh identity check; stale tabs must reload.
Blocked/expired/missing cookies require a fresh form; an opaque 20-minute receipt
supports interrupted authentication, never login authority.

One non-autoloaded `sprint_engine_registration_state` option stores bounded JSON
containing HMAC keys, counts and UTC expiry only. Limits: 2,048 entries / 262,144
bytes; submission budgets of 100/site and 40/actual peer per 10-minute fixed window,
6/browser per 10 minutes, 3/submitted normalised email per hour; replay entries last
20 minutes. Validation failures consume reserved budgets. No raw email/IP/password/
browser token is stored there. Fixed windows allow boundary bursts; shared peers
and proxies share a budget. Core ignores forwarded IP headers. This limits
form-driven writes, not all signup routes, login guessing or DDoS.

Explicit confirmation first proves bounded state creation/write/read-back under
the mutex. Missing live state is structural invalidation, never a silent reset,
including deletion after readiness while a request waits for the mutex. Only
explicit unconfirmed setup can create the row; registration/purge cannot. Recovery
requires a fresh operator confirmation generation, invalidating old forms.
Direct writes clear positive, negative and autoload option caches.
StateStore requires native `wpdb`/mysqli, nonpersistent connections, autocommit,
no active transaction and demonstrated multiple named-lock ownership. It uses
fresh direct reads on the original handle and a per-site mutex (one-second wait).
Native wpdb reconnect/retry is disabled inside reservation/validation/insertion,
ownership is checked before writes and final native insertion, and policy is
restored in `finally`. A random savepoint/release probe detects active transactions
on MySQL and MariaDB without committing, rolling back or changing session settings.
Only the exact missing-savepoint error (1305 / 42000) proves no transaction; all
other outcomes refuse operation. `@@autocommit` is checked separately because
explicit transactions can leave it enabled. No transaction spans arbitrary callbacks.
Cleanup releases only the captured connection's named lock even if a callback
opened a transaction; Core leaves that transaction to its caller. Release occurs
before Core's notification/auth. Unsupported drop-ins/pools/engines fail closed;
a health probe is not host certification. See tested and untested stacks in
validation. Other uncoordinated creators can still race; WP email has no global
unique constraint and Core does not change users tables.

Expiry is enforced on submissions; a bounded hourly WP-Cron purge removes expired
hashes. On an inactive configuration it removes the empty temporary row/schedule.
A quiet site/disabled cron can delay physical deletion, never grow the fixed row.
Deactivation removes temporary state/cron and revokes live confirmation, retaining
accounts, names, progress, historical attempts and saved preferences. Existing
uninstall retention remains unchanged. No schema/version/rewrite migration occurs.

## Extension contracts

`AccessManager::participant_access_kind( $sprint_id )` and
`sprint_engine/participant_access_kind` remain presentation-only exact
`open`/`restricted`/`unknown`. Unextended Core's final LoggedInAccessProvider
with no member filters is the only inferred open policy. Custom providers/member
filters default unknown; exactly one coordinated classifier may assert the whole
policy. Multiple callbacks or invalid output become unknown without last-writer
ordering. Guest classification never fabricates a member or grants entitlement.
Core contains no Pro metadata. Old unmodified Pro stays neutral until a separately
approved classifier adapter; authenticated functionality needs no coordinated release.

SE-017 still receives `(false, resolved_id)` once after Core publication/password/
Launchable/structure readiness. Only exact true yields its unchanged error/403
marker. An explicitly known-open registration form may coexist with that marker;
the denial action still runs once after the guest body. Generic errors are never
promoted to access denial. Processing rechecks classification/readiness under the
registration lock without rerunning SE-017's public gate.

Native hooks own their native denial/authentication policy. No temporary password/
role overrides, direct auth-cookie/current-user calls, new REST/account endpoint
or speculative integration SDK is introduced. Trusted PHP can execute/exit/modify
data beyond any Core boundary; Core retains partial accounts and never treats
unexpected outcomes as verified success. Providers requiring their own rendering
or interactive policies must stay delegated.

Custom `runner_template` overrides must render the safe `guest` context (including
optional `form`/`notice`) and existing denial actions, never infer member controls
from classification. Runner errors and forms remain private/no-store/noindex.

The existing `sprint_engine_participant_accounts` option adds only
`confirmation` to `mode`, `registration_url`, `login_url`, `disable_native`.
Old four-field data reads as unconfirmed. Settings API capability/POST/nonce and
strict whole-value validation protect administrator saves; it is not REST exposed.
Runtime classes in `Registration` are internal coordination, not a public account API.
