# Changelog

All notable changes to `arzcode/shared-secrets` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.2] - 2026-10-08

### Fixed

- The published migration passes PHPStan at level 10 in the host: it checks that the configured user model is an Eloquent model and reads the table names with `config()->string()`, so nothing in it is `mixed`. It still uses no class from the package.

## [1.0.1] - 2026-10-08

### Fixed

- `shared-secrets:prune` no longer fails with a `TypeError` in a host that uses immutable dates (`Date::use(CarbonImmutable::class)`).

## [1.0.0] - 2026-10-03

### Added

- **Shared secrets page** in the panel: a form to push a secret (content, expiry, maximum views, optional passphrase, optional note, recipient) with the generated link shown in place, and a table of the secrets you sent with copy link, activity log, revoke and delete actions.
- **Reader page** on a public, signed panel route (`/{panel}/secret/{id}`), with an optional 1-click retrieval step, a copy button, the number of views left and, when the sender allows it, immediate deletion by the reader.
- **Targeted secrets**: a secret can be restricted to a user of the site, who is notified by email and, when a panel they can access shows database notifications, in the panel. Guests are sent to a login page the recipient can use; anyone else gets a 403.
- **Passphrase protection**, throttled per IP address and paused for `passphrase.lockout_minutes` after `passphrase.max_failures` wrong attempts from any address.
- **Activity log** per secret: views, wrong passphrases, deletions and revocations with their time, user, IP address and browser.
- `shared-secrets:prune` command, scheduled hourly, that wipes expired secrets and deletes the rows closed more than `prune_after_days` ago.
- `shared-secrets:install` and `shared-secrets:uninstall` commands. The installer asks, panel by panel, whether to register the plugin.
- Plugin options: `navigationGroup()`, `navigationSort()`, `navigationIcon()`, `authorize()`, `viewAllSecrets()`, `recipients()`, `recipientQuery()`, `revealPath()` and `revealLogoHeight()`.
- English, Spanish and Catalan translations.

### Security

- The content and the note are encrypted at rest and the passphrase is hashed. The content and the passphrase are wiped as soon as a secret expires, runs out of views, is revoked or is deleted by its reader; the row is kept for the activity log.
- The revealed secret never enters the Livewire snapshot, and the sender cannot read it back after pushing it.
- Fetching the link never spends a view: `HEAD` requests are answered without running the page, and a `GET` only renders it. The reader page is served as uncacheable, unframeable and unindexable, with no referrer.
- Every Livewire request on the reader page checks the recipient again, since those requests carry no signature.
