# SE-031 — Participant Registration and Access Journey

Discovery completed **27 November 2026**. Status: **recommendation for owner review;
implementation is not authorised**. Source issue: [Core #24](https://github.com/the1path/sprint-engine/issues/24);
the [issue snapshot](se-031-issue.md) preserves its full specification.

## 1. Decision

**Go forward with a branded access screen and explicit registration configuration,
using native or existing account screens for the first launch. Do not yet build
an embedded password-registration/login system with automatic sign-in.**

Keep authenticated WordPress identity, AccessManager and the existing progress
services. An account never grants commercial entitlement, starts a Sprint or
creates an attempt. After successful authentication, resolve the canonical Runner
again: a new participant sees Ready to Start; an existing participant resumes
their own canonical state.

The owner's embedded-form candidate is technically possible on a controlled
single-site stack, but it is not simply a call to `wp_insert_user()` followed by
`wp_signon()`. Registration policy, form-level security integrations, concurrency,
abuse controls and interrupted authentication become Core responsibilities.
There is no universal WordPress API proving that every installed MFA/SSO policy
has completed. Native handoff avoids building a second authentication interface.

Recommended smallest launch:

1. Explicitly choose one of three modes, with a safe unset state and persistent
   administrator guidance. Keep existing-account sign-in working throughout.
2. Show generic branded actions after Core readiness checks, without guest access
   to Sprint content or progress. Retain the SE-017 denial contract.
3. Delegate sign-in and recovery to native WordPress or an explicitly configured
   same-site provider. Delegate built-in registration to native WordPress initially,
   only where the operator has enabled and secured public signup.
4. Treat embedded signup/immediate authentication as a separately approved spike
   and subsequent implementation decision. Retain the option to decline it.

This deliberately revises the preferred first mode: its launch implementation
would be **Sprint Engine registration — native WordPress handoff**, not the
proposed first/last-name, email and password form inside Runner. Native signup
requires the normal emailed password-setup journey; it cannot satisfy a promise
of immediate access without mail. The owner must accept that tradeoff or approve
the further spike before an implementation ticket is written. Do not label the
handoff as an embedded form or claim the original preferred UX has been delivered.

**Security decision:** conditional GO for the handoff plan; NO-GO for a general
claim of secure immediate embedded signup across arbitrary plugins/hosts. Public
signup remains disabled in Sprint Engine until explicit configuration. Native
signup launch also requires evidence of suitable abuse protection and mail/recovery
on the intended stack. Invitation/external-provider work can proceed independently
after approval. No uninstallation cause or preference for anonymous Sprints has
been established by this investigation.

## 2. Repository, release and evidence boundary

| Item | Observed evidence |
| --- | --- |
| Core repository | `the1path/sprint-engine`, public checkout |
| Initial branch | `codex/se-030-1-tutorial-journey-rewrite`, clean working tree, HEAD `7066fe6` |
| Verified integration base | Fetched `origin/main`, `f28af5bab1145694bb9936e58a259ede641b7fa5`, merged SE-030.1; initial branch's relevant tree matched it |
| Discovery branch | `codex/se-031-registration-discovery`, created from that exact `origin/main`; old branch/work preserved |
| Published Core | 0.3.0; source version/Stable tag 0.3.0, schema 2, WordPress 6.0+, PHP 8.0+ |
| Development additions | SE-028/029/030/030.1 are present on main; publication remains separately authorised |
| Pro | Available locally; current integration ref verified against its remote; clean and inspected read-only. Private source, repository identifiers and business details are intentionally not reproduced here |
| Activity boundary | Source/API research and documentation checks only. No account experiments, live-site requests, outgoing test mail, orders, credentials, runtime edits, version/schema/rewrite changes, merge, deployment or release |

The [WordPress.org listing](https://wordpress.org/plugins/sprint-engine/)
independently reports Core 0.3.0. The repository's release and validation records
remain the authority for what its source contains; publication does not include
unreleased additions merely because version constants match.

Read: AGENTS.md; the v0.1 specification; development guide; SE-004/004.1 Runner,
SE-012 attempts, SE-013 Dashboard, SE-014 onboarding/readiness, SE-017 denial/gate,
SE-024 typography, SE-025.1 navigation, SE-026.1 release, SE-030/030.1 tutorial
validation and the tutorial-marker contract. Relevant code was inspected directly,
rather than treating historical passing results as new validation.

No conflict with the baseline identity/progress architecture is necessary.
Participant onboarding can be a Core identity UX improvement under a new ticket;
commerce and membership still belong outside Core. Changing the guest redirect,
adding an account form or broadening public disclosure would be behavioural changes
requiring owner approval. This report makes none of them.

In particular, v0.1 section 8 specifies the guest login redirect. A configured
200 access screen would be an explicit later presentation exception, similar in
purpose to SE-017's existing opt-in 403, and must be authorised/documented by its
implementation ticket. It must not relax authentication before member content.

## 3. Current code path and settings inventory

| Boundary | Current behaviour and implication |
| --- | --- |
| `src/Runner/Routes.php` | Matches the actual `/sprint/{slug}/` rewrite/path, not a query-string impersonation. `url()` builds the canonical URL from saved slug using `home_url()` and permalink conventions. Step posts are not public participant routes |
| `Runner::resolve()` | Validates slug, resolves the Sprint, runs Availability **before** guest handling. A guest gets `wp_login_url(canonical_runner)` and 302 by default. Only exact boolean `true` from the public-gate filter selects generic error/403 with Login and the denial action |
| `src/Runner/Availability.php` | Requires published Sprint, no Sprint password, Launchable, valid linear structure and published associated active Steps. It is readiness, not entitlement or public-disclosure classification. Current Step password handling also has a later Runner guard; do not claim every private-content prerequisite is an anonymous disclosure policy |
| `src/Access/*` | The default provider permits the actual signed-in WordPress user and valid Sprint. AccessManager applies the member-access filter, then the tutorial marker's administrator-only policy. `can_access(0, id)` is not a guest-open classifier |
| Authenticated Runner | AccessManager runs before `get_state()`/resume/content. `not_started` renders the introduction; `in_progress` resumes current Step; completion uses canonical attempt. Generic persistence errors are 503, not denials |
| `templates/runner.php` | Denial action requires explicit denied-Sprint marker plus error/403; unavailable content has no promotional callback. No guest Step/progress/REST mutation configuration is supplied |
| Progress/REST | ProgressService owns transitions; no enrolment is needed to render not-started state. REST writes require current identity and REST nonce, access/readiness and relationship validation. Complete/Restart retain the expected-attempt contract from 0.3.0 |
| Dashboard | `/sprint-engine/dashboard/` sends guests to native login; enumerates only after authentication, readiness and access. It is member data, not a public registration landing page |
| Settings | `SettingsPage` registers branding via Settings API, `manage_options`, non-public settings; shows Dashboard location and the Tutorial panel. There is no participant account mode, registration destination, login override or registration handler |
| Branding/navigation | Reuse site-wide `RunnerBranding`, typography and `WebsiteNavigation`. No second branding store, theme dependency or customer copy is needed |
| Tutorial | Protected marker **presence** restricts participation to the signed-in effective `manage_options` user, after extension filters. Activation creates no sample content. Welcome/Settings opt-in and stored decision must remain independent of participant setup |
| Privacy/cache | Runner/Dashboard set DONOTCACHEPAGE, private/no-store/no-cache, noindex/nofollow. Member REST sets no-store including errors. Upstream caches can answer before PHP; these headers alone cannot secure a misconfigured CDN |

SE-017's public filter must keep its false default, exact-true semantics, timing,
arguments and presentation-only meaning. Do not repurpose it as “open Sprint”,
“registration permitted” or an entitlement check. Do not use arbitrary errors as
an excuse to render an account or promotional screen.

## 4. Verified WordPress behaviour

The [WordPress version API](https://api.wordpress.org/core/version-check/1.7/)
and [release archive](https://wordpress.org/download/releases/) reported **7.1.5**.
Reviewed tagged **7.1.5** and supported-floor **6.0** source, including user,
pluggable, default-filters, login, signup, multisite and schema files. These are
source findings, not execution of both versions. Recheck current patches when
implementation starts; the floor is a compatibility target, not a recommendation
to run an unpatched historic release in production.

### Creation, policy, roles and hooks

- [`wp_insert_user()`](https://developer.wordpress.org/reference/functions/wp_insert_user/)
  persists users and invokes insertion filters and `user_register`. It is not a
  public-registration policy service. Supplied `ID` updates a user, so a public
  controller must construct an allowlisted new-user array, never forward request
  arrays. With no explicit role it uses the site's default role.
- [`wp_create_user()`](https://developer.wordpress.org/reference/functions/wp_create_user/)
  is a smaller wrapper over insertion, not an additional signup-policy check.
  [`register_new_user()`](https://developer.wordpress.org/reference/functions/register_new_user/)
  validates login/email, runs `user_registration_email`, `register_post` and
  `registration_errors`, generates a password, creates the user, sets the
  password nag and dispatches `register_new_user`. It accepts no chosen-password
  or name arguments and does not authenticate the new user.
- The native login controller, rather than those insertion helpers, checks
  `users_can_register` and redirects multisite registration to network signup.
  This distinction exists in both reviewed versions: [7.1.5 login source](https://github.com/WordPress/WordPress/blob/7.1.5/wp-login.php),
  [6.0 login source](https://github.com/WordPress/WordPress/blob/6.0/wp-login.php).
- Native `register_form` is a **rendering** hook. `register_post` and
  [`registration_errors`](https://developer.wordpress.org/reference/hooks/registration_errors/)
  are pre-creation validation hooks. Calling insertion directly does not reproduce
  those registration hooks. Re-firing only similarly named hooks cannot guarantee
  integration with plugins expecting native fields, JavaScript or request actions.
- Core's default filters attach
  [`wp_send_new_user_notifications()`](https://developer.wordpress.org/reference/functions/wp_send_new_user_notifications/)
  to `register_new_user`. Raw insertion does not automatically perform that whole
  notification sequence. A future controller must select/document one notification
  lifecycle; do not send duplicates by manually notifying and replaying it.

**Policy recommendation:** require both explicit Sprint Engine mode selection and
effective `users_can_register` for built-in single-site public registration.
Never set that global flag, alter `default_role`, or temporarily filter either to
bypass site policy. Explain that enabling WordPress registration also exposes
WordPress's other native signup path. For native handoff, require an operator-
confirmed low-privilege default; initially support the standard Subscriber role
with its capabilities checked, not an arbitrary default role with elevated powers.
A future embedded handler should set Subscriber explicitly and refuse signup if
that role has been customised to grant authoring/administrative powers. Browser
fields can never choose roles/capabilities. Installed PHP remains trusted and can
alter users through its hooks; no Core controller can sandbox it.

An independent Sprint Engine opt-in **does not safely override a disabled WP
flag** in the proposed built-in mode. In external mode, Core creates no account:
the selected system owns and explains its own registration policy, which may be
independent of that WP flag (for example, a store's account settings). Choosing a
link is not Core permission to change either system's policy.

### Authentication, cookies and recovery

[`wp_signon()`](https://developer.wordpress.org/reference/functions/wp_signon/)
runs native authentication, sets cookies on success and dispatches `wp_login`;
it does not set the current-user global itself. Password authentication can be
refused through [`wp_authenticate_user`](https://developer.wordpress.org/reference/hooks/wp_authenticate_user/)
and the broader authentication chain. A returned user object does not prove a
browser received usable cookies or completed a challenge: `send_auth_cookies`
can suppress cookies and `wp_login` handlers can interrupt for MFA. Native login
also runs screen/request hooks and redirect handling outside `wp_signon()`.
Reviewed [current pluggable source](https://github.com/WordPress/WordPress/blob/7.1.5/wp-includes/pluggable.php)
and [floor pluggable source](https://github.com/WordPress/WordPress/blob/6.0/wp-includes/pluggable.php).

Never call `wp_set_auth_cookie()`, `wc_set_customer_auth_cookie()` or set the
current-user global as a substitute for successful normal authentication. Do not
retry after a policy rejection with a less restrictive API. Redirect to a fresh
request and require the actual authenticated cookie identity before progress.

WordPress owns password hashing, session tokens and reset credentials. Its
[`password hashing API`](https://developer.wordpress.org/reference/functions/wp_hash_password/)
has changed since the floor; custom hashing/storage would be incompatible.
Native reset uses expiring credentials checked through
[`check_password_reset_key()`](https://developer.wordpress.org/reference/functions/check_password_reset_key/)
(one day by default, filterable). Native new-account mail contains password-setup
instructions, not the chosen password. Successful mail submission is not delivery
or ownership proof. Site operators own transport, suppression settings, recovery
support and deliverability; Core must not require its own mail service.

Current `wp_signon()` also clears an existing activation/reset key on successful
login. A future chosen-password flow must therefore assess notification order and
message purpose: sending native password-setup mail and immediately logging in can
leave a stale setup link. Do not work around that by suppressing policy hooks or
persisting the submitted password for a later retry.

### Uniqueness and multisite

Both reviewed user APIs check existing username/email before insertion, but the
standard user schema has ordinary indexes on those columns, **not unique
constraints**: [7.1.5 schema](https://github.com/WordPress/WordPress/blob/7.1.5/wp-admin/includes/schema.php),
[6.0 schema](https://github.com/WordPress/WordPress/blob/6.0/wp-admin/includes/schema.php).
Therefore sequential duplicate rejection is not proof of race-safe global
uniqueness. An embedded endpoint needs tested serialisation for its own requests,
fresh under-lock checks and replay handling. It cannot guarantee exclusivity
against native/Woo/other creators which do not participate in its lock. Do not
modify WordPress tables or promise site-wide at-most-one creation. This is a
material residual limitation of native handoff too, not an invented Core guarantee.

Multisite has network-wide identities and signup/activation policy, disallowed
names/domains and pending signups. The network's `registration` modes and
`wpmu_validate_user_signup` matter; a site's WP flag is insufficient. Reviewed
[current multisite source](https://github.com/WordPress/WordPress/blob/7.1.5/wp-includes/ms-functions.php),
[floor multisite source](https://github.com/WordPress/WordPress/blob/6.0/wp-includes/ms-functions.php)
and both `wp-signup.php` controllers. **No embedded multisite registration in the
initial scope.** Keep native network authentication/signup/activation where the
network permits user creation, without automatic site membership, role assignment,
activation bypass or network provisioning. Prefer invitation/existing-system mode
until that network's handoff is accepted. A network signup on another domain
cannot be entered as an arbitrary external-mode URL; use the native WP-generated
network flow or an operator-owned same-site entry page. Core's existing rejection
of network-wide activation remains unchanged.

## 5. Options and three modes

| Approach | Strength | Cost/limit | Recommendation |
| --- | --- | --- | --- |
| Embedded branded registration and login | Least context switching; chosen password; names possible; immediate return on a known stack | Owns public form security, guest CSRF, race handling, spam/mail abuse and form/MFA integration; no universal policy-completion API | Further spike; not first launch |
| Branded actions with native WP handoff | Reuses account, security UI, recovery and native hooks; no password processing by Sprint Engine | Native username/email and emailed setup; email/cross-device return is imperfect; WP itself has no general signup rate limiter | Smallest Core registration launch on a secured, opted-in site |
| Existing registration system | Site retains its account/security workflow; suitable for stores/membership/SSO | Each provider owns policy and redirect support; cannot promise arbitrary compatibility | First-class mode, no provider adapter matrix |

The three **stored modes** should remain distinct:

| Setting | Guest action on a known open eligible Sprint | Failure/default behaviour |
| --- | --- | --- |
| Unset (absence; not a fourth selectable mode) | Existing sign-in continues; no signup promoted | Persistent admin setup notice; no forced setup wall for participants/admin tutorial |
| Sprint Engine registration | Launch recommendation: clearly labelled native Create account handoff plus Sign in and Forgot password. Preferred embedded form is conditional future work | Disabled WP signup/unsafe role/unsupported network policy => sign-in only plus admin guidance; never silently enable registration |
| Use an existing registration system | Create account via required valid same-site registration destination; optional same-site login destination; native login fallback | Missing/invalid registration URL => suppress signup; invalid optional login => native fallback and reappearing notice |
| Invitation only | Sign in and native password recovery; appropriate invitation/help text | Hides Sprint Engine's signup action only; never claims to disable registration elsewhere on the site |

Use site-wide branding and generic copy, not a private Sprint title, introduction,
image or author-supplied excerpt. “Create an account to save your progress” is
appropriate only for a known open Sprint. Restricted copy should say “Sign in to
check your access” with the existing independently safe information action.
Unknown policy must not promise free access, recommend buying, or offer signup
as a way to unlock this Sprint. This fails closed for **disclosure and promises**;
native sign-in remains possible and real access is checked afterwards.

If embedded registration is later approved, assess first and last name as optional
profile fields: they are unnecessary for progress, and mandatory split names
exclude some participants. This is a proposed UX/privacy change to the candidate,
not an inferred requirement to collect more identity. Its main fields would be
email and password, with native recovery and existing-account sign-in. No mandatory
email verification in that candidate unless the security decision is revised.

## 6. Administrator configuration

Add a separate Participant access section to **Sprints → Settings**, independent
of branding reset and Tutorial opt-in. Proposed one non-autoloaded, non-REST Core
option `sprint_engine_participant_access` contains only the allowlisted mode and
external registration/login destinations. Do not add a parallel identity store,
account table, user role, per-Sprint registration flag or activation migration.
These are proposed boundaries, not options created by discovery.

Settings writes require POST, `manage_options`, Settings API capability/nonce and
whole-value validation. No preselected public-signup mode; upgrades must not
convert existing WP registration settings into Sprint Engine consent. Preserve
last valid saved values on bad submissions, report actionable errors, and validate
again on reads and before displaying a destination. Changes in domain, WP flag,
roles or stored settings must be able to make setup incomplete again.

While incomplete, show a persistent, non-dismissable-until-resolved notice on
Sprint Engine admin screens **and the native WP Dashboard**, to authorised admins
only: “Choose how participants create accounts”, with Settings link and the
specific fix (for example, invalid registration destination or disabled WP signup).
Do not reuse SE-030's first-install-only prompt: its existing-install suppression
is wrong for this requirement. A valid Invitation only choice resolves the notice
even when WP registration is disabled. No automatic activation redirect, global
registration change, notice for Subscribers, or block on running/installing the
administrator tutorial.

External URLs should be HTTPS on the exact configured home origin (loopback HTTP
only in tests), with matching effective port and site/subdirectory boundaries;
reject user-info, protocol-relative URLs, control characters, unsupported schemes,
fragments as return state, auth/admin mutation destinations and obvious loops back
to Runner. Support legitimate site-owned custom account paths/query actions;
do not require every destination to be a WP Page. A configured Page must be public
and passwordless. Syntax validation cannot prove a custom endpoint is functional:
provide an explicit admin Test journey link, with no server-side URL fetch/SSRF.
Same-origin entry can intentionally hand off to SSO; the provider owns that hop.
Never widen `allowed_redirect_hosts` globally to accommodate it.

## 7. Access classification and participant states

Core currently knows member access, not whether an anonymous person can acquire
access just by registering. Do not inspect Pro's private metadata from Core, call
member access filters with a fabricated user, infer “open” from a 403, or assume
that publication/Directory visibility means free access.

Propose one narrow presentation classification inside AccessManager, tentatively
`participant_access_kind($sprint_id)` returning `open`, `restricted` or `unknown`.
Only the built-in unextended logged-in policy can supply Core's known-open baseline.
If a non-default provider or member-policy callbacks are present without an
explicit classification integration, use `unknown`. A single proposed filter
`sprint_engine/participant_access_kind` can carry that enum plus only the resolved
Sprint ID. Invalid output becomes unknown; tutorial marker forces no public signup
regardless of any callback. An integration must account for all its restrictions
and must not override another policy's unknown/denial without coordination.
This is not a replacement AccessProvider API or a permission grant. Final naming
and collision semantics must be reviewed in the follow-up ticket.

| Request/state | Proposed screen/action | Allowed data/writes |
| --- | --- | --- |
| Malformed/missing/draft/private/scheduled/trashed/password/unlaunchable/broken/unpublished-Step Sprint | Existing generic unavailable, usually 404 | No classification/gate/denial hooks, signup promotion, title or progress |
| Tutorial guest | Existing native sign-in path for an administrator | No invitation to create a participant account for the tutorial |
| Ready guest, unset configuration | Existing login handoff, or existing exact-true SE-017 403 | No new public signup; keep existing callbacks working |
| Ready guest, configured, known open | Generic branded account actions (new presentation state; 200) unless existing SE-017 gate has selected 403 | No Sprint/Step body, title, enrolment, REST mutation nonce or attempt |
| Ready guest, restricted/unknown | Preserve opted-in SE-017 generic 403 and its safe information action; otherwise neutral configured sign-in presentation without an access promise | Do not manufacture a denial action from unknown policy; never grant content |
| Authenticated, no access | Existing genuine 403 with denied marker/action | No signup upsell implying entitlement |
| Authenticated, authorised | Existing Ready/Resume/Completed Runner | Current user and canonical attempt only; Start remains explicit |
| Persistence/unknown internal failure | Existing generic 503/retry | No denial classification, purchase or registration CTA |

For the proposed new guest state, use a distinct context/template branch; do not
misuse the existing denied marker or mutate the SE-017 filter default. Evaluate
the existing exact-true public gate after readiness as today and preserve its 403
and denial-action placement. Its opt-in must never cause a registration offer for
an unknown/restricted Sprint. Denial hooks continue to receive only the integer ID.
All guest variants use private/no-store and noindex semantics. A future Core
capability indicator for this additive seam is preferable to inferring availability
from the unchanged 0.3.0 source version.

## 8. Redirect and recovery contract

1. Resolve route to the saved Sprint server-side; derive the destination using
   Runner Routes. Never accept a browser-supplied user, Step, attempt, role or
   arbitrary `redirect_to` as authority. Ignore unrelated identity query selectors.
2. Validate destination origin **and** canonical Sprint path/identity before
   handoff, then again on return. Same-host alone is insufficient: WP's
   [`wp_safe_redirect()`](https://developer.wordpress.org/reference/functions/wp_safe_redirect/)
   uses host validation and can allow installed-plugin host filters; Core should
   enforce its narrower destination policy first. Support site subdirectories,
   encoding, permalink trailing slashes and the difference between home/site URLs.
3. Use [`wp_login_url($canonical)`](https://developer.wordpress.org/reference/functions/wp_login_url/)
   for native sign-in so installed login URL filters continue to work. A known
   external form receives a bounded encoded `redirect_to` hint containing that
   canonical URL. It must validate the hint independently and use its own auth/UI.
   It may ignore the hint; Core must not commandeer all `login_redirect` events.
4. Native registration can receive a return hint to the native login URL which
   itself contains the canonical Sprint return. Native registration's immediate
   post-submit redirect does **not** authenticate. Its password-setup email and
   later reset flows do not reliably preserve nested hints. `wp_lostpassword_url`
   carries a browser redirect hint, not a universal email-to-Sprint guarantee.
   See [native recovery URL API](https://developer.wordpress.org/reference/functions/wp_lostpassword_url/)
   and the tagged login controllers above.
5. Keep the originating access screen available with an ordinary **Return to
   Sprint** link and clear instruction to return after account setup/recovery.
   If the provider ignores the hint, its normal account page remains valid;
   following the original Sprint link or My Sprints after login is the safe
   fallback. For email opened in a different browser/device, sign in there and
   reopen the link. No browser credential copying or automatic account linking.
6. Every returned Runner request repeats availability and real member access.
   Renamed/deleted/unpublished Sprint or lost entitlement yields a generic safe
   state. Existing member progress is never reset to make onboarding convenient.

**Launch limitation requiring acceptance:** guaranteed automatic Sprint return
across native email setup/reset, arbitrary external forms and different devices
is not provided. A scoped return-context helper (short-lived signed first-party
HttpOnly cookie, containing only canonical destination identity) is an optional
follow-up, not a reason for a new session table or global login interception.
Even that cannot transfer context across devices. If exact same-browser email
return is mandatory, make its spike/acceptance a dependency before launch.

## 9. Threat model and security gate

Public users can submit arbitrary HTTP requests repeatedly and in parallel.
Installed PHP, site configuration, the web server and upstream caches are trusted
components with their own acceptance obligations. Transport requires HTTPS outside
disposable loopback. The following applies to any future embedded form; native
and external handoffs delegate endpoint protections to their owner rather than
claiming these mitigations are implemented in Sprint Engine today.

| Threat | Required control / residual risk |
| --- | --- |
| Account enumeration | Generic existing-account/creation-failure message with Sign in/Recover actions; allow useful field syntax errors. Avoid public existence APIs, user IDs and error-detail/timing differences where practical. Immediate successful signup inherently reveals some availability; native WP/Woo may already reveal existing emails. Do not claim perfect enumeration resistance |
| Automated signup/mail abuse | Validate before expensive hashing/mail, request-size bounds, hidden honeypot designed not to trap autofill/assistive tools, signed form age, layered atomic rate limits, local/global capacity bounds and monitoring. Rate-limit failure paths too. No email per invalid/replayed request, simple puzzle as sole defence, or mandatory third-party CAPTCHA |
| Credential stuffing/brute force | Delegate login to the site's supported auth UI initially. An embedded login requires its own tested endpoint coverage by site protections; WP password auth alone is not a rate limiter. Do not use account lockout that attackers can trigger indefinitely |
| Guest/login CSRF | POST only; reject incompatible Origin/Referer/Fetch Metadata, with a token-based path for browsers without those headers. Default logged-out WP nonces share user 0 and are reusable. A future form needs random browser-bound CSRF state, Secure/HttpOnly/SameSite cookie and short expiry/action binding; Origin checks and SameSite alone are insufficient. Clear/reissue state on identity changes. No GET account creation or login |
| Replay/double submit/concurrency | Consume a form submission identity atomically; detect in-flight/retry outcomes without replaying mail or authentication for an existing account. Lock/check canonical email and generated username within owned endpoint; fail closed on lock/storage failure. DB tests with independent workers, not a disabled Submit button, are required. Other registration paths remain outside that lock |
| Email/login normalization | Reject array/object/oversize/control-character input; validate email before and after native filters. Do not silently transform an invalid address into a different recipient. Align comparisons with WP/database collation, bound email to the supported user column, and test case/Unicode collisions. No provider-specific dot/plus stripping. Generate a random valid username unrelated to email/name, check under lock and bound collision retries |
| Password exposure/DoS | Native APIs only; no SQL/hash implementation, URL, log, exception payload, analytics, localStorage/sessionStorage or repopulated password field. Allow paste/password managers and long passphrases. Propose 12-character minimum and 128-byte maximum for a future form, with explicit UI/server validation and Unicode boundary tests; never truncate or independently trim/sanitise passwords. Reassess against site policy and native handling in the spike |
| Role/capability/identity injection | Build fixed new-account fields; never accept `ID`, roles, capabilities, arbitrary meta, activation keys or logged-in-user changes. Refuse signup when a user is already signed in. No automatic promotion to customer/author/admin and no email-based update of an existing account |
| Session fixation/partial auth | Use normal WP authentication, no custom session identifier reuse or direct cookie fallback. Success must be observed on a fresh request as the expected authenticated account; MFA/SSO may interrupt, exit or suppress cookies. Never claim a returned WP_User completed login |
| Redirect manipulation | Server-resolved canonical destination, strict origin/path rules, reject nested malformed encodings/protocol-relative/user-info/control characters and external hosts. Recheck on return. No referrer or guest ID as access authority |
| Cache/PII/token leakage | Private/no-store/no-cache/noindex on dynamic forms, errors, redirects and responses; no sensitive JS persistence. Exclude Runner/account handlers/login/recovery from caches answering before PHP; test separate sessions and expired forms. Avoid third-party assets on account screens and set an appropriate no-referrer policy for account flows |
| Multisite/signup restrictions | Use native network flow and policy; no local bypass via insertion APIs, activation shortcut or automatic `add_user_to_blog`. Test banned/pending/spam users and network signup-disabled cases |
| Security plugin bypass | Preserve native policy/error filters; do not remove callbacks or whitelist this endpoint past CAPTCHA/MFA. Unknown form policy => native/external UI. Explicit adapters need an independent contract/test, not plugin-name detection masquerading as compatibility |
| Unverified email | Address may be mistyped or controlled by someone else. No verified-identity assertion, marketing consent or email-based entitlement/commerce link. Mail owner may recover the account; attacker-created accounts can predate a genuine customer's registration. Explain recovery and retain provider-owned proof requirements |

The [WordPress nonce documentation](https://developer.wordpress.org/apis/security/nonces/)
explicitly distinguishes nonces from authentication and one-time replay prevention
and documents the logged-out user-0 limitation. A generic REST nonce must not be
used as a guest signup/session solution.

The reviewed native login form's test-cookie handling checks cookie support; it
is not a browser-bound proof of sign-in intent. Delegation does not eliminate
native login-CSRF exposure or add a Core CSRF guarantee to native/external forms.
Treat a current logged-in session as insufficient proof of mailbox ownership or
permission to link a different account/order. Native-path hardening and actual
security-provider coverage belong in the signup/security acceptance spike.

### Bounded low-friction controls on ordinary hosting

A practical future design must bound **both** request work and retained keys.
Recommend short-lived HMAC-derived keys for browser, normalized email, coarse
network signal and a site-wide signup budget. Do not store raw IP/email/password
in abuse records. Keyed hashes are still potentially personal data; document their
purpose and retention. Expire records within a short documented window (candidate:
at most 24 hours), enforce logical expiry on reads and bounded cleanup independently
of low-traffic WP-Cron. IP-only blocking penalises shared workplaces/mobile networks
and is easily evaded by botnets; no permanent per-email denial or punitive lockout.

Use only the server's peer address by default. Trust proxy headers only after
explicit operator configuration of known proxies; never blindly use the first
X-Forwarded-For value. If peer address is a shared proxy, provide clear setup
guidance and conservative limits. Timing/honeypots need a visible recoverable
message and an accessible retry/support/native-provider alternative; a human
must not be forced to solve a visual puzzle or run JavaScript. Do not make that
alternative an unprotected route around a mandatory site security policy.

Plain `get_transient()` followed by `set_transient()` is not an atomic counter or
mutex. Object-cache increment/expiry/eviction behaviour varies. One option row per
attacker-chosen identifier is unbounded storage, even when autoload is false.
The spike must select/test a small fixed-capacity database-backed design or a
documented atomic provider with bounded expiry and collision/saturation behaviour.
No limiter/storage algorithm is selected or implemented here. Do not hold a
database transaction across hashing, mail or callbacks without proving lock and
failure behaviour; native user hooks can perform arbitrary non-transactional work.

On saturation/storage failure, refuse new signup briefly, preserve sign-in and
existing progress, and offer a retry/help path. A site-wide budget trades spam
resistance for availability and can be exhausted by attackers: observe/tune it,
do not present it as a DDoS defence. Ordinary shared hosting can enforce bounded
local work with a proven atomic backend, but cannot guarantee resistance to
distributed abuse or traffic that exhausts the web server before PHP. Operator
responsibilities include TLS/proxy/cache setup, native-path protection, updates,
mail volume/delivery, monitoring, backups and incident response.

An optional anti-spam seam may let trusted PHP append a **deny/error only** verdict
after baseline validation and before account creation/mail, with a rendering seam
only if an actual supported provider needs it. It must never turn a Core/security
denial into an allow or bypass nonce/rate/role checks. Prefer existing native
registration/security hooks in the handoff launch. Add no speculative provider SDK,
external CAPTCHA dependency or broad provider framework.

## 10. Exact authentication integration limits

| Integration | Verified fact | Compatibility conclusion |
| --- | --- | --- |
| WP native auth | Authentication filters and cookie/login hooks run via native sign-on; login screen hooks are additional | Native handoff is the default; a custom form is not equivalent merely because it calls wp_signon |
| WordPress Two-Factor | Reviewed upstream source blob `cb09f2721aa2cae1941db2dcd5b2ecb2bea11bf7`: hooks authentication and `wp_login`; suppresses cookies, destroys a pending session, renders a second-factor screen and exits | Core must allow that UI/redirect lifecycle; no unconditional success or direct-cookie fallback. Source review only, no browser/MFA acceptance |
| Wordfence Login Security | Vendor documents separate native/Woo/custom integration behaviour and limitations for checkout registration CAPTCHA | No claim that a new Runner form automatically gets passkeys/2FA/CAPTCHA. Prefer its supported native UI; test actual versions/settings before supporting an embedded form |
| SSO/passwordless/approval/verification plugins | No single native completion API covers every provider; some policies depend on UI/request action rather than password filters | Existing-system mode and native URL filters are the compatibility route. No SAML/OIDC/passkey/provider adapter or universal support claim |

Sources: [Two-Factor source](https://github.com/WordPress/two-factor/blob/master/class-two-factor-core.php)
(review date above; the table records the file's content-blob SHA, not a release or commit),
[Wordfence vendor integration documentation](https://www.wordfence.com/help/login-security/#woocommerce-and-custom-integrations).
No security plugin was enabled against a site during discovery. Native delegation
reduces the number of interfaces Core owns; it does not certify every plugin
combination. A registration adapter must respect rejection, approval and email
verification policies even if Core itself does not mandate verification.

## 11. Unverified email, commerce and Core/Pro boundary

Current WooCommerce plugin metadata reported **11.3.1**, requiring WordPress **7.0**
and PHP **7.4**: [tagged plugin header](https://github.com/woocommerce/woocommerce/blob/11.3.1/plugins/woocommerce/woocommerce.php).
Source review used that tag, not an assumption that old documentation tables are
current. Actual checkout, premium Subscriptions/Memberships and HPOS acceptance
were **not run**. Latest Woo cannot be used in a WordPress 6.0 floor test; do not
silently raise Core's minimum to match it.

### Later purchase using the same account

The Woo [customer data store](https://github.com/woocommerce/woocommerce/blob/11.3.1/plugins/woocommerce/includes/data-stores/class-wc-customer-data-store.php)
loads an existing WP user by ID, reads its role and user/billing metadata; it does
not require an existing `customer` role just to read that user. The
[classic checkout source](https://github.com/woocommerce/woocommerce/blob/11.3.1/plugins/woocommerce/includes/class-wc-checkout.php)
uses the signed-in user ID for the customer/order and updates billing/shipping
data through WC_Customer. Therefore a normal Core-created Subscriber can later
purchase while signed into that **same account**, without Core converting the
role or precreating billing data. This is a source-backed compatibility inference,
not an executed checkout guarantee. Role-based store extensions can add policy.
Block checkout/Store API requires its own acceptance, even though its source was
also inspected.

Woo's [`wc_create_new_customer()`](https://github.com/woocommerce/woocommerce/blob/11.3.1/plugins/woocommerce/includes/wc-user-functions.php)
uses WP insertion with customer defaults plus Woo registration validation,
customer-data filters and `woocommerce_created_customer`. Core WP creation will
not automatically run those Woo-specific signup hooks or customer emails/notices.
Do not call Woo APIs or replay Woo events from Core to mimic customer creation.
Checkout can collect missing billing/shipping values later; existing customer
roles, metadata, saved carts/payment methods, notices and order flows stay owned
by Woo and its extensions. Core itself creates no order, subscription, cart or
grant on registration.

### Email ownership and linking

An email/password signup without proof leaves the address unverified. A warning
email does not fix mistyping, account squatting or pre-hijacking. A genuine owner
recovering an address-based account must use native/provider proof and session
revocation; Core must not merge accounts, assign historic orders, or grant access
because a submitted email matches a customer.

The reviewed Woo version contains an email-verification subsystem: its
[boot source](https://github.com/woocommerce/woocommerce/blob/11.3.1/plugins/woocommerce/src/Internal/CustomerEmailVerification/CustomerEmailVerification.php)
links matching guest orders via `wc_update_new_customer_past_orders` on
`woocommerce_customer_email_verified`. Its
[credential listener](https://github.com/woocommerce/woocommerce/blob/11.3.1/plugins/woocommerce/src/Internal/CustomerEmailVerification/VerificationEventListener.php)
observes specific emailed reset credentials and completion; its
[controller](https://github.com/woocommerce/woocommerce/blob/11.3.1/plugins/woocommerce/src/Internal/CustomerEmailVerification/VerificationController.php)
has a separate account confirmation journey. These are Woo-owned behaviours,
not a reason to set a “verified” marker when Core inserts or logs in a user.
Do not invoke its email-matching link function or verification action from Core.
Older store versions and third-party hooks may behave differently: test the
actual operator stack and existing guest-order/account history before launch.

No mandatory Core email-verification feature is recommended for the deferred
embedded candidate **solely to run an open Sprint**, provided the owner accepts
unverified-address risks and no entitlement/linking/identity claim is made. On
stores or sites with approval/ownership requirements, delegate signup or require
their existing verification. Native emailed password setup is the simpler option
when mailbox proof before first login is desired; it is not a custom Core code-
verification implementation. Optional code verification remains deferred.

### Read-only Pro integration findings (public-safe summary)

The existing integration restricts at Core's member access boundary without
promoting an incoming denial. Open participation, restricted commercial access
and tutorial restrictions are separate. Commercial providers aggregate qualifying
grants by WordPress user identity; purchase does not start a Sprint. Registration
must not change their status/OR rules or preserve entitlement by copying it.

The current public gate and denial information action are presentation-only and
independently constrained by safe public-discovery/information policy. Public
Directory output is descriptive rather than personalised: no member progress,
entitlement or account-specific controls should be injected into its cacheable
output. A hidden-from-Directory Sprint is not necessarily unavailable by direct
Runner link; preserve that distinction and do not promote hidden restricted
content. Shared navigation/branding can continue through Core's existing helpers.

**Only proposed Pro adaptation:** after a separately approved Core classification
seam exists, supply an explicit presentation classification for safely known open
or restricted Sprints, retaining unknown/concealed cases, tutorial exclusion and
all current gate/information policies. Add focused integration tests for both
plugin load orders. No entitlement-provider, account-role, order, Directory,
navigation or progress rewrite is justified. Without that adaptation Core uses
neutral sign-in/unknown behaviour, preserving today's safe gate. This report
neither modifies Pro nor authorises that follow-up.

## 12. Screen, accessibility and failure requirements

Access screen: linked site logo if configured, one useful heading, short generic
explanation, prominent Sign in/Create account as appropriate, Forgot password,
Return to Sprint and Back to website. Restricted/unknown information comes only
from already safe hooks/policy, never private content. No paid feature is required
or advertised to make Core signup work. Reuse configured colours with contrast
checks; branding can still produce inaccessible authored combinations.

Use server-rendered ordinary links/forms with no-JS operation, visible labels,
correct `autocomplete` values (`given-name`, `family-name`, `email`,
`new-password`/`current-password` if a form is eventually built), paste/password-
manager support, logical focus order, focusable error summary linked to fields,
associated inline errors, busy/retry status and preserved non-sensitive input.
Test at 320/390px, 200%/400% zoom, keyboard and an actual screen reader. All copy
and errors must be localisable; allow long translations/RTL. No focus traps,
timed puzzle, colour-only errors or JavaScript-only recovery.

Use the site's native privacy-policy URL where available; optional operator-owned
terms link can be existing site content. Do not invent compulsory legal acceptance,
marketing consent or a new terms-management system. Future signup must explain
account/progress data and functional cookies in the site's privacy text. Cookie-
consent integrations must permit essential authentication/CSRF cookies or produce
a clear accessible recovery message; never hide a cookie failure as “wrong password”.

| Failure | Required recovery |
| --- | --- |
| Signup refused/duplicate/blocked | Generic safe result with sign-in/recovery, useful field errors, no password echo or existing account update |
| Account persisted but notification/login fails | Keep the account; offer native sign-in/recovery and original Sprint link. Do not delete it, recreate it, overwrite its password or retry notification on every reload |
| Mail delivery unavailable | Operator remediation; do not claim delivery, auto-verify email or bypass an approval requirement |
| MFA/SSO interruption | Complete provider-owned journey; follow canonical link after auth, without a false success screen |
| Already signed in / identity changes in another tab | Use actual identity on next request; refuse new-account POST/implicit account switching; refresh CSRF state |
| Expired form, rejected cookie or throttling | Accessible retry/reload/help and native/provider sign-in, preserving progress and avoiding retry storms |
| Provider ignores return or destination becomes invalid | Native sign-in fallback; original canonical Sprint link or authenticated My Sprints, no external redirect guessed from request data |

## 13. Proposed implementation boundaries

All entries below require new implementation authorisation.

| Concern | Smallest proposed ownership |
| --- | --- |
| Configuration/admin notice | Focused participant settings class using existing Settings API page; one Core option, no migration/activation-created account settings |
| Guest access presentation | Runner coordination plus a dedicated safe template branch; reuse branding/navigation/assets only on Runner requests |
| Guest classification | AccessManager method and one tightly documented presentation enum filter, no change to existing member provider interface or SE-017 semantics |
| Launch auth/signup/recovery | WP/native or configured existing UI; no new public account REST endpoint, rewrite, custom user/session table or dependency |
| Return | Server-generated canonical hints and explicit links; no universal redirect adapter and no global login hijack |
| Deferred embedded form | Dedicated account service/controller, not ProgressService/repositories or REST progress controller. Prefer same canonical Runner POST before output; no new route is inherently needed. Must re-resolve Sprint and policy on POST, use its own guest-CSRF/replay/abuse boundary, and keep progress untouched |
| Deferred storage | WP owns users/usermeta/auth sessions. Any short-lived abuse/replay state must have a separately justified bounded store and cleanup policy; no schema/option implementation in this ticket |

Preserve CPTs, metadata, routes, attempts, historical data, member REST contracts,
public actions/filters, PHP/WP floors, version/schema and rewrite lifecycle. No
automatic migration of identities, email linking, Core commerce dependency or
Directory personalisation. Existing third-party Runner template overrides need
documented fallback/upgrade tests for any new guest state; they must not receive
private data merely to support the new layout.

## 14. Sequenced tickets and approval gates

These are proposed work packages, not automatically opened or assigned tickets.
No effort estimate is claimed as fact.

| Sequence | Scope and dependencies | Objective acceptance |
| --- | --- | --- |
| D1 — Owner decision | Approve native handoff launch versus further embedded spike; accept/reject email-return limitation; confirm initial multisite policy | Written choice of account mode UX, no mandatory Core verification decision, and explicit scope. Feature freeze otherwise remains |
| I1 — Configuration and safe classification | Depends on D1. Settings, persistent notice, strict URL validation, pure presentation classification; no account handler | Fresh/upgrade remain unset; no public signup until privileged valid choice; invalidation reopens notice; invitation resolves it; existing accounts and tutorial continue; unknown providers fail closed; exact-true SE-017 regression passes |
| I2 — Branded access and delegated journeys | Depends on I1. Native sign-in/reset, external/invitation modes, native signup handoff when approved and secured | All state/redirect rows above pass with no private content/attempt creation; canonical sign-in return; provider-ignored/email return fallback is usable; no-JS/mobile/keyboard/localisation checks; no user/order/entitlement mutations by presentation |
| S1 — Public signup security/return spike | Essential before enabling native public signup on the target stack; before any embedded implementation. Disposable sites, synthetic users, captured mail only | Demonstrate native/provider protection coverage on direct as well as Sprint-linked paths; role/WP-flag restrictions, mail/recovery, CSRF/login-CSRF and concurrent duplicate behaviour; record residual native race limitations. If exact email return is required, prove scoped bridge or report no-go |
| S2 — Embedded candidate spike (optional) | Only if owner still requires preferred chosen-password/immediate UX. Depends on S1; single-site first | Proven browser-bound guest CSRF, atomic bounded abuse/replay state, own-endpoint duplicate serialisation, partial failure recovery, native validation/notification lifecycle, and denied/MFA/cookie-suppressed/SSO cases. No policy bypass. Recommend no-go or smaller scope if guarantees cannot be met |
| P1 — Narrow Pro classification adaptation | Separate Pro approval, after Core contract; read-only discovery is not permission to modify Pro | Both load orders; open/restricted/hidden/unknown/tutorial; existing gate/info/navigation and providers unchanged; no personalisation of public Directory; registration grants nothing |
| V1 — Integration and launch acceptance | Depends on chosen I/S/P scope; staging/production activity still requires separate authorisation | Execute matrix below, review docs/privacy/settings and security failures; retain versions until separately authorised release preparation. Merge/deploy/release are separate decisions |

Optional follow-ups: scoped same-browser return helper, approved provider adapters,
embedded forms after successful spike, broader multisite account UX, optional email
verification, anti-spam provider UI and additional profile fields. Guest Sprints,
anonymous progress, magic-link authentication, marketing automation and account/
commerce reconciliation are outside this work.

## 15. Implementation acceptance matrix (not executed here)

| Dimension | Required cases / invariant |
| --- | --- |
| Runtime | Core-only WP 6.0/PHP 8.0 floor on isolated local infrastructure; current WP 7.1.5 with supported deployment PHP 8.3 (available CLI 8.3.36); current-stable PHP 8.6.0 compatibility lane, subject to upstream WP/dependency support. Re-pin patch versions at implementation. Cross-corner current WP/PHP 8.0 only if upstream supports it; mark exclusions. MySQL/MariaDB InnoDB with non-default prefix; SQLite only as supplementary coverage |
| Commerce stack | Current WC 11.3.1 requires WP 7.0+, so test Core+Pro/current WC on compatible current WP; use an operator-supported older WC only in isolated compatibility tests if a WP 6.0 commerce promise is needed. Do not force latest WC onto the floor or infer premium compatibility from doubles |
| Config/install | Fresh and upgrade, unset/all three choices, bad enums, bad/deleted URLs, changed domain/flag/role, tampered admin nonce/capability, branding reset isolation, notice scope, tutorial install/skip/run and reactivation with preserved settings/content |
| Sprint availability | Published/open, restricted, policy unknown, Directory-hidden, missing/invalid slug, query spoofing, draft/private/future/trash, password, unlaunchable/broken chain/unpublished Steps, tutorial marker corrupt/duplicate. No guest private data or progress, no promotion on unavailable states |
| Identity/journey | Guest/new user; existing Subscriber/customer/admin; already logged-in signup; two users/tabs; not-started/active/completed/history retained. Sign-in/register/recovery with captured local mail; disabled signup, existing email, wrong password, expired/reused reset link and wrong-account cookies |
| Handoffs | Canonical Sprint including subdirectory/permalink/encoded slug, rename/unpublish during signup, native URLs and login filters, external hint accepted/ignored, invalid login fallback, looping destinations and cross-device email fallback |
| Security negative | CSRF including logged-out nonce replay/login-CSRF, crafted Origin/Referer, cross-site form, open redirects/CRLF/user-info/encoding, arrays/oversize/Unicode, role/meta/ID injection, enumeration/timing, honeypot/autofill, spoofed proxy headers, shared-IP behaviour, rate saturation/storage failure and slow/parallel requests |
| Concurrency | Independent PHP/HTTP workers same normalized email/username and replay token; different emails; retries after commit/mail/auth failure; native/other registration running concurrently. Document the limit of endpoint-only locking; never delete an existing account to repair a race |
| Authentication plugins | Real native login and recovery; MFA required/optional, rejected credentials, cookie suppression, passwordless/SSO redirect and approval/verification restrictions. Test actual selected plugin versions; no direct cookie fallback or false successful login |
| Privacy/cache | Separate guest/member sessions, cache warmed by each, dynamic form expiry, no credentials/PII/session tokens in URLs/logs/JS storage. CDN/page/object-cache bypass, HTTPS/proxy/cookie paths, essential-cookie consent and blocked-cookie recovery |
| UX/accessibility | No JS, keyboard, real screen reader, mobile 320/390px, zoom/reflow, long translations/RTL, labels/errors/autocomplete/password manager, slow submit and accessible abuse-control fallback |
| Later checkout | Core-created low-privilege user signs in and purchases with same ID in classic and block checkout; missing billing fields collected; no duplicate account/order/role conversion or retroactive email linking by Core; existing customer records, carts/notices and entitlements preserved. Test HPOS modes and real premium plugins if claimed |
| Regression/lifecycle | Existing Core suites/static checks; Pro focused integration/load orders; current/floor versions; auth/access/nonce/cross-Sprint/idempotency/stale attempts unchanged. Lifecycle checks only if authorised implementation touches lifecycle; no progress deletion on deactivation |

Tests must use guarded disposable local sites, synthetic account addresses and
captured local mail with outgoing delivery blocked. No real accounts/orders or
live hosting experiments are authorised by this discovery issue.

The [PHP source downloads page](https://www.php.net/downloads.php?source=Y)
reported current stable **8.6.0** (19 November 2026), and 8.3.37 for the 8.3 line.
The local checks below used the installed **8.3.36** CLI; they establish neither
8.6 support nor a full current/floor runtime matrix. No runtime was upgraded here.

## 16. Actual checks and remaining evidence

Performed: fetched and compared Core integration base, read issue/comments (zero
comments), inspected current Core and read-only Pro integration source, inspected
tagged current/floor WP and current WC sources, reviewed native API and security-
provider documentation. No throwaway account prototype was necessary to establish
the source-level gaps; none was run or committed.

| Check | Actual outcome |
| --- | --- |
| `php vendor/bin/phpcs --standard=phpcs.xml.dist -s` | Completed without findings; direct bundled binary used because Composer CLI is not on PATH |
| `php tests/release.php` | PASS |
| `php tests/integration/assets.php` | PASS: 12 assertions; this suite does not bootstrap a WP site |
| `node --test tests/js/*.test.cjs` | PASS: 39 tests, 0 failures/skips |
| Documentation relative-link and issue-snapshot check | PASS: local link targets in all five changed documents; archived body matches the fetched issue |
| `git diff --check` / docs-only scope check | PASS: five documentation files only; no runtime, test, package, version or schema edits |

NOT RUN: WP account creation/login/reset HTTP experiments; current/floor WP/PHP
runtime matrix; Core WP integration/lifecycle/regression suites; MySQL/MariaDB
signup races or limiter stress tests; native/external provider browser journeys;
Core+Pro execution; real WC checkout/HPOS and premium Subscriptions/Memberships;
actual MFA/SSO/security plugin combinations; assistive-technology/mobile acceptance;
hosting/CDN/proxy/cache/cookie-consent and mail-delivery acceptance. There is no
account implementation to execute in this documentation-only ticket, and no
disposable account/mail/commerce fixture was provisioned. Existing local test
sites were not repurposed. Historical validation results are not new passes.

Remaining owner decisions are UX scope, native mail/return tradeoff and supported
network/provider policy; technical launch gates are the target stack's abuse,
authentication, mail, cache and checkout evidence. These block **implementation
or launch claims**, not completion of this discovery. Review the proposed tickets
before any runtime work. No version/schema/release change is requested here.
