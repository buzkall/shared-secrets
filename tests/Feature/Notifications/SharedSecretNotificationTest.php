<?php

use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Notifications\SharedSecretNotification;
use Arzcode\SharedSecrets\Support\SecretUrl;
use Arzcode\SharedSecrets\Tests\Fixtures\User;

use function Pest\Laravel\travelTo;

it('emails the signed link, the sender and the limits of the secret', function(): void {
    travelTo('2026-01-01 10:00:00');
    $recipient = User::factory()->create();
    $secret = SharedSecret::factory()->to($recipient)->create(['max_views' => 3]);

    $mail = new SharedSecretNotification($secret->id, 'Ada Sender')->toMail($recipient);

    expect($mail)
        ->subject->toBe('Ada Sender shared a secret with you')
        ->actionUrl->toBe(SecretUrl::for($secret))
        ->and($mail->introLines)->toContain('It can be revealed 3 times and expires on 08/01/2026 10:00:00.');
});

it('escapes the sender name in the email', function(): void {
    $recipient = User::factory()->create();
    $secret = SharedSecret::factory()->to($recipient)->create();

    $html = (string)new SharedSecretNotification($secret->id, '<script>alert(1)</script>')
        ->toMail($recipient)
        ->render();

    expect($html)
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});

it('sends the email for a secret that is still available', function(): void {
    $recipient = User::factory()->create();
    $secret = SharedSecret::factory()->to($recipient)->create();

    $recipient->notify(new SharedSecretNotification($secret->id, 'Ada Sender'));

    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(1);
});

it('sends no email for a secret that was closed before the queue got to it', function(): void {
    $recipient = User::factory()->create();
    $secret = SharedSecret::factory()->to($recipient)->closed()->create();

    $recipient->notify(new SharedSecretNotification($secret->id, 'Ada Sender'));

    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(0);
});
