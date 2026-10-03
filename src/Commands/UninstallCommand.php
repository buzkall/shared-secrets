<?php

namespace Arzcode\SharedSecrets\Commands;

use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Models\SharedSecretEvent;
use Arzcode\SharedSecrets\SharedSecretsServiceProvider;
use Arzcode\SharedSecrets\Support\PanelProviders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

class UninstallCommand extends Command
{
    public $signature = 'shared-secrets:uninstall';
    public $description = 'Reverse the Shared Secrets install: unregister the plugin and optionally drop the tables and delete the published migration, config and translations.';

    public function handle(): int
    {
        intro('Uninstalling Shared Secrets');

        warning('This will remove Shared Secrets from your application.');

        if (! confirm(label: 'Do you want to continue?', default: false)) {
            note('Aborted.');

            return self::SUCCESS;
        }

        $steps = [
            $this->unpatchPanelProviders(...),
            $this->dropTables(...),
            $this->deletePublishedMigrations(...),
            $this->deleteConfigFile(...),
            $this->deletePublishedTranslations(...),
            $this->runFinalSteps(...)
        ];

        foreach ($steps as $step) {
            $this->newLine();
            $step();
        }

        return self::SUCCESS;
    }

    protected function unpatchPanelProviders(): void
    {
        $files = PanelProviders::files();

        if ($files === []) {
            warning('No app/Providers/Filament/*PanelProvider.php found — nothing to clean.');

            return;
        }

        foreach ($files as $file) {
            $contents = (string)file_get_contents($file);
            $relative = $this->relativePath($file);

            if (! PanelProviders::hasPlugin($contents)) {
                note(sprintf('SharedSecretsPlugin not present in %s — skipping.', $relative));

                continue;
            }

            $patched = PanelProviders::removePlugin($contents);

            if (! PanelProviders::parses($patched)) {
                warning(sprintf('Could not unpatch %s safely — remove SharedSecretsPlugin from it manually.', $relative));

                continue;
            }

            if (PanelProviders::hasPlugin($patched)) {
                warning(sprintf('SharedSecretsPlugin still referenced in %s — remove it manually.', $relative));
            }

            file_put_contents($file, $patched);
            info(sprintf('Removed SharedSecretsPlugin from %s.', $relative));
        }
    }

    protected function dropTables(): void
    {
        // the events reference the secrets, so they go first
        $tables = [new SharedSecretEvent()->getTable(), new SharedSecret()->getTable()];

        if (array_filter($tables, Schema::hasTable(...)) === []) {
            note('No Shared Secrets tables found — skipping.');

            return;
        }

        if (! confirm(
            label: 'Would you like to drop the Shared Secrets database tables?',
            default: false,
            hint: 'This permanently deletes every secret and its activity log.',
        )) {
            note('Skipped — tables left in place.');

            return;
        }

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
            note("Dropped {$table}");
        }

        info('Shared Secrets tables dropped.');
        warning('Their rows remain in the `migrations` table; remove them manually if you also delete the migration files.');
    }

    protected function deletePublishedMigrations(): void
    {
        $files = [];

        foreach (SharedSecretsServiceProvider::migrationNames() as $name) {
            $files = [...$files, ...(glob(database_path("migrations/*_{$name}.php")) ?: [])];
        }

        if ($files === []) {
            note('No published Shared Secrets migrations found — skipping.');

            return;
        }

        if (! confirm(label: sprintf('Delete %d published Shared Secrets migration file(s)?', count($files)), default: false)) {
            note('Skipped — published migrations left in place.');

            return;
        }

        foreach ($files as $file) {
            File::delete($file);
            note('Deleted ' . $this->relativePath($file));
        }

        info('Published migrations deleted.');
    }

    protected function deleteConfigFile(): void
    {
        $file = config_path('shared-secrets.php');

        if (! file_exists($file)) {
            note('No published config file found — skipping.');

            return;
        }

        if (! confirm(label: sprintf('Delete the published config file %s?', $this->relativePath($file)), default: false)) {
            note('Skipped — config file left in place.');

            return;
        }

        File::delete($file);
        info('Deleted ' . $this->relativePath($file));
    }

    protected function deletePublishedTranslations(): void
    {
        $dir = lang_path('vendor/shared-secrets');

        if (! is_dir($dir)) {
            note('No published translations found — skipping.');

            return;
        }

        if (! confirm(label: sprintf('Delete the published translations in %s?', $this->relativePath($dir)), default: false)) {
            note('Skipped — translations left in place.');

            return;
        }

        File::deleteDirectory($dir);
        info('Deleted ' . $this->relativePath($dir));
    }

    protected function runFinalSteps(): void
    {
        $command = 'composer remove arzcode/shared-secrets';

        info('Removing the arzcode/shared-secrets package…');

        $result = Process::path(base_path())
            ->forever()
            ->run($command, function(string $type, string $output): void {
                $this->output->write($output);
            });

        if (! $result->successful()) {
            error(sprintf('`%s` failed — run it manually to finish the uninstall.', $command));

            return;
        }

        outro('Shared Secrets uninstall complete.');
    }

    protected function relativePath(string $absolutePath): string
    {
        $base = base_path() . DIRECTORY_SEPARATOR;

        return str_starts_with($absolutePath, $base) ? substr($absolutePath, strlen($base)) : $absolutePath;
    }
}
