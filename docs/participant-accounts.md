# Participant Accounts (SE-031.2, unreleased)

This source slice adds administrator preferences and branded guest handoffs. It
does **not** create accounts, authenticate participants or close native registration.
WordPress.org still serves Core 0.3.0. See the [issue](discovery/se-031-2-issue.md)
and [validation](validation/se-031-2-participant-access.md).

## Administrator setup

Open **Sprints → Settings → Participant Accounts**, choose a mode and explicitly
save **Save participant settings**. Branding and Tutorial have separate forms.

| Mode | Behaviour in this slice |
| --- | --- |
| No saved choice | Existing native login redirect/public access gate; no new signup offer. Persistent setup notice |
| Sprint Engine registration | Saved preference, visibly **pending implementation**. Branded sign-in works; no account CTA or active native-form closure |
| Use an existing registration system | An eligible, explicitly known-open guest can follow Create account to the configured same-site provider. Existing-account sign-in stays prominent |
| Invitation only | Sign-in, native recovery and contact-administrator guidance; no Core account creation offer. This does not close other site registration |

Unset or invalid configuration produces a non-dismissible, actionable notice on
Core admin screens and the native WordPress Dashboard, for `manage_options` users
only. Valid invitation-only or external configuration resolves setup. Built-in
preference always retains an implementation-pending notice in this slice; invalid
WP flag/role/network policy is reported separately alongside it. No activation
redirect or Tutorial prerequisite is introduced.

### Existing provider destinations

Enter the full registration URL and, optionally, login URL. URLs must use the exact
`home_url()` scheme/host/effective port and stay within its subdirectory. HTTPS is
required; HTTP is accepted only for a loopback home with WordPress environment
type `local`. Published, passwordless native pages and account paths/query actions
are supported. A missing page at a custom provider route cannot be validated by
Core: the administrator must test the journey. Core never fetches these URLs.

User-info, fragments, whitespace/control/backslash tricks, nested encoding,
dot segments, protocol-relative/cross-origin URLs, nested redirect payloads,
secret/reset parameters, native administrative/mutation routes and obvious Runner/
Dashboard loops are refused. These intentionally bounded settings are not a general
URL builder. Use a simple provider landing URL rather than a precomputed action
token or a URL containing its own return destination.

Invalid submissions preserve the entire previous valid configuration. Destinations
are checked again whenever links are generated. Unpublishing/password-protecting
a selected page hides signup and restores the setup notice. An invalid optional
login URL independently falls back to native login without hiding valid signup.

The provider receives only a server-derived `redirect_to` hint to the canonical
Sprint; inbound query parameters cannot select it. Hints are limited to 2,048
bytes and the generated delegated URL to 4,096 bytes. The provider owns its forms,
authentication, security policy and final redirect. Configure and test that return.
Core also shows the original Sprint link when delegating. If the provider ignores
the hint, participants can use that original link after signing in. Automatic
return across arbitrary providers, email links or devices is not promised.
Native login and recovery URL filters remain in effect; no global login redirect
interception is added.

### Built-in preference and native registration checkbox

The native-form checkbox is suggested checked before the first deliberate built-in
save. Only an explicit settings save records the choice. Opting out remains
persistent across reloads and mode switches; it is not automatically checked again.
Expand the native **registration preferences and security requirements** disclosure
to review it. A saved unchecked choice has a persistent inline warning outside that
disclosure. No preference is inferred from activation, upgrade or a WP option.

**Both checked and unchecked remain dormant now.** The standard WordPress Register
link, GET form and direct POST remain under existing WordPress policy. Closing the
native endpoint before a functional replacement would create a dead end. Active
interception is deliberately deferred to SE-031.3; there is no production readiness
filter, hidden enable flag or placeholder account endpoint in this patch.

Future built-in signup requires effective WP registration permission, default role
`subscriber`, a real Subscriber role with `read` and no granted capabilities beyond
`read`/`level_0`, single-site operation, a supported native-password/custom-form
security stack and environment acceptance. Settings explains problems and links
directly to **Settings → General**. Core never changes the global flag/default role
or creates a role. Multisite must use native network/existing-provider guidance.

The operator must protect every exposed registration route, login, TLS, cache
configuration, mail delivery and provider policies. Closing one native form would
not protect WooCommerce or independent registration APIs/forms, eliminate native
email races, or establish universal security-plugin compatibility. UI-dependent
approval, verification, CAPTCHA, SSO/passwordless journeys belong to their provider.
No mandatory vendor/plugin or detection heuristic is introduced.

