<?php

use Arzcode\SharedSecrets\Actions\ConsumeSharedSecret;
use Arzcode\SharedSecrets\Data\Visitor;
use Arzcode\SharedSecrets\Enums\RevealOutcome;
use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;

it('never reveals a secret more times than its maximum views', function(): void {
    $secret = SharedSecret::factory()->create(['max_views' => 2]);
    $consume = app(ConsumeSharedSecret::class);
    $consume->handle($secret->id, null, new Visitor);
    $consume->handle($secret->id, null, new Visitor);

    $result = $consume->handle($secret->id, null, new Visitor);

    expect($result)
        ->outcome->toBe(RevealOutcome::Unavailable)
        ->content->toBeNull()
        ->and($secret->refresh()->views_count)->toBe(2);
});

it('does not reveal a targeted secret to anyone but its recipient', function(): void {
    $secret = SharedSecret::factory()->to(User::factory()->create())->create();
    $visitor = new Visitor(user: User::factory()->create());

    $result = app(ConsumeSharedSecret::class)->handle($secret->id, null, $visitor);

    expect($result)
        ->outcome->toBe(RevealOutcome::Unavailable)
        ->content->toBeNull()
        ->and($secret->refresh()->views_count)->toBe(0);
});

it('treats a payload it can no longer decrypt as unavailable', function(): void {
    $secret = SharedSecret::factory()->create();
    DB::table('shared_secrets')->update(['content' => 'encrypted-with-a-rotated-out-key']);

    $result = app(ConsumeSharedSecret::class)->handle($secret->id, null, new Visitor);

    expect($result->outcome)->toBe(RevealOutcome::Unavailable)
        ->and($secret->refresh()->views_count)->toBe(0);
});
