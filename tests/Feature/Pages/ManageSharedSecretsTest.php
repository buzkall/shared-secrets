<?php

use Arzcode\SharedSecrets\Enums\SharedSecretEventType;
use Arzcode\SharedSecrets\Enums\SharedSecretStatus;
use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Notifications\SharedSecretNotification;
use Arzcode\SharedSecrets\Pages\ManageSharedSecrets;
use Arzcode\SharedSecrets\Support\SecretUrl;
use Arzcode\SharedSecrets\Tests\Fixtures\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;
use function Pest\Livewire\livewire;

it('redirects guests to the panel login', function(): void {
    get('/admin/shared-secrets')->assertRedirect('/admin/login');
});

it('forbids users the plugin does not authorize', function(): void {
    config()->set('testing.forbidden', true);
    actingAs(User::factory()->create());

    get('/admin/shared-secrets')->assertForbidden();
});

it('forbids pushing a secret when the plugin does not authorize the user', function(): void {
    actingAs(User::factory()->create());
    $page = livewire(ManageSharedSecrets::class)->fillForm(['content' => 'hunter2']);
    config()->set('testing.forbidden', true);

    $page->call('create')->assertForbidden();

    assertDatabaseCount('shared_secrets', 0);
});

it('places the page in the navigation group set on the plugin', function(): void {
    expect(ManageSharedSecrets::getNavigationGroup())->toBe('Tools');
});

