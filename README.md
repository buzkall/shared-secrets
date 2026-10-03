# Shared Secrets

Share passwords and secrets from your Filament panel through self-destructing signed links, in the spirit of
[Password Pusher](https://oss.pwpush.com/).

A panel user pastes a secret, picks how long it lives and how many times it can be viewed, and gets a signed link.
The content is encrypted at rest and wiped as soon as it expires, runs out of views, is revoked or is deleted by its
reader. A secret can also be targeted at a user of the site, who is notified and must log in to open it.

## Requirements

- PHP 8.4+
- Laravel 12 or 13
- Filament 5.8+

## Installation

```bash
composer require arzcode/shared-secrets
php artisan shared-secrets:install
```

The installer:

1. publishes the migration (`create_shared_secrets_tables`),
2. asks whether to publish the config file (`config/shared-secrets.php`),
3. asks whether to run the migrations,
4. asks, for each `app/Providers/Filament/*PanelProvider.php`, whether to register `SharedSecretsPlugin` in it.

Re-running it is safe: it never publishes the migration or registers the plugin twice, and it leaves an existing
config file alone.

To install by hand instead:

```bash
php artisan vendor:publish --tag=shared-secrets-migrations
php artisan vendor:publish --tag=shared-secrets-config
php artisan migrate
```

```php
use Arzcode\SharedSecrets\SharedSecretsPlugin;

$panel->plugins([
    SharedSecretsPlugin::make(),
]);
```

## Plugin options

```php
SharedSecretsPlugin::make()
    ->navigationGroup(fn () => __('Tools'))          // string, enum or closure
    ->navigationSort(10)
    ->navigationIcon(Heroicon::OutlinedKey)
    ->authorize(fn (User $user) => $user->isAdmin()) // who may share secrets; default: every panel user
    ->viewAllSecrets(fn () => false)                 // list everyone's secrets instead of only your own
    ->recipients(true)                               // allow targeting a user of the site
    ->recipientQuery(fn ($query) => $query->where('active', true))
    ->revealPath('secret')                           // URL segment of the reader link, inside the panel path
    ->revealLogoHeight('3rem');                      // logo height on the reader page; null keeps the panel's
```

Lifetimes, the maximum number of views, passphrase throttling, the retention of closed secrets and the notification
channels are set in `config/shared-secrets.php`.

## How it works

- **The link** is `/{panel}/secret/{id}?signature=…`. The signature proves the link was issued by your application;
  expiry and views are enforced from the database, so a spent link shows a "no longer available" page.
- **Targeted secrets** only open for their recipient. Guests are sent to the login page of a panel the recipient can
  access and brought back afterwards; anyone else gets a 403. The recipient receives an email and, when a panel they
  can access shows database notifications, a notification in the panel.
- **The 1-click retrieval step** keeps chat previews and URL scanners from spending views. Without it, the page
  reveals the secret as soon as a browser loads it. Fetching the link never spends a view by itself, so a scanner
  that does not run JavaScript cannot use one up.
- **A passphrase** can be required. Wrong attempts are throttled per IP address and, past a limit from any address,
  pause the secret for `passphrase.lockout_minutes`. The secret is never wiped by wrong attempts.
- **The content is never shown again** to its sender. An optional note, also encrypted, identifies a secret in the
  list; the activity log records every view with its time, user, IP address and browser.
- **Pruning**: `php artisan shared-secrets:prune` wipes expired secrets and deletes the rows closed more than
  `prune_after_days` ago. It is scheduled hourly unless `schedule.enabled` is off.

## Uninstalling

```bash
php artisan shared-secrets:uninstall
```

After a confirmation, it removes `SharedSecretsPlugin` from your panel providers without asking again. It then asks,
one by one and defaulting to "no", whether to drop the tables, delete the published migration, delete the published
config file and delete any published translations. It ends by running `composer remove arzcode/shared-secrets`.

## Testing

```bash
composer test
composer analyse
composer format
```
