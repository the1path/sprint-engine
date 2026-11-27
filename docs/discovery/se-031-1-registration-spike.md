# SE-031.1 — Native Participant Registration Security and Feasibility Spike

Completed **27 November 2026** for [issue #26](https://github.com/the1path/sprint-engine/issues/26).
See the [archived specification](se-031-1-issue.md), [SE-031 discovery](se-031-participant-registration.md)
and [sanitised executed-case evidence](se-031-1-results.json).
**Experimental evidence and implementation recommendation only. Stop for owner review.**

## Decision

**Conditional GO to a small, explicitly enabled single-site registration implementation.**
The preferred journey is technically feasible using WordPress account creation,
password hashing, authentication, cookies and recovery. It does not require a
parallel identity system, WooCommerce, mandatory mailbox verification or a new
login system. Existing-account sign-in and recovery should remain native/provider
handoffs. LifterLMS's separation of rendering, validation and native persistence
is the useful precedent; its full form engine is unnecessary for four fields.

This advances SE-031's source-only conclusion: a disposable form actually created
a Subscriber, authenticated through `wp_signon()`, and reached the exact Sprint's
Ready to Start screen on a **new HTTP request**, with no enrolment/attempt. The
same journey worked with current and floor WordPress/PHP. Actual Two Factor
0.17.0 interrupted sign-in, withheld member access, then completed its own
challenge and returned to the intended Sprint. Native authentication rejection
and cookie suppression did not bypass the member access boundary.

**This is not an unconditional security or release approval.** Supported operation
requires a documented native-password/custom-form-compatible stack, effective WP
registration permission, a checked Subscriber role, proven atomic storage/locking,
HTTPS/cache configuration and protection of every public signup entry point.
Unknown UI-dependent approval, CAPTCHA, verification, passwordless or SSO policy
requires the existing registration system/native UI. Absence of a recognised
security plugin is not evidence of compatibility. Do not add detection heuristics
that turn an unknown policy into permission.

SE-031 **S1 is only partly satisfied**. Local account, recovery, CSRF/replay,
bounded-state, concurrency and selected MFA evidence now exists. Native-path
abuse coverage on the target site, hosting/CDN/TLS, actual mail delivery and the
operator's full security stack are not established. A direct native registration
request bypassed the prototype's limiter, as expected: that limiter is scoped
to its own endpoint. **Do not enable built-in public signup on a site whose
operator cannot establish those prerequisites.** Use existing-system/invitation
mode, or the narrower native handoff with its own secured native endpoint. The
handoff does not itself solve native endpoint abuse.

Pro backwards compatibility is a condition of the proposed design. Existing
unmodified Pro must continue to run before any optional classification adapter
is released. Without explicit guest classification, registration falls back to
ordinary sign-in and current access decisions. Do not require simultaneous
Core/Pro updates to preserve existing member functionality.

## Scope, branch and environment

Read AGENTS.md, the v0.1 architecture/security/Runner/progress specification,
merged SE-031 report and issue snapshot, development guide, SE-017 public gate,
SE-026.1 release, tutorial marker and current access/Runner/REST/attempt contracts.
Pro integration was inspected read-only, including identity/access, entitlement
providers, public gate/Directory, Workspace, history/Workbooks, Notes, Journeys,
Collections and resources. Private source and customer details are not reproduced.

| Item | Actual evidence |
| --- | --- |
| Initial Core checkout | Clean `codex/se-031-registration-discovery`, `0cd958c` |
| Verified base | Fetched `origin/main`, merged PR #25, `4ec50f5745fa59a91d30c861bbd8b4c5353eb231` |
| Spike branch | `codex/se-031-1-registration-spike`, from that exact base; old work retained |
| Shipping source | No runtime/dependency/version/schema/rewrite edits. Version/Stable tag 0.3.0, schema 2, WP 6.0+, PHP 8.0+ unchanged |
| Current executed lane | WordPress 7.1.5, PHP 8.3.36, MariaDB 10.11.11, InnoDB, `sp31_` prefix |
| Exact floor executed lane | WordPress 6.0, PHP 8.0.30, same isolated MariaDB engine, separate database |
| Published Core comparison | Actual WordPress.org Core 0.3.0 archive, distinct from unreleased main; used with the experimental layer and existing Pro |
| Pro | Current integration ref verified against its remote; original checkout clean throughout. Copied runtime: 158 files, zero byte differences from read-only source |
| Security plugin | Actual Two Factor 0.17.0, current WP lane only; its declared WP minimum is 7.0 |
| Commerce | Actual WooCommerce 11.3.1, current WP lane, HPOS enabled; local COD orders only |
| Browser | Installed Chrome through Playwright, 390px viewport, JavaScript disabled for the functional registration journey |
| Isolation | Newly created disposable temporary root, marker guards, new MariaDB data directory/databases, loopback-only HTTP/DB, synthetic `.invalid` accounts, captured local mail, outbound WP HTTP blocked |

The [WP update API](https://api.wordpress.org/core/version-check/1.7/) was rechecked:
7.1.5 remained current; it also listed maintained 6.0.19 for the 6.0 series. The
exact declared floor was executed, not silently described as a maintained release.
PHP 8.6 was not installed/tested; the local current lane is 8.3.36, not a claim
of newest-PHP compatibility. Current Woo requires WP 7.0+, so no attempt was made
to load it or current Two Factor on WP 6.0. These exclusions do not raise Core's floor.

No production site, credentials, real account/order, outgoing email or live payment
was used. The experimental MU plugin, database, mail, tokens and harness remain
outside shipping source and are not committed. No Pro file was edited. No merge,
deployment, publication or release preparation is part of this work.

The v0.1 authenticated identity/progress architecture is preserved. A future
configured guest form would explicitly extend section 8's default login redirect;
its implementation ticket must authorise that presentation exception while keeping
authentication before member content. This spike changes no standing Core contract.

## Established implementations: what to reuse

Versions were pinned from downloaded source, rather than inferred from an old
documentation page. LifterLMS and Tutor LMS were **source-reviewed, not runtime-
certified**; Woo and Two Factor were also executed as described below.

### LifterLMS 11.1.1

The [registration controller](https://github.com/gocodebox/lifterlms/blob/11.1.1/includes/forms/controllers/class.llms.controller.registration.php)
handles its form on `init`, checks its WP nonce, offers an early validation veto,
rejects an already logged-in registrant, calls `llms_register_user()` and redirects
through its own registration filter. The controller passes posted fields to its
form handler, **not directly to WordPress user insertion**. Its nonce is a native
WP nonce; this alone is not proof of browser-bound guest CSRF or one-time use.

The [form handler](https://github.com/gocodebox/lifterlms/blob/11.1.1/includes/forms/class-llms-form-handler.php)
loads configured fields, validates required/matching values and protected storage
destinations, sanitises mapped fields and builds user/meta data. Registration uses
`wp_insert_user()`, a Student default role, generated login/password when absent,
and LifterLMS lifecycle hooks. Existing-account updates are a separate action.
This is a substantial LMS account/form architecture, not a reason to build a Core
form builder, adopt a Student role or copy enrolment/voucher behaviour.

[`llms_register_user()`](https://github.com/gocodebox/lifterlms/blob/11.1.1/includes/functions/llms.functions.person.php)
calls the form handler and, when requested and a password is supplied, uses
`wp_signon()`. The inspected wrapper returns the created ID without checking the
sign-on return value. Reuse native sign-on, but strengthen the outcome handling:
creation and authentication must remain separate, and access must be observed on
a new authenticated request. Another plugin's chosen pattern is not security proof.

[Open-registration policy](https://github.com/gocodebox/lifterlms/blob/11.1.1/includes/llms.functions.core.php)
is an LMS-owned option/filter. A
[registration shortcode](https://github.com/gocodebox/lifterlms/blob/11.1.1/includes/shortcodes/class.llms.shortcode.registration.php)
renders the same form on a chosen page, which is a useful existing-system destination.
Third-party form builders require their own field mapping/validation and post-auth
redirect integration; calling a helper is not a universal external-form contract.
Core must retain this issue's stricter effective WP registration-policy requirement.

The [CAPTCHA abstraction](https://github.com/gocodebox/lifterlms/blob/11.1.1/includes/spam/class-llms-captcha.php)
connects rendering and pre-validation to LMS-specific hooks, with optional reCAPTCHA/
Turnstile implementations. That illustrates why a new form cannot assume another
plugin's CAPTCHA applies automatically. Core should not copy those dependencies,
store IP/profile extras merely because the LMS does, or invoke LMS notification
and purchase/enrolment hooks as if Core had registered an LMS student.

**Useful adaptation:** a small renderer/controller and focused account service,
fixed field allowlist, native WP user storage, a validation veto and native auth.
Keep names optional and technical login generation hidden behind email sign-in.
Use the LMS's existing same-site account page when its own policies are required.

### WooCommerce 11.3.1 and Tutor LMS 4.2.1

| Source | Observed architecture | Core consequence |
| --- | --- | --- |
| [Woo customer creation](https://github.com/woocommerce/woocommerce/blob/11.3.1/plugins/woocommerce/includes/wc-user-functions.php) | Native user insertion with Customer defaults and Woo validation/customer hooks | Use WP directly in standalone Core. Do not fake `woocommerce_created_customer`, convert roles, or run customer/order linking merely to create a participant |
| [Woo form handler](https://github.com/woocommerce/woocommerce/blob/11.3.1/plugins/woocommerce/includes/class-wc-form-handler.php) | Native nonce, store registration setting, form-level validation, customer API, optional registration-auth filter and store redirects; its cookie helper is not the full WP authentication pipeline | Borrow separation and explicit store policy, not direct-cookie sign-in. Existing store UI remains a supported handoff owned by the operator |
| [Tutor Student registration](https://github.com/themeum/tutor/blob/v4.2.1/classes/Student.php) | Checks WP public-registration permission, nonce and required fields; calls registration validation, builds insertion data, supports a verification branch and redirects. The non-verification branch sets current user/auth cookies directly | Useful WP-flag/field validation precedent. Do not copy its direct-cookie path, enrol-after-signup action, generic transaction assumptions or password text sanitisation |

None of the inspected flows proves atomic global username/email uniqueness or
universal security-plugin compatibility. No source/dependency was copied wholesale.

## Native WordPress boundary and smallest account lifecycle

Reviewed current/floor [7.1.5 user source](https://github.com/WordPress/WordPress/blob/7.1.5/wp-includes/user.php)
and [6.0 user source](https://github.com/WordPress/WordPress/blob/6.0/wp-includes/user.php),
their native login controllers, pluggable hashing/cookie/reset functions and user
schema. Key distinctions from SE-031 were exercised rather than assumed:

- [`wp_insert_user()`](https://developer.wordpress.org/reference/functions/wp_insert_user/)
  supports a chosen password and fixed profile fields; it is not the public signup
  permission check. Construct a new-user array with no supplied ID, arbitrary meta,
  role or capability fields. Core must check WP policy and its configured mode first.
- `register_new_user()` is a native username/email-and-generated-password journey.
  Do not bend it into a chosen-password form with temporary global password filters
  or create-then-overwrite credentials. Nor should a guest form open the privileged
  native REST users endpoint.
- For the chosen-password controller, explicitly run applicable native
  `user_registration_email`, `register_post` and `registration_errors` checks, then
  revalidate final values/duplicates and call native insertion. Native insertion
  retains its own filters and `user_register`. These hooks do not reproduce
  `register_form`, native request actions or third-party JavaScript/UI policies.
- [`wp_signon()`](https://developer.wordpress.org/reference/functions/wp_signon/)
  owns authentication and session cookies. Handle WP_Error, challenge/redirect/exit,
  and cookies not actually being delivered. Never substitute direct cookies or
  `wp_set_current_user()` to make a failed/pending login appear successful.
- Use native email sign-in and password recovery for existing accounts. Both current
  and floor email sign-in returned to the canonical Sprint. A partial-created account
  was recovered through native emailed reset and normal sign-in, without recreating
  the user or storing/replaying the original password.

For a chosen password, the prototype used one explicit native
[`wp_send_new_user_notifications($id, 'admin')`](https://developer.wordpress.org/reference/functions/wp_send_new_user_notifications/)
notification. A focused check found exactly one such signup notification for the
later-checkout participant. It sends no redundant “set your password” email to
someone who just chose it. It does not manually dispatch `register_new_user` and
duplicate the default notification listener. Native recovery still sends its own
mail when requested. This admin-only initial notification is a proposed product
choice requiring owner acceptance, not a claim to reproduce native registration's
complete lifecycle. No custom welcome/verification/mailer system is proposed.

All persisted experimental participant passwords examined were native hashes.
The application state contains HMAC-derived identifiers/counters, not credentials.
There was no password echo, credential URL, client storage or request-body logging
in the candidate flow. Synthetic fixture passwords exist in the ignored test
harness; no password/config/session/mail artifact is committed. Native WP hooks
receive native parameters, including credentials where their APIs require them;
trusted installed PHP must not log or misuse those values.

## Prototype design and executed security properties

The experiment was a guarded, non-shipping MU layer over **unchanged** Core runtime.
It intercepted an explicitly selected form request on the real canonical Runner
path, resolved the actual saved Sprint and used Core Availability. Ordinary requests
continued through Core/Pro. Its guest classification was diagnostic fixture data,
not a proposed production metadata format or a shipped Core/Pro adapter.

Sanitised pseudocode, intentionally not a production implementation:

```text
GET canonical Sprint + explicit experimental registration selection:
    require disposable guard for the experiment
    resolve real route and published/passwordless/Launchable/ready Sprint
    require explicitly enabled mode + effective WP flag + safe Subscriber role
    require known-open presentation classification; tutorial never qualifies
    set random first-party HttpOnly browser cookie, signed action/Sprint form token
    return generic form, native sign-in/recovery links, private/no-store/noindex

POST same canonical Sprint:
    repeat route, readiness, mode, policy, role and actual-identity checks
    reject existing signed-in identity, extra/structured/oversized fields
    reject foreign origin/referrer/cross-site metadata where present
    require browser-bound HMAC token, correct Sprint binding and expiry
    validate bounded email/password/optional names and honeypot
    acquire bounded-time database mutex for this site's owned signup/state path
    read state fresh, remove expired entries, check capacity/rates/replay
    atomically reserve this submission before account creation
    run native pre-registration hooks; recheck final duplicates and role
    insert only allowlisted new-user fields via WordPress
    release mutex before notification and authentication
    notify once through the selected native lifecycle
    supply only server-derived canonical auth return hint
    call normal wp_signon; permit native challenge/interruption; never force cookies
    redirect to canonical Sprint only; new request repeats Core auth/access/readiness
    Start remains the only operation that creates an attempt
```

The prototype's diagnostic input bounds were 8 KiB request, 100-byte email/name
fields and 12–128-byte passwords; optional names were allowed blank. It refused
edge-whitespace passwords rather than silently changing submitted credentials.
These are **not final production password/Unicode semantics**: choose UI/server
bounds together and test native handling across the supported versions before
implementation acceptance. A short random valid login avoided exposing email in
usernames; native duplicate checks still ran. No customer/Student role was added.

### Browser CSRF, replay, origin and privacy

The token was a short-lived HMAC over a cryptographically random browser secret,
random form identity, issue time and resolved Sprint. It was not a standard
logged-out WP nonce. Another browser's token, missing cookie, tampered signature,
cross-Sprint submission, external Origin, external Referrer with null Origin and
cross-site metadata failed without creating an account. Consumed and concurrent
identical submissions created at most one account. Signed expiry was tested.
See [WordPress's nonce limitations](https://developer.wordpress.org/apis/security/nonces/).

Chromium with JavaScript disabled actually sent `Origin: null`; the first strict
comparison rejected it. The correction treats missing/null provenance as unknown,
never as trusted origin: browser token/Sprint binding remain mandatory, and supplied
hostile Referrer/cross-site metadata still deny. Dedicated negative tests passed
after this change. This supports no-JS participation without making Origin a CSRF
credential. The loopback cookie was HttpOnly/SameSite Strict; production Secure/
host-only cookie behaviour still requires HTTPS acceptance.

For production, require an `__Host-`-prefixed guest cookie with Secure, HttpOnly,
root path and no Domain attribute, and test sibling-subdomain cookie injection.
A host-only attribute choice without the prefix is not sufficient protection
against every same-name domain-cookie injection. The loopback prototype's cookie
is deliberately not a production cookie-scope certification.

Guest form/safe error responses were private/no-store/noindex and contained no
private Sprint title/introduction/Step body or member progress. No-JS Chrome at
390px completed signup and had empty localStorage/sessionStorage. Form password,
email and token state were not placed into redirect URLs. Native recovery and
external MFA retain their own credential/cookie protocols; do not claim their
standard reset-link URLs are token-free. No captured credential/mail/log is public.
An actual CDN/page-cache bypass and wider accessibility/device audit remain open.

### Atomic bounded abuse state and shared hosting

The tested mechanism used **one non-autoloaded experimental options row**, fresh
database reads, at most 96 retained entries/64 KiB, and a per-site database named
mutex. Keys were HMAC-derived browser, normalized email and peer-IP identifiers,
plus global budget and replay identities. Diagnostic windows were 10 minutes for
rate buckets and 20 minutes for replay. No per-attacker-key options, transient
read-modify-write counters, custom account/session table or new Core schema.

The sample peer/global/browser/email budgets were deliberately small test values,
not launch defaults. Sequential requests with different spoofed X-Forwarded-For
values still exhausted the **same actual peer** budget: ten validation requests
were processed and two throttled. This also demonstrated the shared-workplace/IP
availability tradeoff. No forwarded address is trusted by default. A host with a
proxy must explicitly configure and test its trusted-proxy boundary, or use a
provider that owns that policy.

Two independent PHP HTTP workers against the same database created one account
for a concurrent same-email pair and one for an identical-token pair. Saturating
the fixed store refused new signup without growing it; expired entries were
removed synchronously on the next write without WP-Cron. Real lock contention
returned a safe retryable failure without account creation; terminating the lock
holder's connection released the lock and allowed a retry. Notification/auth did
not run while that mutex was held. DB error details were not returned to visitors.

[MariaDB GET_LOCK](https://mariadb.com/docs/server/reference/sql-functions/secondary-functions/miscellaneous-functions/get_lock)
documents timeout/error distinctions and connection-lifetime release.
[MySQL's named-lock documentation](https://dev.mysql.com/doc/refman/8.0/en/locking-functions.html)
supports investigating the same mechanism there, but **MySQL was not executed**.
A normal single-primary shared-host connection can support this small design;
database proxies, reconnection, clustered routing, connection pooling and disabled
named locks need specific acceptance. Do not silently replace a failed mutex
with non-atomic transients. Unsupported atomicity means native/provider fallback.

Native user-insertion hooks can themselves perform I/O, take locks or produce
side effects. The controller releases its mutex before its own notification/auth
phases, but cannot bound arbitrary trusted callbacks or roll back external mail.
The tested stack's behaviour is evidence; it is not a universal latency or
transaction guarantee for every installed user-creation listener.

Two important limits remain:

1. A barrier experiment with **uncoordinated native WordPress insertion** created
   two users with the same email from independent processes. The owned mutex cannot
   serialize native/Woo/LMS account creators which do not use it. Do not promise
   global uniqueness, change WP's schema, or repair races by deleting/updating an
   existing identity. Fail safely on ambiguity; retain this in operator guidance.
2. The fixed state has logical expiry and bounded retained size, but a quiet site
   can retain expired hashed entries until its next request. Production needs a
   bounded periodic purge as well as on-request expiry. Site-wide capacity/rates
   can themselves be exhausted by attackers. Tune and observe them; they cannot
   protect a web server overwhelmed before PHP. No production budget or purge
   implementation has been authorised by this spike.

A honeypot and minimum form-age heuristic were exercised. A one-second timing
assertion was sensitive to second-boundary/runtime timing; a ten-second diagnostic
setting verified the early-submit mechanism deterministically. **Do not make a
timing puzzle or compulsory wait the launch security boundary.** Prefer validation,
browser CSRF, atomic budgets/replay and a recoverable accessible anti-spam path;
honeypot/timing are optional signals with autofill/assistive-technology review.
No external CAPTCHA is required. A trusted deny-only anti-spam integration may
append errors, but may not override Core policy, token, access or role denials.

## Authentication, partial failure and unverified email

Actual authentication-filter rejection left an existing new account but no usable
member session. Cookie suppression returned the sign-on user/redirect path while
the next Runner request remained a guest and went to normal login. Creation/mail/
authentication are not one reversible transaction; do not delete or recreate a
persisted account when a later step fails. Native recovery worked for such an
account. A simulated notification-transport failure did not duplicate or remove
the successfully created account. Actual remote delivery was intentionally blocked.

With [Two Factor 0.17.0](https://github.com/WordPress/two-factor/blob/0.17.0/class-two-factor-core.php),
the plugin's own `wp_login` path interrupted into its challenge and withheld member
access. Its native form initially lacked the intended Sprint return context.
Supplying **only a server-derived canonical request hint** before sign-on preserved
the return, and completing the locally captured external MFA challenge produced
an authenticated Ready screen. No direct cookie fallback, fake login screen action
or new Core email-code implementation was used. This verifies this plugin/version/
configuration, not all MFA, passkeys, approval or SSO implementations.

The SSO/delegation rejection case was a **test policy**, not an installed SAML/OIDC
provider. Existing-provider mode must own its form, challenge/approval and redirect;
Core supplies a validated hint and usable original-Sprint/native-login fallback.
Native reset was exercised using captured email and native forms. Supplying the
Sprint return on subsequent native login worked; automatic return across arbitrary
email/device/provider journeys is still not guaranteed. Do not globally intercept
`login_redirect` to conceal that limitation.

Unverified email is an accepted risk only for a low-privilege open-Sprint account,
if the owner approves this scope. Mistyped addresses, account squatting/pre-hijacking,
recovery by the true mailbox holder, unsolicited mail and profile-name ambiguity
remain. Account creation/login does not mark the address verified or prove a real
person's identity. Require provider-owned verification where site policy requires
it, or delegate. Do not add deferred checkpoints, automatic logout, email codes,
marketing consent, historic-order/account linking or entitlement on registration.
Generic errors reduce unnecessary enumeration, but immediate successful signup
inherently reveals some account availability; no perfect enumeration claim.

## Pro compatibility and later commerce

The executed candidate is unchanged main Core **plus an isolated experimental
layer**, not a shipping “new Core” implementation. The comparison lane used the
actual published Core archive plus the same layer. Both ran existing unmodified
Pro, in Core-first and Pro-first active-plugin orders. The proposed classification
API does not exist in either Core; the tests checked its absence explicitly.

| Executed combination | Result |
| --- | --- |
| Main Core + existing Pro, both orders | PASS: safe missing-classifier sign-in fallback; existing public restricted gate/403 and authenticated denial; no guest private content/signup promise |
| Published 0.3.0 + existing Pro, both orders | PASS: same compatibility/fallback behaviour; no dependency on new account/classification API |
| Explicit diagnostic open classifier | PASS: account creation/Ready through existing Pro; restricted/tutorial still denied; hidden-from-Directory Sprint still accessible directly to an authorised member |
| Existing work | PASS: exact owned progress/response/Notes table and existing user snapshots retained through signup in all four combinations |
| Public Directory | PASS: component output hash identical for guest/member and before/after signup; no personalised cacheable output |
| Native Pro objects | PASS: separate before/after snapshot retained synthetic Collection/Journey/resource drafts and metadata with Woo active. This is not full Journey/resource workflow or protected-file acceptance |
| Existing Core/Pro recovery suite | PASS: 9 existing assertions for saved Workspace values, current/stale attempt guards, recovery, restart and historical Workbook read-only retention |

The narrow proposed Pro adaptation remains **presentation classification only**,
separately approved: known open/restricted/unknown, never entitlement or account
creation. No access provider, entitlement OR/status rule, Workspace/history/Notes,
Journey, Collection, resource or navigation change is justified. Until that adapter
exists, unknown guest policy continues to sign-in through current Core/Pro. New
Core must not expose a form for unknown policy, invoke denial actions for generic
errors, change the exact-true SE-017 contract, or require an adapter for existing
authenticated features to work.

Actual WooCommerce 11.3.1 checkout was also executed with Pro and the experimental
registration layer. One Core-style Subscriber later created **two offline test
orders** through native classic checkout and block Store API checkout, with actual
HPOS storage. Both retained the same WP user ID and Subscriber role. No customer
role conversion/precreated billing data was needed. A different existing Customer
role stayed intact. A pre-existing guest order with the participant's email stayed
unlinked after signup and checkout. Unmapped purchases did not grant restricted
Sprint access. These are real free-plugin/COD/HPOS observations, not premium-plugin
or live payment evidence.

Do not call Woo customer creation, customer-auth-cookie helpers, verification or
email-matching order-link actions from Core. Let the store collect billing/shipping
and enforce its own policies at checkout. See SE-031's verified-email/linking
analysis; no new Core commerce integration is needed.

## Production recommendation and approval sequence

Keep this smaller than an LMS account subsystem:

1. One participant settings section with the three modes: built-in, existing
   system, invitation only; absence is safe unset. `manage_options`/nonce/Settings
   API validation, persistent actionable notice on Core admin/WP Dashboard,
   no automatic WP flag/role changes, independent of tutorial/branding reset.
2. One focused account service/controller and server-rendered form in the existing
   Runner/access journey. No registration REST API, new rewrite, user table,
   authentication system or runtime dependency. Use current site-wide branding;
   optional names, email and password; native sign-in/recovery links.
3. One small presentation classification boundary at AccessManager. Pure Core
   baseline may be known open; external policy without explicit classification
   is unknown. Availability/tutorial checks are independent and cannot be granted
   by classification. Keep all actual access checks at AccessManager after auth.
4. One proven, bounded atomic abuse/replay store. The named-lock/one-row design is
   the smallest **tested candidate**, subject to MySQL/host/reconnect/purge/load
   acceptance. Bound mutex time and payload work, release before mail/auth, and
   fail closed to recoverable native/provider guidance when unsupported.
5. One normal WP creation/notification/sign-on lifecycle. No account creation or
   authentication on viewing, no attempt until Start, no mutation of existing
   accounts/progress/history to make onboarding convenient.

The MU interception is a laboratory harness, not the production integration
design. The implementation must integrate guest state at the existing Runner
decision point so the public gate is evaluated once and its existing timing,
strict boolean contract and denial action are preserved. Do not transplant the
MU early-interception code wholesale. Test custom Runner template overrides,
hook counts/order, malformed routes, generic 404/503 and all readiness failures
on the actual final implementation.

| Proposed next ticket | Required acceptance before considering it done |
| --- | --- |
| Owner decision / supported stack | Approve conditional built-in scope, optional names, native email sign-in/recovery, admin-only initial notification and unverified-mailbox risk. Confirm provider delegation and S1/hosting responsibilities; no universal plugin claim |
| Settings + guest presentation | All modes/unset, fresh/upgrade, persistent invalidation notices, no tutorial blocker, exact same-site destinations, open/restricted/unknown/hidden/tutorial/readiness and SE-017 count/order contracts; unmodified Pro fallback in both orders |
| Built-in registration | Native allowlisted creation/hooks/notification/sign-on, browser-bound CSRF, fixed atomic rate/replay/capacity/expiry/purge, correct errors and no-JS/mobile/focus/labels/password-manager/Unicode coverage; no direct cookies; actual current/floor and independent-worker checks |
| Compatibility acceptance | Repeat real selected security-provider rejection/challenge/recovery and complete Core/Pro regression on the final patch; both load orders, published Core/backward compatibility, exact saved-data retention, classic/block checkout/HPOS and applicable actual premium commerce |
| Owner staging acceptance | Verify native signup endpoint protection as well as new form, HTTPS/proxies/cache bypass, physical/mobile/accessibility, mail delivery and complete participant/admin journey on the intended stack; no live experiments from this spike |
| Separate release preparation | Only after approved implementation, regression and owner staging acceptance: review all unreleased work including tutorial, version/readme/changelog/tested-up-to evidence, package and publication checklist. No automatic release or feature-freeze exception |

Where these conditions cannot be met, stop at native/existing-provider handoff
and keep invitation-only available. Embedded multisite remains unsupported; use
native network/provider guidance without bypassing activation, network restrictions,
membership or roles. Nothing in this spike authorises production implementation.

## Actual checks, failures and remaining limits

The [evidence JSON](se-031-1-results.json) lists the individual executed case names;
counts are assertion groups, not a claim of exhaustive combinations.

| Check | Actual outcome |
| --- | --- |
| Current WP/PHP account/security run | PASS: 62 checks, final prototype rerun |
| Exact floor account/security run | PASS: 60 checks, final prototype rerun |
| Browser/MFA/expiry/rate extensions | PASS: 8 checks, including completed actual MFA and 390px no-JS Chrome |
| Additional origin/route boundaries | PASS: 6 checks, including null-Origin token mismatch, external Referrer and cross-Sprint POST |
| Core/Pro matrix | PASS: 56 checks, four source/load-order combinations |
| Existing Core/Pro recovery suite | PASS: 9 assertions, actual unmodified Pro |
| Native Pro draft/data preservation | PASS: 2 additional checks |
| Real Woo classic/block checkout, HPOS | PASS: 9 checks; local COD only |
| Lock contention/native bypass/partial-account recovery | PASS: 8 checks; native bypass is a measured limitation, not protection |
| Native uncoordinated insertion race | Both independent workers created a user with the same email; expected residual reproduced |
| Native signup notification focus check | Exactly one selected admin signup notification for the later-checkout participant |
| Shipping PHPCS / release metadata / assets / JS | PASS: PHPCS exit 0, release checks, 12 asset assertions, 39 JS tests |
| Documentation and scope | PASS: 92 local links, exact archived issue body, evidence counts, whitespace and six-files-only documentation diff |

The assertion groups above total **220**; the separate native race/notification
observations are not inflated into that count. Failed exploratory runs are not
counted as passes. Important corrections/findings:

- A fixture created URLs before refreshing WordPress's permalink state, losing
  the trailing slash. The fixture was corrected through native rewrite state;
  actual canonical Runner behaviour was retained.
- APIRequestContext cookie copying and an already-signed-in form expectation were
  test-harness mistakes, corrected without weakening account/session checks.
- No-JS Origin-null rejection and missing native MFA return context were real
  prototype integration findings, corrected and retested as described above.
- The timing test was made an explicit diagnostic setting; timing remains an
  optional heuristic. A failed MFA completion attempt also exposed a harness
  error: it submitted a resend button/incorrect field instead of the provider's
  actual verification field. The corrected native submission completed normally.
- An unnecessary floor ZIP-extension loading warning was removed from the test
  configuration; account tests did not require that extension. No runtime floor
  was raised, warning suppressed in shipping code or assertion removed.

**NOT RUN / not established:** PHP 8.6 or maintained WP 6.0.19 runtime lanes;
MySQL 8/other MariaDB versions, database proxies/connection-reconnect failure,
large sustained/distributed load; actual SSO/passwordless/approval/Wordfence or
other security-plugin variants; LifterLMS/Tutor runtime form integrations;
multisite embedded registration; real premium Memberships/Subscriptions/payment
gateways, legacy non-HPOS checkout, full resource file delivery/Journey/Collection
workflow acceptance; full Core/Pro release regression, physical devices, screen
readers/RTL/400% zoom; HTTPS/cookie-consent/CDN/host acceptance and mail delivery.
Physical purge on idle sites and final Unicode/password semantics are design gates,
not implemented production guarantees. No new public APIs/settings/screens exist
in shipping Core as a result of this ticket.

## Reproduction and evidence handling

The ignored local harness is `.tools/se0311/` in this checkout. It deliberately
is not a portable shipping test suite and is not included in the public PR:
prototype runtime/configuration/session fixtures must not be committed. Its
`root.txt` locates the newly allocated temporary disposable root; marker checks
prevent operating an arbitrary installation. Sanitised pseudocode above and the
case/evidence list describe the experiment without publishing credentials/tokens.

Provisioning recipe: allocate a new temporary root and explicit disposable marker;
initialise a new loopback MariaDB data directory on port 33319, never reuse a site;
extract pinned WP archives into current/floor directories and create separate
databases with `sp31_` prefix. Generate local WP configs/salts, block outgoing mail
using `pre_wp_mail` before installation, block external WP HTTP, disable automatic
updates/cron, then perform native WP installation and Core activation. Copy only
runtime files from Core/unchanged Pro. Put the guarded experimental MU layer only
on these disposable sites. Native fixture authoring uses WP posts and Core's
StructureManager/Meta services; no manually fabricated progress rows.

Use current PHP for ports 8241/8242 and floor PHP for 8243/8244, each pair sharing
only its own database. Two independent HTTP server processes are intentional:
Windows PHP's single development server is not concurrency evidence by itself.
Lifter/Tutor source is reviewed separately; do not install either on a real site.

Actual local replay commands from the repository root (existing ignored harness):

```powershell
# Provisioning scripts require the explicitly created disposable root/marker.
& .tools/se0311/setup.ps1
$spikeRoot = (Get-Content -Raw .tools/se0311/root.txt).Trim()
php -c (Join-Path $spikeRoot 'php.ini') .tools/se0311/install.php current
& .tools/se0311/start.ps1
node .tools/se0311/accounts.cjs current
node .tools/se0311/accounts.cjs floor
node .tools/se0311/extended.cjs
node .tools/se0311/boundaries.cjs
# Activate the copied Pro via guarded control.php; build real retained work.
# Then run the four source/load-order combinations and existing regression.
node .tools/se0311/pro.cjs
# Activate/configure actual Woo, synthetic virtual product, COD and HPOS.
node .tools/se0311/checkout.cjs
node .tools/se0311/recovery.cjs
node .tools/se0311/preservation.cjs
```

Run provisioning **once per fresh root**; it is not a reset script for a used
database. Account runs precede retained-progress fixture creation. The Pro fixture
used authenticated native/Core APIs to save Workspace/Notes, complete a Sprint and
restart, then compared exact snapshots. The existing suite command was
`php -c <current-ini> tests/integration/step-change-recovery-pro.php` with
`SE_TEST_WP_ROOT`, `SE_TEST_DISPOSABLE=yes`, `SE_TEST_PRO=yes` set to the disposable
site. Native race workers used a test-only barrier after native duplicate checks.
All commands' successful counts were checked, not merely process exit zero.

Captured mail/reset credentials, cookies, raw site/database files, diagnostic
screenshots, fixture identities and private source remain local and excluded.
Retain only sanitised results/hashes in Git. Stop only the recorded task HTTP/DB
processes after evidence collection; do not delete or repurpose prior test sites.
The reviewer can inspect the retained local experiment, but a clean clone does
not contain the ignored runtime harness. Production tests belong in the separately
approved implementation, not in this documentation-only spike.

At completion, all four recorded task HTTP workers were stopped, the new task
database was shut down, and a listener check found none of their five ports open.
Prior disposable sites and unrelated processes were retained.