Owner-approved SE-031.3 scope supersedes the spike's optional-names recommendation:
**first and last names will both be required**, with respectful international/
Unicode validation and standard WP profile storage. Email and chosen password,
generated hidden username, native existing-account email sign-in/reset, admin-only
initial native notification and no mailbox verification/delayed checkpoint remain
the approved direction. Names are not marketing consent. These fields and account
POST handling are not implemented here.

## Participant experience

Configured, ready ordinary Sprints use the existing Runner logo, typography,
colours, spacing, website navigation and privacy link. The screen is generic:
no Sprint title, excerpt, image, Step, identity, progress, attempt or REST write
configuration is supplied. Known-open external mode can offer Create account;
restricted/unknown says **Sign in to check access** and makes no entitlement promise.
An independently safe existing information action may still appear through SE-017.
Tutorial guests retain the existing administrator login/public-gate path and never
get signup, including malformed/empty tutorial markers.

The links work without JavaScript. After authentication, a fresh canonical Runner
request rechecks readiness and actual member access. Ready/Resume/Completed/denied,
unavailable states and Start's explicit mutation remain authoritative. Merely
opening any guest/login/Ready page creates neither account nor attempt.

## Integration contract

```php
$kind = $access_manager->participant_access_kind( $sprint_id );
apply_filters( 'sprint_engine/participant_access_kind', $kind, $sprint_id );
```

This additive **presentation-only** seam returns exact lowercase `open`,
`restricted` or `unknown`. It never grants member access or invokes a member
provider/filter with a guest/fabricated user. Invalid IDs/posts are unknown.
Tutorial marker presence always returns restricted before the classifier.

The unextended final `LoggedInAccessProvider`, with no callbacks on
`sprint_engine/user_can_access_sprint`, is the only inferred Core-open policy.
Any custom provider/member filter makes the baseline unknown. **One coordinated
classifier callback** may explicitly classify all relevant restrictions. Multiple
registered callbacks, even if they might agree, are a collision: Core returns
unknown without calling them. Invalid output becomes unknown. Integrations must
coordinate under one callback rather than rely on priority/last-writer ordering.
This conservative contract is intentional and is not an entitlement API.

Runner calls classification only for a configured, ready ordinary guest, after
Availability and the existing public-gate filter. Unset, authenticated, unavailable
and tutorial paths do not invoke the classifier. A separately approved Pro adapter
can use this seam to assert known-open/restricted/unknown under its own complete
policy; Core contains no Pro metadata. Until then, unmodified Pro's member filter
causes unknown and existing authenticated functionality requires no coordinated
release. A classifier must not advertise hidden/unresolved policy as open.

SE-017 still receives `(false, $resolved_sprint_id)` once per ready guest resolve,
after publication/password/Launchable/structure/Step readiness. Only exact boolean
true selects 403 and the existing denial marker. In configured mode it can coexist
with the new generic guest body, including an explicitly classified open handoff;
it is never repurposed as a classifier. The denial action runs once, in the shipped
template after Core's guest/login body, only for error/403 with that marker.
Authenticated denials and generic 404/503 retain their existing placement/behaviour.
Trusted callbacks own escaping, independent safe information policy and disclosure.

Templates retain `runner_template`; configured guests have `guest` link context
and generic `title`, `state: guest/status: 200`, or the unchanged `error/403` denial
shape plus `guest`. Custom template overrides must render the guest context and
retain existing safe denial hooks if this feature is configured. They must never
render member controls merely because a Sprint is published or classified open.

All Runner responses keep private/no-store/no-cache and noindex/nofollow semantics.
No Directory personalisation, member-access/progress/REST change or new rewrite,
version, schema, account table or dependency is introduced.

## Option lifecycle

`sprint_engine_participant_accounts` is one small, non-REST array option containing
`mode`, `registration_url`, `login_url`, `disable_native`. Modes are `built_in`,
`external`, `invitation`; empty is safe unset. `disable_native` is a boolean after
the first deliberate built-in save or explicit opt-out, otherwise null. It remains dormant here.

An authorised administrator's first admin initialisation seeds safe unset storage
with autoload disabled, before registering the sanitizer. Frontend requests,
activation and upgrades do not select a mode. Settings API capability/POST/nonce
checks protect saves; strict complete-value validation prevents partial replacement.
Reads fail safely on corrupt shape and revalidate changing destinations/policy.
Reactivation/upgrades retain preferences. Branding reset, Tutorial decisions and
normal retention on deactivation/uninstall do not erase them. No migration runs.
