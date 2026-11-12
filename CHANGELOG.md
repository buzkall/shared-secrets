# Changelog

All notable changes to `arzcode/shared-secrets` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [1.3.0] - 2026-11-12

### Added

- `->recipientLabel()` on the plugin sets how a user reads in the recipient select (its options, its search results and the selected user), to tell apart users who share a name, for instance by adding their surname or role: `->recipientLabel(fn (User $user) => "{$user->name} {$user->surname} · {$user->role->getLabel()}")`. The closure receives the user as `$user` or `$record`; without one, or when it returns a blank value, the select keeps the `user_title_attribute` from the config. The secrets table, the reader page and the notifications always use that attribute.

### Fixed

- The recipient select no longer sends a request each time it is opened: its options were fetched again from the server although they were already rendered with the page, which took seconds on a busy panel.
- While every allowed recipient fits in the select's options (50, `Users::OPTIONS_LIMIT`), typing filters them in the browser instead of sending a request per keystroke. With more recipients than that, typing still searches the database on the `user_search_columns`.

### Upgrading

- Filtering in the browser only matches the label shown in the select. On sites with 50 recipients or fewer, typing a user's email or any other column from `user_search_columns` no longer finds them unless the label includes it; add it to `->recipientLabel()` if your users search by it.

## [1.2.0] - 2026-11-03

### Added

- `languages.hosts` in the config, for sites that serve each language from its own domain: it maps a locale to its host (`example.cat`, or `https://example.cat`), or names a static method returning the map as `[App\Enums\Language::class, 'hosts']`, which is called once per request or queued job. The secret's link is built and signed on the host of its language, and a regional variant uses its fallback's host. It ships as `null`, which keeps every link on the application URL.
- When a guest opens a secret targeted at a user from a language host, the login page they are sent to is on that same host, so the link they are returned to after logging in is kept in the session they log in to.

### Upgrading

- Every host listed in `languages.hosts` must reach the application and serve the panel's routes, its login included. A panel bound to its own domains with `->domain()` or `->domains()` keeps its links and its login on those domains and ignores `languages.hosts`.
- Every host listed in `languages.hosts` also needs a working session: leave `SESSION_DOMAIN` empty so each domain keeps its own cookie, or use subdomains and set it to the shared parent (`.example.com`). A `SESSION_DOMAIN` the language hosts are not part of leaves the reader page without a session (419 errors). If the application trusts only some hosts (`TrustHosts`), add every language host. With separate domains a recipient logs in once per domain, and passkeys or social and single sign-on callbacks registered for the main domain only will not work on the others; use subdomains in that case.
- A host with a path, a query or a scheme other than `http` or `https` is ignored, and the link stays on the application URL. Without a scheme, the link takes the scheme of `app.url`.

## [1.1.0] - 2026-10-20

### Added

- A language selector on the push form, shown when `languages.source` in the config names a backed enum or an Eloquent model describing the site's languages; it ships as `null`, which keeps the selector hidden. The reader page and the recipient's email and panel notification are shown in the chosen language. `languages.fallbacks` maps a regional variant to the locale its missing strings come from (`['ba' => 'ca']`).

### Upgrading

- The secret's language is stored in a new `locale` column. Nothing reads or writes it until it exists, so updating is safe without it; while `languages.source` is set and the column is missing, the language field is disabled and tells the sender to run it: `php artisan vendor:publish --tag=shared-secrets-migrations` and `php artisan migrate`.

## [1.0.3] - 2026-10-14

### Fixed

- After pushing a second secret from the same page, the link section showed the previous secret's link, or nothing at all if that secret had already been used up, instead of the new link.

### Changed

- The brand logo on the reader page defaults to `6rem` instead of `3rem`, and is kept inside the card's width without distortion. Set `revealLogoHeight()` to change it, or `null` to keep the panel's height.

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
