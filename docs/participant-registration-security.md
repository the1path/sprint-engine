# Public participant registration: operator setup

Built-in registration is optional and unreleased. Use an existing provider when
your approval, verification, CAPTCHA or identity system requires its own form.
Sprint Engine cannot certify or replace that system. See the full
[Participant Accounts contract](participant-accounts.md) and
[tested scope](validation/se-031-3-native-registration.md).

1. **Check hosting.** Home, WordPress login and Runner must use real HTTPS with
   WordPress correctly recognising it behind your trusted TLS proxy. Test native
   login, secure cookies and canonical returns. Built-in signup refuses HTTP;
   there is no bypass switch. Native mysqli, owned multiple database named locks,
   autocommit, no active transaction, SQL savepoint support and a stable
   nonpersistent connection must pass readiness checks. The savepoint probe
   deliberately receives missing-savepoint error 1305 / SQLSTATE 42000 in healthy
   autocommit mode; database statement/error monitoring may record it. Core contains
   that expected result; other errors fail closed. Do not remove transaction checks
   or force a commit to make readiness pass.
   Database drop-ins, transaction/pool/proxy arrangements and untested engines
   require host acceptance or external registration.
   Verify candidate ZIP activation as well: exact WordPress 6.0 with MySQL 8.0.45
   currently refuses fresh table verification in the unchanged installer. See the
   [polish validation](validation/se-031-3-1-registration-polish.md) and resolve this
   separate host/release gate without weakening installer checks.
2. **Check WordPress.** In Settings → General, deliberately permit registration
   and choose standard Subscriber. Do not grant Subscriber extra permissions.
   Core never changes those settings. Built-in is single-site only.
3. **Protect every signup path.** Include standard WP registration if available,
   WooCommerce/My Account/checkout, REST/independent provider forms, plus login
   and reset. Configure platform rate controls for shared-IP/proxy traffic;
   Core's small form limiter uses the actual peer, not forwarded headers. It is
   not a site-wide security system or DDoS control.
4. **Check policies and delivery.** Test native validation, password hashing,
   normal sign-on, your MFA/approval rules and password reset with synthetic
   accounts. Core sends only the native initial admin notification. It does not
   verify a mailbox, establish consent or send a welcome/setup email. Test real
   mail separately before enabling public accounts. Protect recovery and allow
   for squatting/incorrect email addresses.
   New generated logins derive from names, never email, and can appear in native
   WordPress public author URLs. They are not secrets. Review author-archive
   exposure under your own privacy policy; existing identities are not renamed.
5. **Exclude application pages from shared caches.** Runner forms, POST/errors,
   native login/MFA and authenticated pages must honour private/no-store/noindex.
   Never cache or log form bodies/cookies/passwords. Test guest/member separation
   with your actual CDN/proxy. Configure reliable WP-Cron if timely removal of
   expired temporary hashes is required; quiet sites can delay physical purge.
6. **Save explicitly.** Choose Sprint Engine registration in Sprints → Settings,
   review the checks and tick the security confirmation only after these tests.
   The highlighted confirmation group is presentation only; saving still requires
   the same deliberate checkbox and successful prerequisites. It never confirms
   automatically.
   Keep the suggested native-form closure checked unless another integration
   requires it. Unchecked leaves native signup outside Core's limiter. Closure
   affects only the standard resolved WP register action; its Register link
   remains with clear guidance. Independent providers/Woo remain independent.
7. **Test the complete journey.** Open a real eligible Sprint as a guest, create
   an account, complete any native MFA, reach Ready, then explicitly Start. Verify
   sign-in with email, reset, phone/narrow/no-JS layout, existing member work and
   external/provider return. Unknown/restricted content never promises signup
   or entitlement. Old Pro needs the separately approved classifier adapter to
   advertise open Sprints; member access continues to use its existing policy.

If policy, roles, URLs or storage become structurally invalid, Core refuses its
form and revokes confirmation. Repair and save confirmation again. A fresh
confirmation invalidates old forms, including after temporary state cleanup. A
missing live row, including deletion while a request waits for the signup mutex,
is not recreated by that request. Investigate deletion/restore/cleanup jobs before
repairing and explicitly reconfirming; never restore an empty row under a live
confirmation. Changing mode or deactivating Core also revokes it; reactivation
alone does not enable signup.
Native signup then follows WordPress's global policy, so protect the reopened
route. Temporary rate/lock/capacity/outage failures keep an already-valid native
gate closed. A plain HTTP request is refused without erasing consent site-wide
or reopening the confirmed HTTPS native gate.

If registration created an account but mail/authentication/cookies failed, retain
the account and use native sign-in/reset and the original Sprint link. Do not
delete/recreate it or bypass MFA. Automatic return from arbitrary email/devices
and universal security-plugin compatibility are not guaranteed. Core 0.4.0
release preparation, SE-031.4 acceptance and owner staging approval remain
separate gates; this guide is not deployment authorisation.
