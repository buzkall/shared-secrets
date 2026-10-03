<?php

namespace Arzcode\SharedSecrets\Commands;

use Arzcode\SharedSecrets\Actions\CloseSharedSecret;
use Arzcode\SharedSecrets\Enums\SharedSecretStatus;
use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Support\Cast;
use Illuminate\Console\Command;

class PruneSharedSecretsCommand extends Command
{
    protected $signature = 'shared-secrets:prune';
    protected $description = 'Wipe the content of expired secrets and delete the rows closed long ago';

    public function handle(CloseSharedSecret $close): int
    {
        $wiped = 0;

        SharedSecret::query()->overdue()->chunkById(200, function($secrets) use ($close, &$wiped): void {
            foreach ($secrets as $secret) {
                $close->handle($secret, SharedSecretStatus::Expired);
                $wiped++;
            }
        });

        $days = config('shared-secrets.prune_after_days');
        $deleted = is_int($days)
            ? Cast::int(SharedSecret::query()->closedBefore(now()->subDays($days))->delete())
            : 0;

        $this->components->info(__('shared-secrets::shared-secrets.commands.prune.done', [
            'wiped'   => $wiped,
            'deleted' => $deleted
        ]));

        return self::SUCCESS;
    }
}
