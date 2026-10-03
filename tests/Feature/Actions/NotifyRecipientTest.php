<?php

use Arzcode\SharedSecrets\Actions\NotifyRecipient;
use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Notifications\SharedSecretNotification;
use Arzcode\SharedSecrets\Support\SecretUrl;
use Arzcode\SharedSecrets\Tests\Fixtures\User;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

it('emails the recipient and notifies them in the panel when one they can access shows database notifications', function(): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->admin()->create();
    $secret = SharedSecret::factory()->from($sender)->to($recipient)->create();
    Notification::fake();

    app(NotifyRecipient::class)->handle($secret, $recipient, $sender);

    Notification::assertSentTo($recipient, SharedSecretNotification::class);
    Notification::assertSentTo($recipient, DatabaseNotification::class);
});

it('only emails a recipient whose panels show no database notifications', function(): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->client()->create();
    $secret = SharedSecret::factory()->from($sender)->to($recipient)->create();
    Notification::fake();

    app(NotifyRecipient::class)->handle($secret, $recipient, $sender);

    Notification::assertSentTo($recipient, SharedSecretNotification::class);
    Notification::assertNotSentTo($recipient, DatabaseNotification::class);
});

it('sends no email when the mail channel is turned off', function(): void {
    config()->set('shared-secrets.notifications.mail', false);
    $sender = User::factory()->create();
    $recipient = User::factory()->admin()->create();
    $secret = SharedSecret::factory()->from($sender)->to($recipient)->create();
    Notification::fake();

    app(NotifyRecipient::class)->handle($secret, $recipient, $sender);

    Notification::assertNotSentTo($recipient, SharedSecretNotification::class);
    Notification::assertSentTo($recipient, DatabaseNotification::class);
});

it('sends no database notification when that channel is turned off', function(): void {
    config()->set('shared-secrets.notifications.database', false);
    $sender = User::factory()->create();
    $recipient = User::factory()->admin()->create();
    $secret = SharedSecret::factory()->from($sender)->to($recipient)->create();
    Notification::fake();

    app(NotifyRecipient::class)->handle($secret, $recipient, $sender);

    Notification::assertSentTo($recipient, SharedSecretNotification::class);
    Notification::assertNotSentTo($recipient, DatabaseNotification::class);
});

it('stores a Filament database notification that links to the secret', function(): void {
    config()->set('shared-secrets.notifications.mail', false);
    $sender = User::factory()->create(['name' => 'Ada Sender']);
    $recipient = User::factory()->admin()->create();
    $secret = SharedSecret::factory()->from($sender)->to($recipient)->create();

    app(NotifyRecipient::class)->handle($secret, $recipient, $sender);

    $data = $recipient->notifications()->sole()->data;

    expect($data)
        ->format->toBe('filament')
        ->title->toBe('Ada Sender shared a secret with you')
        ->and($data['actions'][0]['url'])->toBe(SecretUrl::for($secret));
});
