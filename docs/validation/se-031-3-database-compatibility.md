# SE-031.3 — PR #31 database compatibility follow-up

28 November 2026. Unreleased, draft [PR #31](https://github.com/the1path/sprint-engine/pull/31),
following initial implementation `0367803c13733aa0c1f10e2084c64a4416b81567`.
Read with [initial validation](se-031-3-native-registration.md),
[accounts contracts](../participant-accounts.md) and
[operator guidance](../participant-registration-security.md).
Only registration storage, its tests and related documentation changed. No Pro,
versions, schema, dependencies, merge, deployment or release changes.

## Findings and smallest compatible boundary

**Confirmed on actual MySQL 8.0.45:** `SELECT @@in_transaction` returns error
1193 / HY000, unknown system variable. The initial MariaDB-only probe therefore
prevented otherwise supported MySQL registration. Checking only `@@autocommit`
would be unsafe: an explicit transaction leaves it at 1.

Both health and every owned-operation guard now share a short native SQL probe:

1. Check autocommit, original handle, connection ID and named-lock ownership as
   before; health still proves two simultaneous independent named locks.
2. Create a cryptographically random, valid 52-byte savepoint name on that exact
   handle. `SAVEPOINT` must succeed.
3. Release that savepoint. Successful release proves an active transaction and
   refuses registration. Only error **1305 / SQLSTATE 42000** proves that the
   savepoint was absent outside a transaction. Other failures refuse operation.

This uses common MySQL/MariaDB SQL and native mysqli available at PHP 8.0; no
version detection, MariaDB-only variable, optional transaction instrumentation,
privileged transaction view or new connection is involved in production. It
does not commit, roll back, change autocommit/isolation or consume a caller's
savepoints. Healthy operation deliberately generates the missing-savepoint SQL
result; database monitoring may record it. PHP reporting mode is not changed.
The expected result is contained with either returned errors or strict mysqli
exceptions, using PHP 8.0-compatible APIs.

See the primary [MySQL savepoint semantics](https://dev.mysql.com/doc/refman/8.0/en/savepoint.html),
[MySQL implementation](https://github.com/mysql/mysql-server/blob/mysql-8.0.45/sql/transaction.cc)
and [MariaDB savepoint semantics](https://mariadb.com/docs/server/reference/sql-statements/transactions/savepoint).
Autocommit-off is independently rejected, including before table access. Tests
exercise explicit empty, read-only, consistent-snapshot and write transactions,
and prove caller work remains uncommitted and unrolled back using a separate
observer connection. Core never attempts to repair a caller's transaction.

The critical section still disables wpdb reconnection, pins the original mysqli
handle and server connection, and verifies ownership before state writes and
final native user insertion. Its bounded autocommitted replay reservation stays
committed when a callback opens and later rolls back its own transaction.
Connection-loss and duplicate/contention tests remain unchanged. Cleanup alone
may release the named lock on the captured handle after transaction rejection;
`RELEASE_LOCK` cannot release another connection's lock, performs no account/state
write and cannot reconnect. This avoids retaining the mutex until connection end
when a callback leaves a transaction open. The caller retains its transaction.

## Missing-state race

**Confirmed in source:** readiness checked a live row before waiting for the
mutex, but the unconditional `INSERT IGNORE` afterward could recreate a deleted
row as empty. A consumed, still-signed form with a different email could then
lose its replay record under the same confirmation generation.

Under the owned mutex, Core now reads first. Only explicit initialization, with
freshly refreshed settings showing no saved confirmation, may create an absent
row. Registration and purge cannot initialize it. Missing live state causes a
safe failure and revokes confirmation, even during a competing setup save. The
row stays absent; recovery requires explicit new confirmation and generation.
Read-back validation, JSON bounds, cached-option invalidation and existing
role/policy/content checks remain in force.

The focused regression uses **independent PHP/native database connections**. A
worker holds the real registration mutex, observes the parent waiting in
`GET_LOCK` after its health probe, deletes the already-consumed form's live state,
then releases the mutex. It exercises registration and explicit initialization,
checks no empty row/account appears, and verifies old forms fail after deliberate
reconfirmation. No runtime test hooks or fake storage are used. The test's own
same-user process-list visibility is a harness requirement, not a production
permission requirement.

## Executed evidence

The [sanitized follow-up results](se-031-3-database-results.json) retain exact
counts, test labels, source hashes and the outstanding failure without credentials
or local user paths.

All installations/databases were disposable, marked and isolated on loopback,
with synthetic accounts, blocked outbound HTTP/mail and local captured mail.
No existing WordPress site or database was used. Current installs use a
`/wordpress` subdirectory; the floor install uses the root. Each browser lane has
two independent PHP workers behind actual local TLS with a client-only exception
for the self-signed certificate. Machine trust and DNS configuration are unchanged.

| Lane | Database | WordPress / PHP | Native security | Restricted grants | Transaction/race |
| --- | --- | --- | --- | --- | --- |
| Actual MySQL | MySQL Community 8.0.45, InnoDB, native mysqli | 7.1.5 / 8.3.36 | 267 passed | 4 passed | 48 passed |
| MariaDB current | MariaDB 10.11.11, InnoDB, native mysqli | 7.1.5 / 8.3.36 | 267 passed | 4 passed | 48 passed |
| MariaDB exact floor | MariaDB 10.11.11, InnoDB, native mysqli | 6.0 / 8.0.30 | 267 passed | 4 passed | 48 passed |

The actual server was provisioned from the official
[MySQL 8.0.45 Windows archive](https://cdn.mysql.com/Downloads/MySQL-8.0/mysql-8.0.45-winx64.zip),
SHA-256 `3de8effe49cd427942ade672d5f8b3517751e1df52a915b038790a9e093cb150`.
It used its own initialized data directory, TCP port, database and native mysqli
connections; it was not MariaDB in a MySQL compatibility mode. Binding was
verified as `127.0.0.1`, with MySQL X disabled. `VERSION()` reported `8.0.45`.

The new 48-check suite includes all three mysqli error-report modes (off,
error/warnings, strict exceptions), preservation of that mode, preexisting and
callback transactions, committed replay after callback rollback, mutex/retry
cleanup, both independent deletion races and explicit initialization recovery.
Existing 267-check suites also cover the fixed launch budgets, capacity,
malformed/missing/disappearing state, native identity/password/hash/reset and
policy/generation/cache contracts. Restricted-grant tests use an actual separate
SELECT/UPDATE-only user and throwaway options clone; INSERT denial cannot enable
registration and all test objects are removed.

The MySQL browser suite passed **98 checks**, including two-worker duplicate
token/email and bounded contention, connection kills before/during insertion,
actual Two Factor 0.17.0 challenge/completion, exact canonical international
returns, cookie/provenance controls, fresh Ready without progress, no-JS signup,
Settings API saves and deactivate/reactivate behavior. Unmodified Pro passed
**18 checks** in both plugin load orders with retained Core/Pro data and existing
identity/sign-in. MariaDB retained **98 + 11** main/boundary browser checks per
lane, current Pro **18**, WooCommerce **13** and accessibility **6** checks. Floor
MFA remains unchanged historical Two Factor 0.9.1 compatibility evidence, not a
production recommendation.

MySQL additionally passed **13 actual WooCommerce 11.3.1 checks** (My Account,
classic and block Store API HPOS checkout with synthetic COD), **11 boundary
checks**, **6 browser accessibility checks** and **2 repeated configured-connection
kill cases**. The latter kill the original native connection before and during
insertion: no account or authentication results. They use the actual configured
database credentials/host, without assumptions about MariaDB. Protected payments,
production gateways and other accessibility limits remain unproven as before.

MySQL, MariaDB current and MariaDB floor access/return-URL regression each passed
**289** checks on clean disposable baselines.
The current suite required its documented clean disposable baseline: running it
against leftover commerce/classifier fixtures first failed its known-open guest
expectation. Restoring the isolated baseline resolved that harness prerequisite;
no assertion or runtime access behavior was changed. Initial MySQL provisioning
also required a loopback test-user grant; no WordPress or security check was bypassed.

PHPCS, changed PHP syntax on 8.0/8.3, browser JavaScript syntax, **39 JS tests**,
release metadata, **12 asset assertions**, exact **90-file** runtime package and
`git diff --check` pass. General Core/Pro regression results in initial validation
remain earlier evidence; this follow-up does not relabel them as MySQL executions.

**Outstanding failure, reproduced again on MariaDB:** unchanged Pro History
stops after **26 passes** at `PRO-004 install/initialize leaves every existing
response/Core row and table definition unchanged.` Pro and its assertion remain
untouched. This is still an **SE-031.4 release acceptance investigation**, not
accepted or suppressed. Other complete Pro History cases are not claimed passed.

## Reproduction and operational limits

On an explicitly disposable, marked HTTPS/native-mysqli installation, copy the
candidate runtime and guarded fixtures as described in initial validation:

```sh
SE_TEST_DISPOSABLE=yes SE_TEST_WP_ROOT=/isolated/wordpress composer test:registration
SE_TEST_DISPOSABLE=yes SE_TEST_DB_ADMIN=yes SE_TEST_WP_ROOT=/isolated/wordpress php tests/integration/participant-registration-storage.php
```

Use the matching PHP configuration and separate initialized databases for each
lane. `proc_open` and visibility of the same user's waiting connection in
`information_schema.PROCESSLIST` are required for the database race harness.
It creates/drops only its disposable InnoDB canary table. The restricted-grant
test additionally needs disposable administrator grants to create/remove its
temporary database user and table. Neither harness belongs on an ordinary site.
Run the committed HTTPS browser scripts with that site's generated fixture,
admin credentials, two independent workers and local-only mail capture; environment
variables and setup are detailed in initial validation.

Executed evidence covers these exact single-writer native-mysqli stacks.
**Not executed/certified:** other MySQL/MariaDB releases, MySQL 8.4, WP 6.0 on
MySQL, database drop-ins, persistent/pooling/multiplexing/multiwriter/proxy setups,
production TLS/CDN/cache/mail/cron, sustained load/DDoS and other provider/MFA
combinations. Healthy probes do not certify host topology. Other previously
recorded accessibility, commerce and release limits remain. Recheck the actual
host and confirm explicitly after repairs/restores; use the existing provider
when unsupported. No transaction-check bypass or forced commit is appropriate.

Owner review, SE-031.4 acceptance (including Pro History), the separate Pro
classifier adapter and separately authorized Core 0.4.0 release preparation
remain outstanding. No merge, deployment or release occurred.