it('stores the secret with the defaults, the creator and the panel', function(): void {
    travelTo('2026-01-01 10:00:00');
    $user = User::factory()->create();
    actingAs($user);

    livewire(ManageSharedSecrets::class)
        ->fillForm(['content' => 'hunter2'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Secret created');

    $secret = SharedSecret::query()->sole();

    expect($secret)
        ->content->toBe('hunter2')
        ->creator_id->toBe($user->id)
        ->recipient_id->toBeNull()
        ->panel->toBe('admin')
        ->max_views->toBe(3)
        ->views_count->toBe(0)
        ->requires_retrieval_step->toBeTrue()
        ->allows_deletion->toBeTrue()
        ->and($secret->expires_at->toDateTimeString())->toBe('2026-01-08 10:00:00');
});

it('encrypts the content and the note and hashes the passphrase at rest', function(): void {
    actingAs(User::factory()->create());

    livewire(ManageSharedSecrets::class)
        ->fillForm(['content' => 'hunter2', 'note' => 'VPN access', 'passphrase' => 'open sesame'])
        ->call('create')
        ->assertHasNoFormErrors();

    $row = DB::table('shared_secrets')->sole();

    expect($row->content)->not->toContain('hunter2')
        ->and($row->note)->not->toContain('VPN access')
        ->and($row->passphrase)->not->toBe('open sesame')
        ->and(Hash::check('open sesame', $row->passphrase))->toBeTrue()
        ->and(SharedSecret::query()->sole()->note)->toBe('VPN access');
});

it('stores the options chosen by the sender', function(): void {
    travelTo('2026-01-01 10:00:00');
    actingAs(User::factory()->create());

    livewire(ManageSharedSecrets::class)
        ->fillForm([
            'content'                 => 'hunter2',
            'expires_in'              => 60,
            'max_views'               => 2,
            'requires_retrieval_step' => false,
            'allows_deletion'         => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $secret = SharedSecret::query()->sole();

    expect($secret)
        ->max_views->toBe(2)
        ->requires_retrieval_step->toBeFalse()
        ->allows_deletion->toBeFalse()
        ->and($secret->expires_at->toDateTimeString())->toBe('2026-01-01 11:00:00');
});

it('shows the link and drops the content and the passphrase from the page state after pushing', function(): void {
    actingAs(User::factory()->create());

    $page = livewire(ManageSharedSecrets::class)
        ->fillForm(['content' => 'hunter2', 'passphrase' => 'open sesame'])
        ->call('create');

    $secret = SharedSecret::query()->sole();

    $page
        ->assertSet('createdSecretId', $secret->id)
        ->assertSet('data.content', null)
        ->assertSet('data.passphrase', null)
        ->assertSee(SecretUrl::for($secret))
        ->assertDontSee('hunter2');

    // The id is in there, which proves this is the real component state.
    expect(json_encode($page->snapshot))
        ->toContain($secret->id)
        ->not->toContain('hunter2')
        ->not->toContain('open sesame');
});

it('requires the content', function(): void {
    actingAs(User::factory()->create());

    livewire(ManageSharedSecrets::class)
        ->fillForm(['content' => ''])
        ->call('create')
        ->assertHasFormErrors(['content' => 'required']);

    assertDatabaseCount('shared_secrets', 0);
});

it('rejects an expiry that is not one of the configured options', function(): void {
    actingAs(User::factory()->create());

    livewire(ManageSharedSecrets::class)
        ->fillForm(['content' => 'hunter2', 'expires_in' => 999999])
        ->call('create')
        ->assertHasFormErrors(['expires_in']);

    assertDatabaseCount('shared_secrets', 0);
});

it('rejects more views than the configured maximum', function(): void {
    actingAs(User::factory()->create());

    livewire(ManageSharedSecrets::class)
        ->fillForm(['content' => 'hunter2', 'max_views' => 11])
        ->call('create')
        ->assertHasFormErrors(['max_views']);

    assertDatabaseCount('shared_secrets', 0);
});

it('accepts a typed number of views when the maximum is too large for a row of buttons', function(): void {
    config()->set('shared-secrets.max_views.max', 50);
    actingAs(User::factory()->create());

    livewire(ManageSharedSecrets::class)
        ->fillForm(['content' => 'hunter2', 'max_views' => 51])
        ->call('create')
        ->assertHasFormErrors(['max_views'])
        ->fillForm(['content' => 'hunter2', 'max_views' => 40])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SharedSecret::query()->sole()->max_views)->toBe(40);
});

it('targets the secret at the chosen recipient and emails them', function(): void {
    $recipient = User::factory()->create();
    actingAs(User::factory()->create(['name' => 'Ada Sender']));
    Notification::fake();

    livewire(ManageSharedSecrets::class)
        ->fillForm(['content' => 'hunter2', 'recipient_id' => $recipient->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $secret = SharedSecret::query()->sole();

    expect($secret->recipient_id)->toBe($recipient->id);

    Notification::assertSentTo(
        $recipient,
        fn(SharedSecretNotification $notification): bool => $notification->secretId === $secret->id
            && $notification->senderName === 'Ada Sender'
    );
});

it('offers the allowed users as recipients before anything is typed', function(): void {
    $sender = User::factory()->create(['name' => 'Ada Sender']);
    $allowed = User::factory()->create(['name' => 'Grace Allowed']);
    User::factory()->create(['name' => 'Bob Blocked', 'email' => 'bob@blocked.test']);
    actingAs($sender);

    livewire(ManageSharedSecrets::class)->assertFormFieldExists(
        'recipient_id',
        fn(Select $field): bool => $field->getOptions() === [$sender->id => 'Ada Sender', $allowed->id => 'Grace Allowed']
    );
});

it('rejects a recipient outside the query allowed by the plugin', function(): void {
    $blocked = User::factory()->create(['email' => 'someone@blocked.test']);
    actingAs(User::factory()->create());
    Notification::fake();

    livewire(ManageSharedSecrets::class)
        ->fillForm(['content' => 'hunter2', 'recipient_id' => $blocked->id])
        ->call('create')
        ->assertHasFormErrors(['recipient_id']);

    assertDatabaseCount('shared_secrets', 0);
    Notification::assertNothingSent();
});

it('offers no recipient field when the plugin turns recipients off', function(): void {
    config()->set('testing.recipients', false);
    actingAs(User::factory()->create());

    livewire(ManageSharedSecrets::class)->assertFormFieldHidden('recipient_id');
});

it('lists only the secrets sent by the current user', function(): void {
    $user = User::factory()->create();
    $mine = SharedSecret::factory()->from($user)->create();
    $theirs = SharedSecret::factory()->from(User::factory()->create())->create();
    actingAs($user);

    livewire(ManageSharedSecrets::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('loads the users of the listed secrets in a single query', function(): void {
    $user = User::factory()->create();
    SharedSecret::factory()->from($user)->to($user)->count(3)->create();
    actingAs($user);
    DB::enableQueryLog();

    livewire(ManageSharedSecrets::class)->assertSuccessful();

    $userQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn(string $sql): bool => str_contains($sql, 'from "users"') && str_contains($sql, ' in ('));

    expect($userQueries)->toHaveCount(1);
});

it('lists the secrets of every sender when the plugin allows it, without offering their links', function(): void {
    config()->set('testing.view_all', true);
    $user = User::factory()->create();
    $mine = SharedSecret::factory()->from($user)->create();
    $theirs = SharedSecret::factory()->from(User::factory()->create())->create();
    actingAs($user);

    livewire(ManageSharedSecrets::class)
        ->assertCanSeeTableRecords([$mine, $theirs])
        ->assertActionVisible(TestAction::make('copyLink')->table($mine))
        ->assertActionHidden(TestAction::make('copyLink')->table($theirs));
});

it('does not let a user who sees every secret revoke or delete one they did not send', function(): void {
    config()->set('testing.view_all', true);
    $theirs = SharedSecret::factory()->from(User::factory()->create())->create();
    actingAs(User::factory()->create());

    livewire(ManageSharedSecrets::class)
        ->assertActionHidden(TestAction::make('revoke')->table($theirs))
        ->assertActionHidden(TestAction::make(DeleteAction::class)->table($theirs))
        ->call('mountAction', 'revoke', [], ['table' => true, 'recordKey' => $theirs->id])
        ->call('callMountedAction')
        ->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => $theirs->id])
        ->call('callMountedAction');

    expect($theirs->refresh()->isAvailable())->toBeTrue();
});

it('never renders the content of a sent secret', function(): void {
    $user = User::factory()->create();
    SharedSecret::factory()->from($user)->create(['content' => 'hunter2', 'note' => 'VPN access']);
    actingAs($user);

    $page = livewire(ManageSharedSecrets::class)
        ->assertSee('VPN access')
        ->assertDontSee('hunter2');

    expect(json_encode($page->snapshot))->not->toContain('hunter2');
});

it('escapes the note in the table', function(): void {
    $user = User::factory()->create();
    SharedSecret::factory()->from($user)->create(['note' => '<script>alert(1)</script>']);
    actingAs($user);

    livewire(ManageSharedSecrets::class)
        ->assertSee('<script>alert(1)</script>')
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

it('wipes a revoked secret and logs who revoked it', function(): void {
    $user = User::factory()->create();
    $secret = SharedSecret::factory()->from($user)->withPassphrase('open sesame')->create();
    actingAs($user);

    livewire(ManageSharedSecrets::class)
        ->callAction(TestAction::make('revoke')->table($secret))
        ->assertNotified('Secret revoked');

    $row = DB::table('shared_secrets')->sole();

    expect($row->content)->toBeNull()
        ->and($row->passphrase)->toBeNull()
        ->and($secret->refresh()->status())->toBe(SharedSecretStatus::Revoked)
        ->and($secret->events()->sole())
        ->type->toBe(SharedSecretEventType::Revoked)
        ->user_id->toBe($user->id);
});

it('offers no revoke action on a secret that is already closed', function(): void {
    $user = User::factory()->create();
    $secret = SharedSecret::factory()->from($user)->closed()->create();
    actingAs($user);

    livewire(ManageSharedSecrets::class)
        ->assertActionHidden(TestAction::make('revoke')->table($secret))
        ->assertActionHidden(TestAction::make('copyLink')->table($secret));
});

it('deletes a secret together with its activity', function(): void {
    $user = User::factory()->create();
    $secret = SharedSecret::factory()->from($user)->create();
    $event = $secret->recordEvent(SharedSecretEventType::Viewed);
    actingAs($user);

    livewire(ManageSharedSecrets::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($secret));

    assertModelMissing($secret);
    assertModelMissing($event);
});

it('opens the activity log of a secret', function(): void {
    $user = User::factory()->create();
    $secret = SharedSecret::factory()->from($user)->create();
    $secret->recordEvent(SharedSecretEventType::Viewed);
    actingAs($user);

    livewire(ManageSharedSecrets::class)
        ->mountAction(TestAction::make('events')->table($secret))
        ->assertActionMounted(TestAction::make('events')->table($secret));
});
