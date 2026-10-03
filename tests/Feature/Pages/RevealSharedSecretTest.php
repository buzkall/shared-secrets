<?php

use Arzcode\SharedSecrets\Enums\SharedSecretEventType;
use Arzcode\SharedSecrets\Enums\SharedSecretStatus;
use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Pages\RevealSharedSecret;
use Arzcode\SharedSecrets\Support\SecretUrl;
use Arzcode\SharedSecrets\Tests\Fixtures\User;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

const UNAVAILABLE = 'This secret is no longer available.';

it('returns 403 for a link without a valid signature', function(): void {
    $secret = SharedSecret::factory()->withoutRetrievalStep()->create();

    get("/admin/secret/{$secret->id}")->assertForbidden();
    get(SecretUrl::for($secret) . 'tampered')->assertForbidden();

    expect($secret->refresh()->views_count)->toBe(0);
});

it('answers a HEAD request without spending a view', function(): void {
    $secret = SharedSecret::factory()->withoutRetrievalStep()->create();

    call('HEAD', SecretUrl::for($secret))->assertNoContent();

    expect($secret->refresh()->views_count)->toBe(0);
});

it('marks the page as uncacheable, unframeable and unindexable', function(): void {
    $secret = SharedSecret::factory()->create();

    $response = get(SecretUrl::for($secret))
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    // Livewire adds its own directives to the same header.
    expect($response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('private');
});

it('does not spend a view or show the secret until it is revealed', function(): void {
    $secret = SharedSecret::factory()->create(['content' => 'hunter2']);

    get(SecretUrl::for($secret))
        ->assertSee('Reveal secret')
        ->assertDontSee('wire:init="reveal"', escape: false)
        ->assertDontSee('hunter2');

    expect($secret->refresh()->views_count)->toBe(0);
});

it('reveals the secret, spends a view and logs it', function(): void {
    $secret = SharedSecret::factory()->create(['content' => 'hunter2']);

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->call('reveal')
        ->assertSee('hunter2')
        ->assertDontSee('Reveal secret');

    expect($secret->refresh())
        ->views_count->toBe(1)
        ->status()->toBe(SharedSecretStatus::Active)
        ->and($secret->events()->sole())
        ->type->toBe(SharedSecretEventType::Viewed)
        ->ip_address->toBe('127.0.0.1')
        ->user_id->toBeNull();
});

it('tells the reader how many more times the link can be opened', function(int $maxViews, string $message): void {
    $secret = SharedSecret::factory()->create(['max_views' => $maxViews]);

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->call('reveal')
        ->assertSee($message);
})->with([
    'several left' => [5, 'The link can be opened 4 more times.'],
    'one left'     => [2, 'The link can be opened 1 more time.'],
    'none left'    => [1, 'This was its last view: the link will no longer work.'],
]);

it('keeps the revealed secret out of the Livewire snapshot', function(): void {
    $secret = SharedSecret::factory()->create(['content' => 'hunter2']);

    $page = livewire(RevealSharedSecret::class, ['secret' => $secret->id])->call('reveal');

    // The id is in there, which proves this is the real component state.
    expect(json_encode($page->snapshot))
        ->toContain($secret->id)
        ->not->toContain('hunter2');
});

it('escapes the revealed secret', function(): void {
    $secret = SharedSecret::factory()->create(['content' => '<script>alert(1)</script>']);

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->call('reveal')
        ->assertSee('<script>alert(1)</script>')
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

it('leaves the reveal to the browser when the link is fetched without the retrieval step', function(): void {
    $secret = SharedSecret::factory()->withoutRetrievalStep()->create(['content' => 'hunter2']);

    get(SecretUrl::for($secret))
        ->assertSee('wire:init="reveal"', escape: false)
        ->assertDontSee('hunter2');

    expect($secret->refresh()->views_count)->toBe(0);
});

it('does not spend a second view when reveal is called again', function(): void {
    $secret = SharedSecret::factory()->create();

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->call('reveal')
        ->call('reveal');

    expect($secret->refresh()->views_count)->toBe(1);
});

it('wipes the secret after its last view and stops serving it', function(): void {
    $secret = SharedSecret::factory()->singleView()->create(['content' => 'hunter2']);

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->call('reveal')
        ->assertSee('hunter2');

    expect(DB::table('shared_secrets')->sole()->content)->toBeNull()
        ->and($secret->refresh()->status())->toBe(SharedSecretStatus::Exhausted);

    get(SecretUrl::for($secret))
        ->assertSee(UNAVAILABLE)
        ->assertDontSee('Reveal secret');
});

it('shows a closed secret as unavailable', function(SharedSecretStatus $reason): void {
    $secret = SharedSecret::factory()->closed($reason)->create();

    get(SecretUrl::for($secret))
        ->assertSee(UNAVAILABLE)
        ->assertDontSee('Reveal secret');
})->with([
    'revoked'   => SharedSecretStatus::Revoked,
    'exhausted' => SharedSecretStatus::Exhausted,
    'deleted'   => SharedSecretStatus::Deleted,
]);

it('shows an unknown secret as unavailable', function(): void {
    $url = URL::signedRoute('filament.admin.shared-secrets.reveal', ['secret' => '01JZZZZZZZZZZZZZZZZZZZZZZZ']);

    get($url)->assertSee(UNAVAILABLE);
});

it('wipes a secret that expired while the page was open instead of revealing it', function(): void {
    $secret = SharedSecret::factory()->create(['content' => 'hunter2']);
    $page = livewire(RevealSharedSecret::class, ['secret' => $secret->id]);
    $this->travel(8)->days();

    $page->call('reveal')
        ->assertDontSee('hunter2')
        ->assertSee(UNAVAILABLE);

    expect(DB::table('shared_secrets')->sole()->content)->toBeNull()
        ->and($secret->refresh())
        ->views_count->toBe(0)
        ->closed_reason->toBe(SharedSecretStatus::Expired);
});

it('asks for the passphrase before revealing a protected secret', function(): void {
    $secret = SharedSecret::factory()->withPassphrase('open sesame')->create(['content' => 'hunter2']);

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->call('reveal')
        ->assertHasFormErrors(['passphrase' => 'required'])
        ->assertDontSee('hunter2');

    expect($secret->refresh()->views_count)->toBe(0);
});

it('does not reveal a protected secret opened without the retrieval step', function(): void {
    $secret = SharedSecret::factory()->withoutRetrievalStep()->withPassphrase('open sesame')->create(['content' => 'hunter2']);

    get(SecretUrl::for($secret))
        ->assertSee('Passphrase')
        ->assertDontSee('wire:init="reveal"', escape: false)
        ->assertDontSee('hunter2');

    expect($secret->refresh()->views_count)->toBe(0);
});

it('rejects a wrong passphrase, logs the attempt and spends no view', function(): void {
    $secret = SharedSecret::factory()->withPassphrase('open sesame')->create(['content' => 'hunter2']);

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->fillForm(['passphrase' => 'wrong'])
        ->call('reveal')
        ->assertHasErrors(['data.passphrase' => 'That passphrase is not correct.'])
        ->assertSet('data.passphrase', null)
        ->assertDontSee('hunter2');

    expect($secret->refresh()->views_count)->toBe(0)
        ->and($secret->events()->sole()->type)->toBe(SharedSecretEventType::PassphraseFailed);
});

it('reveals a protected secret with the right passphrase', function(): void {
    $secret = SharedSecret::factory()->withPassphrase('open sesame')->create(['content' => 'hunter2']);

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->fillForm(['passphrase' => 'open sesame'])
        ->call('reveal')
        ->assertHasNoErrors()
        ->assertSee('hunter2');

    expect($secret->refresh()->views_count)->toBe(1);
});

it('throttles passphrase attempts, even a correct one', function(): void {
    config()->set('shared-secrets.passphrase.attempts_per_minute', 2);
    $secret = SharedSecret::factory()->withPassphrase('open sesame')->create(['content' => 'hunter2']);
    $page = livewire(RevealSharedSecret::class, ['secret' => $secret->id]);
    $page->fillForm(['passphrase' => 'wrong'])->call('reveal');
    $page->fillForm(['passphrase' => 'wrong'])->call('reveal');

    $page->fillForm(['passphrase' => 'open sesame'])
        ->call('reveal')
        ->assertHasErrors(['data.passphrase' => 'Too many attempts. Wait a minute and try again.'])
        ->assertDontSee('hunter2');

    expect($secret->refresh()->views_count)->toBe(0);
});

it('pauses the secret after too many wrong passphrases instead of wiping it', function(): void {
    config()->set('shared-secrets.passphrase.max_failures', 2);
    $secret = SharedSecret::factory()->withPassphrase('open sesame')->create(['content' => 'hunter2']);
    $page = livewire(RevealSharedSecret::class, ['secret' => $secret->id]);
    $page->fillForm(['passphrase' => 'wrong'])->call('reveal');
    $page->fillForm(['passphrase' => 'wrong'])->call('reveal');

    $page->fillForm(['passphrase' => 'open sesame'])
        ->call('reveal')
        ->assertHasErrors(['data.passphrase' => 'Too many wrong passphrases. This secret is paused for a while; try again later.'])
        ->assertDontSee('hunter2');

    expect($secret->refresh())
        ->content->toBe('hunter2')
        ->views_count->toBe(0)
        ->status()->toBe(SharedSecretStatus::Active)
        ->and($secret->events()->count())->toBe(2);
});

it('accepts the passphrase again once the wrong attempts are older than the lockout', function(): void {
    config()->set('shared-secrets.passphrase.max_failures', 2);
    config()->set('shared-secrets.passphrase.lockout_minutes', 30);
    $secret = SharedSecret::factory()->withPassphrase('open sesame')->create(['content' => 'hunter2']);
    $page = livewire(RevealSharedSecret::class, ['secret' => $secret->id]);
    $page->fillForm(['passphrase' => 'wrong'])->call('reveal');
    $page->fillForm(['passphrase' => 'wrong'])->call('reveal');
    $this->travel(31)->minutes();

    $page->fillForm(['passphrase' => 'open sesame'])
        ->call('reveal')
        ->assertHasNoErrors()
        ->assertSee('hunter2');

    expect($secret->refresh()->views_count)->toBe(1);
});

it('sends a guest to the login of a panel the recipient can access, remembering the link', function(string $state, string $loginPath): void {
    $recipient = User::factory()->{$state}()->create();
    $secret = SharedSecret::factory()->to($recipient)->withoutRetrievalStep()->create();
    $url = SecretUrl::for($secret);

    get($url)
        ->assertRedirect($loginPath)
        ->assertSessionHas('url.intended', $url);

    expect($secret->refresh()->views_count)->toBe(0);
})->with([
    'admin recipient'  => ['admin', '/admin/login'],
    'client recipient' => ['client', '/client/login'],
]);

it('returns 403 to a logged-in user who is not the recipient', function(): void {
    $secret = SharedSecret::factory()->to(User::factory()->create())->withoutRetrievalStep()->create();
    actingAs(User::factory()->create());

    get(SecretUrl::for($secret))->assertForbidden();

    expect($secret->refresh()->views_count)->toBe(0);
});

it('reveals a targeted secret to its recipient and logs who viewed it', function(): void {
    $recipient = User::factory()->client()->create();
    $secret = SharedSecret::factory()->to($recipient)->create(['content' => 'hunter2']);
    actingAs($recipient);

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->call('reveal')
        ->assertSee('hunter2');

    expect($secret->events()->sole()->user_id)->toBe($recipient->id);
});

it('returns 403 when the session changes to another user before revealing', function(): void {
    $recipient = User::factory()->create();
    $secret = SharedSecret::factory()->to($recipient)->create();
    actingAs($recipient);
    $page = livewire(RevealSharedSecret::class, ['secret' => $secret->id]);
    actingAs(User::factory()->create());

    $page->call('reveal')->assertForbidden();

    expect($secret->refresh()->views_count)->toBe(0);
});

it('lets the reader delete the secret after seeing it', function(): void {
    $secret = SharedSecret::factory()->create(['content' => 'hunter2']);

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->call('reveal')
        ->callAction('deleteNow')
        ->assertSee('The secret has been deleted')
        ->assertDontSee('hunter2');

    expect(DB::table('shared_secrets')->sole()->content)->toBeNull()
        ->and($secret->refresh()->status())->toBe(SharedSecretStatus::Deleted)
        ->and($secret->events()->where('type', SharedSecretEventType::DeletedByRecipient)->exists())->toBeTrue();
});

it('does not let anyone delete a secret they have not revealed', function(): void {
    $secret = SharedSecret::factory()->create();

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->assertActionHidden('deleteNow');

    expect($secret->refresh()->isAvailable())->toBeTrue();
});

it('offers no deletion when the sender did not allow it', function(): void {
    $secret = SharedSecret::factory()->withoutDeletion()->create();

    livewire(RevealSharedSecret::class, ['secret' => $secret->id])
        ->call('reveal')
        ->assertActionHidden('deleteNow');
});

it('enlarges the brand logo on the reader page', function(): void {
    $secret = SharedSecret::factory()->create();

    get(SecretUrl::for($secret))->assertSee('.fi-simple-header .fi-logo { height: 3rem !important; }', escape: false);
});

it('only warns that deleting affects everyone when anyone with the link can open the secret', function(): void {
    $recipient = User::factory()->create();
    $open = SharedSecret::factory()->create();
    $targeted = SharedSecret::factory()->to($recipient)->create();
    actingAs($recipient);

    livewire(RevealSharedSecret::class, ['secret' => $open->id])
        ->call('reveal')
        ->assertActionExists('deleteNow', fn(Action $action): bool => $action->getModalDescription() === 'The secret will be wiped for everyone and the link will stop working.');

    livewire(RevealSharedSecret::class, ['secret' => $targeted->id])
        ->call('reveal')
        ->assertActionExists('deleteNow', fn(Action $action): bool => $action->getModalDescription() === 'The secret will be wiped and the link will stop working.');
});
