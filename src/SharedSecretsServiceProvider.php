<?php

namespace Arzcode\SharedSecrets;

use Arzcode\SharedSecrets\Commands\PruneSharedSecretsCommand;
use Arzcode\SharedSecrets\Commands\UninstallCommand;
use Arzcode\SharedSecrets\Support\PanelProviders;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

class SharedSecretsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'shared-secrets';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigrations(static::migrationNames())
            ->hasCommands([PruneSharedSecretsCommand::class, UninstallCommand::class])
            ->hasInstallCommand(fn(InstallCommand $command): InstallCommand => $command
                ->startWith(fn(InstallCommand $cmd) => intro('Installing Shared Secrets'))
                ->publishMigrations()
                ->endWith(function(InstallCommand $cmd): void {
                    $steps = [
                        fn() => $this->publishConfigFile($cmd),
                        fn() => $this->runMigrations($cmd),
                        $this->patchPanelProviders(...),
                        fn() => outro('Shared Secrets install complete. Reload your Filament panel.')
                    ];

                    foreach ($steps as $step) {
                        $cmd->newLine();
                        $step();
                    }
                })
                // Spatie hides install commands from `artisan list`.
                ->setHidden(false)
                ->setDescription('Publish the migration and the config of Shared Secrets, run the migration and register the plugin in your panels'));
    }

    public function packageBooted(): void
    {
        $this->callAfterResolving(Schedule::class, function(Schedule $schedule): void {
            if (! config()->boolean('shared-secrets.schedule.enabled', true)) {
                return;
            }

            $schedule->command(PruneSharedSecretsCommand::class)->hourly()->withoutOverlapping();
        });
    }

    /**
     * Also used by the uninstaller to find the published copies.
     *
     * @return array<string>
     */
    public static function migrationNames(): array
    {
        return [
            'create_shared_secrets_tables'
        ];
    }

    protected function publishConfigFile(InstallCommand $command): void
    {
        if (file_exists(config_path('shared-secrets.php'))) {
            note('config/shared-secrets.php already present — leaving as-is.');

            return;
        }

        if (! confirm(label: 'Would you like to publish the config file?', default: true)) {
            note('Skipped — the package defaults apply.');

            return;
        }

        $command->callSilently('vendor:publish', ['--tag' => 'shared-secrets-config']);
        info('Config file published.');
    }

    protected function runMigrations(InstallCommand $command): void
    {
        if (! confirm(label: 'Would you like to run the migrations now?', default: true)) {
            note('Skipped — run `php artisan migrate` when you are ready.');

            return;
        }

        $command->call('migrate');
    }

    /**
     * Each panel is asked about on its own: sharing secrets is rarely wanted
     * in every panel of an application.
     */
    protected function patchPanelProviders(): void
    {
        $files = PanelProviders::files();

        if ($files === []) {
            warning('No app/Providers/Filament/*PanelProvider.php found — register SharedSecretsPlugin manually in your panel provider.');

            return;
        }

        foreach ($files as $file) {
            $contents = (string)file_get_contents($file);
            $relative = $this->relativePath($file);

            if (PanelProviders::hasPlugin($contents)) {
                note(sprintf('SharedSecretsPlugin already present in %s — leaving as-is.', $relative));

                continue;
            }

            if (! confirm(label: sprintf('Register SharedSecretsPlugin in %s?', $relative), default: true)) {
                note(sprintf('Skipped %s.', $relative));

                continue;
            }

            $patched = PanelProviders::addPlugin($contents);

            if ($patched === null || ! PanelProviders::parses($patched)) {
                warning(sprintf('Could not patch %s — add ->plugins([SharedSecretsPlugin::make()]) manually.', $relative));

                continue;
            }

            file_put_contents($file, $patched);
            info(sprintf('Patched %s to register SharedSecretsPlugin.', $relative));
        }
    }

    protected function relativePath(string $absolutePath): string
    {
        $base = base_path() . DIRECTORY_SEPARATOR;

        return str_starts_with($absolutePath, $base) ? substr($absolutePath, strlen($base)) : $absolutePath;
    }
}
