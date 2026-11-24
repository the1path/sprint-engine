# Tutorial marker contract — SE-030 / PRO-018

Agreed issue boundary: [Core SE-030](https://github.com/the1path/sprint-engine/issues/21)
and [Pro PRO-018](https://github.com/the1path/sprint-engine-pro/issues/53).
This contract is supplied in development source; version `0.3.0` does not establish
SE-030 availability. Release and combined-site acceptance require separate approval.

| Property | Contract |
| --- | --- |
| Object | Only the installed `sprint_engine_sprint` post |
| Exact protected key | `_sprint_engine_tutorial_admin_only` |
| Installer representation | Exactly one metadata row with string value `1` |
| Restriction test | `metadata_exists( 'post', $sprint_id, '_sprint_engine_tutorial_admin_only' )` |
| Missing key | Preserve all existing ordinary-Sprint rules |
| Any present value | Administrator-only; empty, `0`, `false`, malformed serialized data and duplicate rows do **not** remove the restriction |
| Administrator | Authenticated current user with effective `manage_options`; never role-name matching |
| Identity | Post ID plus marker, independent of title, slug or authored copy |

Core applies this decision in `AccessManager::can_access()` **after** the provider
and all `sprint_engine/user_can_access_sprint` callbacks. A marked Sprint returns
true only for the signed-in current administrator, including when an external
provider denies; an external grant can never promote a non-admin. Authentication,
publication, passwordlessness, Launchable/structure, nonce, current-user ownership,
attempt identity and Step-to-Sprint validation still apply at normal member
boundaries. This is a tutorial-specific rule, not a change to ordinary access.

Core Runner, REST start/complete/restart (including locked mutation rechecks), and
My Sprints use the canonical access decision. Resume occurs through the guarded
Runner; Core has no separate public REST resume route. Native Sprint/Step editor
REST remains administrator-only. The Dashboard also excludes a denied marked
item after its trusted item filter. Internal ProgressService/history APIs remain
trusted application APIs: their consumers must still authorize member output.

The key is protected by WordPress's leading-underscore convention, is absent from
registered editable REST metadata and has no UI checkbox. Routine saves do not
write or clear it. Do not copy it to a newly authored ordinary Sprint. Authorized
PHP/database administrators remain trusted and can alter metadata; the marker is
not a sandbox against installed malicious PHP.

Core's non-autoloaded `sprint_engine_tutorial_id` option is only the installer
pointer/mutex. It is **not** an entitlement or discovery contract. A canonical
marked post can be recovered when its pointer is missing; conflicting live
pointers, duplicate tagged posts or malformed rows cause installation to fail
closed without overwriting anything. Deleting the Sprint deletes its own marker
through normal WordPress deletion. Historical progress is never repurposed.

## Pro discovery obligations

PRO-018 owns its Pro-side checks and fixes on its separate branch. Core does not
modify Pro source, introduce a resource API or silently change unmarked discovery.

- Exclude **every marked Sprint** from the **public** Directory projection,
  including for an administrator viewing that public projection. Filter before
  computing cards, search results, counts, pagination and facets.
- Apply the same exclusion to public Collection and Journey discovery/hubs and
  indirect Sprint relationships. Do not disclose title, excerpt, image, links or
  relationship labels/counts for a marked tutorial through those projections.
- Private/member integrations must call canonical Core access for the actual
  current user; a purchase, membership or other entitlement is insufficient.
  Pro resources must independently validate current Core access and association.
- Do not depend on the new Core PHP class, a version number, title/slug, a truthy
  `get_post_meta(..., true)` value or the installer option. The exact presence test
  above works before SE-030 is deployed and preserves baseline when absent.
- Include malformed/present markers and an actually entitled member in Pro's
  public discovery and direct delivery acceptance tests.

Core acceptance proves the final access veto with an unchanged Pro installation
and its actual AccessPolicy plus a granting entitlement-provider test double.
It does not certify PRO-018 public Directory/Collection/Journey fixes. Combined
staging rollout must wait for that separate non-disclosure validation.
