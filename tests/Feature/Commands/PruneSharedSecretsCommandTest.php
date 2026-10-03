<?php

use Arzcode\SharedSecrets\Enums\SharedSecretStatus;
use Arzcode\SharedSecrets\Models\SharedSecret;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\travelTo;

it('wipes expired secrets and keeps their rows', function(): void {
    $expired = SharedSecret::factory()->overdue()->withPassphrase('open sesame')->create();
    $active = SharedSecret::factory()->create(['content' => 'hunter2']);

    artisan('shared-secrets:prune')->assertSuccessful();

    $row = DB::table('shared_secrets')->where('id', $expired->id)->sole();

    expect($row->content)->toBeNull()
        ->and($row->passphrase)->toBeNull()
        ->and($expired->refresh()->closed_reason)->toBe(SharedSecretStatus::Expired)
        ->and($active->refresh())
        ->content->toBe('hunter2')
        ->closed_at->toBeNull();
});

it('deletes the rows closed longer ago than the retention period', function(): void {
    config()->set('shared-secrets.prune_after_days', 30);
    travelTo('2026-01-01 10:00:00');
    $old = SharedSecret::factory()->closed()->create();
    travelTo('2026-01-20 10:00:00');
    $recent = SharedSecret::factory()->closed()->create();
    travelTo('2026-02-05 10:00:00');

    artisan('shared-secrets:prune')->assertSuccessful();

    assertModelMissing($old);
    assertModelExists($recent);
});

it('keeps closed rows forever when no retention period is set', function(): void {
    config()->set('shared-secrets.prune_after_days');
    travelTo('2026-01-01 10:00:00');
    $old = SharedSecret::factory()->closed()->create();
    travelTo('2030-01-01 10:00:00');

    artisan('shared-secrets:prune')->assertSuccessful();

    assertModelExists($old);
});

it('is scheduled hourly', function(): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn(Event $event): bool => str_contains((string)$event->command, 'shared-secrets:prune'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 * * * *');
});

it('is not scheduled when the schedule is turned off', function(): void {
    config()->set('shared-secrets.schedule.enabled', false);
    $this->app->forgetInstance(Schedule::class);

    $events = collect(app(Schedule::class)->events())
        ->filter(fn(Event $event): bool => str_contains((string)$event->command, 'shared-secrets:prune'));

    expect($events)->toBeEmpty();
});
