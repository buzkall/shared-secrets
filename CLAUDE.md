# CLAUDE.md — contract for working on `arzcode/shared-secrets`

This file is the single source of truth for conventions in this repository. Every session must read it before touching code, and every PR must respect it.

## What this package is

**Shared Secrets** is a Filament 5 plugin to share passwords and secrets through self-destructing signed links, in the spirit of [Password Pusher](https://oss.pwpush.com/). A panel user pushes a secret with a lifetime and a maximum number of views and gets a link; the content is encrypted at rest and wiped when the secret expires, runs out of views, is revoked or is deleted by its reader. A secret can be targeted at a user of the site, who is notified and must log in to open it.

- Composer name: `arzcode/shared-secrets`
- PHP namespace: `Arzcode\SharedSecrets`
- Config file: `config/shared-secrets.php` (`config('shared-secrets.*')`)
- Translation namespace: `shared-secrets::shared-secrets.*`
- Plugin id: `shared-secrets` (`SharedSecretsPlugin::ID`)

Targets: **PHP 8.4+, Laravel 12 or 13, Filament 5.8+, Livewire 4**.

## Structure

| Where | What |
|---|---|
| `src/SharedSecretsPlugin.php` | Filament plugin: registers the page and the public reader route, holds the fluent options |
| `src/SharedSecretsServiceProvider.php` | Spatie `PackageServiceProvider`: config, translations, migration, commands, install steps, prune schedule |
| `src/Pages/ManageSharedSecrets.php` | Panel page: push form, generated link, table of sent secrets, activity slide-over |
| `src/Pages/RevealSharedSecret.php` | Public reader page (`SimplePage`) behind the signed route |
| `src/Actions/` | `CreateSharedSecret`, `ConsumeSharedSecret`, `CloseSharedSecret`, `NotifyRecipient` |
| `src/Models/` | `SharedSecret` (ULID key), `SharedSecretEvent` |
| `src/Support/` | `SecretUrl`, `Users`, `RecipientPanels`, `PanelProviders`, `Cast` |
| `src/Commands/` | `PruneSharedSecretsCommand`, `UninstallCommand` (the install command is built by Spatie from the provider) |
| `src/Http/Middleware/ProtectRevealResponse.php` | Headers and `HEAD` handling for the reader route |

There are no Blade views and no CSS or JS assets: both pages are composed from Filament schema components, so a host needs no theme change and no `npm run build`. Keep it that way; if a view ever becomes necessary, the host's theme needs an `@source` line and the installer a step for it.

## Filament 5 conventions (non-negotiable)

1. **Plugin contract.** Panel-specific wiring (the page, the reader route) is done from `SharedSecretsPlugin::register()`. Package-wide wiring (config, migration, commands, schedule) is done from the service provider.
2. **Options are fluent methods on the plugin**, evaluated with `EvaluatesClosures`. Anything a host decides per panel (navigation group, who is authorised, who can be a recipient, the reveal path) lives there, not in config. Config holds what is global: lifetimes, limits, table names, notification channels.
3. **`SharedSecretsPlugin::get()` returns null** on a panel that did not register the plugin. Every caller handles that; never assume the plugin exists.
4. **Schemas, not `Form`/`Infolist` shims.** Pages define `form(Schema $schema)` and `content(Schema $schema)`. Never use the deprecated `Placeholder` field; use `TextEntry`.
5. **Computed table columns set `->sortable(false)`.** Hosts may make every column sortable globally. `note` is encrypted and therefore neither sortable nor searchable.
6. **Datetimes are displayed as `d/m/Y H:i:s`**, in columns, entries, emails and notifications. Never leave a bare `->dateTime()`.
7. **Every user-facing string is a translation key**, present in all of `resources/lang/{en,es,ca}`. `tests/Unit/TranslationsTest.php` fails on a missing key.

## Database schema

Table names come from `config('shared-secrets.table_names')`. The migration is a published stub (`database/migrations/create_shared_secrets_tables.php.stub`), never auto-loaded in a host. Keep it a single file with no PHPDoc.

| Table | Columns |
|---|---|
| `shared_secrets` | `id` (ULID), `panel`, `creator_id`, `recipient_id` (both nullable, null on delete), `content` (encrypted, nullable), `note` (encrypted, nullable), `passphrase` (hash, nullable), `max_views`, `views_count`, `expires_at`, `requires_retrieval_step`, `allows_deletion`, `closed_at`, `closed_reason`, timestamps |
| `shared_secret_events` | `id`, `shared_secret_id` (cascade), `type`, `user_id` (nullable), `ip_address`, `user_agent`, `created_at` |

- `SharedSecretStatus` (`Active`, `Expired`, `Exhausted`, `Revoked`, `Deleted`) is both the computed status and the value of `closed_reason`. `SharedSecret::status()` reports `Expired` for an open secret past `expires_at`, before the prune command has closed it.
- `SharedSecretEventType`: `Viewed`, `PassphraseFailed`, `DeletedByRecipient`, `Revoked`.
- The user model is never hard-coded: `Support\Users::model()` reads `config('shared-secrets.user_model') ?? config('auth.providers.users.model')`. That key ships as `null`, so it must fall back with `??`; `config($key, $default)` would never apply the default (Rector's `ApplyDefaultInsteadOfNullCoalesceRector` is skipped for this reason).

## Security rules (this is the whole product)

Each of these has a test that fails if the rule is removed. Do not weaken one without replacing its test.

1. **The plaintext is never kept in Livewire state.** `RevealSharedSecret::$revealedContent` is a protected property, rendered once by the request that spent the view. Its entry carries `wire:ignore` so the text stays on screen during later requests. Never make it public, never put the content in a form field. On the panel page, `create()` refills the form so the content and the passphrase leave the snapshot, and only the secret's id is kept.
2. **The sender never reads a secret back.** No column, action or page shows `content` after creation. The link is rebuilt on demand by `SecretUrl::for()` and is never stored on the secret.
3. **Closing wipes.** `CloseSharedSecret` nulls `content` and `passphrase` and keeps the row. Every way a secret ends (expiry, last view, revoke, reader delete) goes through it.
4. **Views are spent under a row lock.** `ConsumeSharedSecret` runs in a transaction with `lockForUpdate()` so concurrent readers can never exceed `max_views`. It re-checks recipient, expiry and passphrase itself; never trust the page's checks alone.
5. **Livewire requests are not signed.** The `signed` middleware only guards the first `GET`. `RevealSharedSecret::hydrate()` re-authorises every later request, and each action re-checks what it needs (`canDeleteNow()`).
6. **Fetching the link never spends a view.** `ProtectRevealResponse` answers `HEAD` with a 204, and a `GET` only renders the page: without the retrieval step the reveal happens from a `wire:init` request, which a scanner that does not run JavaScript never makes.
7. **Targeted secrets open only for their recipient.** A guest is redirected to the login of a panel the recipient can access (`RecipientPanels::loginUrl()`), with the link stored as the intended URL; any other user gets a 403.
8. **Wrong passphrases pause, never destroy.** Attempts are throttled per secret and IP address, and `passphrase.max_failures` recent failures from any address lock the secret for `passphrase.lockout_minutes`. The link alone must never be enough to wipe a secret its reader has not opened.
9. **Deleting needs a view.** "Delete it now" is only offered to a reader who has revealed the secret, and only when the sender allowed it.
10. **The reader page is uncacheable, unframeable, unindexable and sends no referrer**, set by `ProtectRevealResponse`, because a host's own security headers do not cover panel routes.
11. **Senders see only their own secrets** unless the plugin's `viewAllSecrets()` allows more, and even then the copy-link action is offered to the sender only: for an untargeted secret the link is as good as the content.
12. **Recipients are scoped everywhere the same way.** The select's preloaded options, its search, its label lookup (which is what validates the submitted id) and `create()` all go through the plugin's `recipientQuery()`.

## Install and uninstall commands

`shared-secrets:install` is built in the service provider as a list of small, idempotent steps; `shared-secrets:uninstall` (`Commands\UninstallCommand`) mirrors it. Every install step needs an uninstall step, in the same commit.

| Install | Uninstall | Asks on uninstall? |
|---|---|---|
| publish the migration | delete the published migration | yes |
| publish the config (asks) | delete the published config | yes |
| run the migrations (asks) | drop the tables | yes |
| register the plugin, asked per panel provider | remove it from every panel provider | no |
| — | delete published translations | yes |
| `composer require` by the user | `composer remove`, through the `Process` facade | no |

`Support\PanelProviders` patches the host's `app/Providers/Filament/*PanelProvider.php` on PHP tokens, never on lines or raw characters, and every patch goes through `PanelProviders::parses()` before it is written. Do not simplify it back to regex handling; `tests/Feature/Support/PanelProvidersTest.php` holds a case per failure it was written for. The installer asks per panel because sharing secrets is rarely wanted in every panel of an application.

## Quality gates

- Every PR keeps **Pest green** (`vendor/bin/pest`), **Pint clean** (`vendor/bin/pint --test`), **PHPStan clean at level 10** (`vendor/bin/phpstan analyse`) and **Rector clean** (`vendor/bin/rector process --dry-run`; run Pint after applying Rector). All four run in CI.
- No behaviour lands without a test. Security rules in particular need a failing-if-removed test each.
- Update `CHANGELOG.md` under `[Unreleased]` with every user-visible change.
- Commit messages follow Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`, `test:`).

## Code style

`pint.json` is the Laravel preset with this project's overrides. Write code that already matches it:

- `fn()` and `function()` with no space before the parenthesis.
- `=>` aligned in multi-line arrays; no trailing comma in multi-line arrays or argument lists.
- Casts with no space: `(string)$value`.
- `new Foo` without parentheses when there are no constructor arguments.
- Prefer `->when()` on query builders to chained `if` statements.
- Curly braces on every control structure; explicit parameter and return types everywhere.

## Writing code for PHPStan level 10

`phpstan.neon` runs Larastan at level 10 over `src`, `config` and `database`. Write code that passes on the first run:

- Every array parameter, property and return type gets a PHPDoc type; closures get typed parameters and return types.
- Values that arrive as `mixed` (config, form state, model keys) are narrowed before use: `config()->string()` / `->integer()` / `->boolean()` / `->array()`, `Support\Cast`, or `is_string()` / `is_int()` guards. No bare `(string)` / `(int)` casts on `mixed`.
- Models declare their attributes with `@property` PHPDoc, since the migration is a stub Larastan cannot read.
- Never add `@phpstan-ignore` comments, baseline entries or inline `@var` overrides, and never widen a type to make an error go away.

## Testing

- Orchestra Testbench with an in-memory SQLite database and two real Filament panels registered from `tests/Fixtures`: `admin` (has the plugin and database notifications) and `client` (has neither). The fixture `User` reaches `admin` only with the `admin` role, which is how targeted-secret and notification tests tell the panels apart.
- The fixture `AdminPanelProvider` reads `config('testing.*')` flags (`forbidden`, `view_all`, `recipients`, `database_notifications`) so a test can flip a plugin option without registering another panel.
- `TestCase` turns on `Model::shouldBeStrict()`: hosts commonly do, so eager-load every relation a page touches.
- **Provider order matters.** `Filament\Support\SupportServiceProvider` must be registered before `Livewire\LivewireServiceProvider` in `tests/TestCase.php`.
- The install and uninstall tests write into the Testbench skeleton (`vendor/orchestra/testbench-core/laravel`) and clean up after themselves; `Process::fake()` keeps them from running `composer remove` for real.
- Test names state the result and its condition (`it('returns 403 to a logged-in user who is not the recipient')`); each test creates its own data and uses factory states (`->to($user)`, `->withPassphrase()`, `->withoutRetrievalStep()`, `->closed()`).
- Snapshot assertions include a positive control (the snapshot contains the secret's id) so they cannot pass on an empty snapshot.

## Workbench

`testbench.yaml` and `workbench/` serve the package with the test fixtures for manual checks: `vendor/bin/testbench workbench:create-sqlite-db`, `vendor/bin/testbench migrate`, `vendor/bin/testbench db:seed --class='Workbench\Database\Seeders\DatabaseSeeder'`, then `composer workbench:serve`. Use plain `migrate`, never `migrate:fresh`.
