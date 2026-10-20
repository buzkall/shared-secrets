<?php

use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Pages\ManageSharedSecrets;
use Arzcode\SharedSecrets\Pages\RevealSharedSecret;
use Arzcode\SharedSecrets\Support\SecretUrl;
use Arzcode\SharedSecrets\Tests\Fixtures\Language;
use Arzcode\SharedSecrets\Tests\Fixtures\User;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

function rollBackLocaleMigration(): void
{
    (include __DIR__ . '/../../../database/migrations/add_locale_to_shared_secrets_table.php.stub')->down();
}

it('adds the locale column once and drops it on rollback', function(): void {
    $migration = include __DIR__ . '/../../../database/migrations/add_locale_to_shared_secrets_table.php.stub';

    expect(Schema::hasColumn('shared_secrets', 'locale'))->toBeTrue();

    $migration->up();
    expect(Schema::hasColumn('shared_secrets', 'locale'))->toBeTrue();

    $migration->down();
    expect(Schema::hasColumn('shared_secrets', 'locale'))->toBeFalse();
});

it('pushes, notifies and reveals secrets when the upgrade migration has not been run and no languages are configured', function(): void {
    rollBackLocaleMigration();
    $sender = User::factory()->create();
    $recipient = User::factory()->admin()->create();
    actingAs($sender);

    livewire(ManageSharedSecrets::class)
        ->fillForm(['content' => 'hunter2', 'recipient_id' => $recipient->getKey()])
        ->call('create')
        ->assertHasNoFormErrors();

    $secret = SharedSecret::query()->sole();
    actingAs($recipient);

    get(SecretUrl::for($secret))->assertOk();
    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->call('reveal')
        ->assertSee('hunter2');
});

it('pushes the secret and tells the sender to run the migration when languages are configured before it', function(): void {
    rollBackLocaleMigration();
    config()->set('shared-secrets.languages.source', Language::class);
    $sender = User::factory()->create();
    $recipient = User::factory()->admin()->create();
    actingAs($sender);

    livewire(ManageSharedSecrets::class)
        ->assertFormFieldDisabled('locale')
        ->assertSee('php artisan vendor:publish --tag=shared-secrets-migrations')
        ->fillForm(['content' => 'hunter2', 'recipient_id' => $recipient->getKey()])
        ->call('create')
        ->assertHasNoFormErrors();

    $secret = SharedSecret::query()->sole();
    actingAs($recipient);

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->call('reveal')
        ->assertSee('hunter2');
});
