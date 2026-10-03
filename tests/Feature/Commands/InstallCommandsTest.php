<?php

use Arzcode\SharedSecrets\Support\PanelProviders;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

const REGISTER_IN_TEST_PANEL = 'Register SharedSecretsPlugin in app/Providers/Filament/TestPanelProvider.php?';

/**
 * Both commands patch files of the testbench skeleton, so each test backs up
 * and restores what it touches.
 */
beforeEach(function(): void {
    $this->providersPath = app_path('Providers/Filament');
    $this->providerFile = $this->providersPath . '/TestPanelProvider.php';
    $this->configFile = config_path('shared-secrets.php');
    $this->migrationsPath = database_path('migrations');

    File::ensureDirectoryExists($this->providersPath);
    file_put_contents($this->providerFile, <<<'PHP'
        <?php

        namespace App\Providers\Filament;

        use Filament\Panel;
        use Filament\PanelProvider;

        class TestPanelProvider extends PanelProvider
        {
            public function panel(Panel $panel): Panel
            {
                return $panel
                    ->id('test')
                    ->path('test')
                    ->plugins([
                        SomeOtherPlugin::make(),
                    ]);
            }
        }
        PHP);
});

afterEach(function(): void {
    File::deleteDirectory($this->providersPath);
    File::delete($this->configFile);
    File::delete(glob($this->migrationsPath . '/*_create_shared_secrets_tables.php') ?: []);
});

it('installs the package: publishes the migration and the config and registers the plugin', function(): void {
    $this->artisan('shared-secrets:install')
        ->expectsConfirmation('Would you like to publish the config file?', 'yes')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsConfirmation(REGISTER_IN_TEST_PANEL, 'yes')
        ->assertSuccessful();

    $provider = file_get_contents($this->providerFile);

    expect(glob($this->migrationsPath . '/*_create_shared_secrets_tables.php'))->toHaveCount(1)
        ->and($this->configFile)->toBeFile()
        ->and($provider)->toContain('use Arzcode\SharedSecrets\SharedSecretsPlugin;')
        ->and($provider)->toContain("SomeOtherPlugin::make(),\n                SharedSecretsPlugin::make(),\n            ]);")
        ->and(PanelProviders::parses($provider))->toBeTrue();
});

it('publishes and registers nothing twice when the install runs again', function(): void {
    $this->artisan('shared-secrets:install')
        ->expectsConfirmation('Would you like to publish the config file?', 'yes')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsConfirmation(REGISTER_IN_TEST_PANEL, 'yes')
        ->assertSuccessful();

    $this->artisan('shared-secrets:install')
        ->expectsOutputToContain('config/shared-secrets.php already present')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsOutputToContain('SharedSecretsPlugin already present in app/Providers/Filament/TestPanelProvider.php')
        ->assertSuccessful();

    expect(substr_count(file_get_contents($this->providerFile), PanelProviders::ENTRY))->toBe(1)
        ->and(glob($this->migrationsPath . '/*_create_shared_secrets_tables.php'))->toHaveCount(1);
});

it('leaves a panel provider untouched when its registration is declined', function(): void {
    $original = file_get_contents($this->providerFile);

    $this->artisan('shared-secrets:install')
        ->expectsConfirmation('Would you like to publish the config file?', 'no')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsConfirmation(REGISTER_IN_TEST_PANEL, 'no')
        ->assertSuccessful();

    expect(file_get_contents($this->providerFile))->toBe($original)
        ->and($this->configFile)->not->toBeFile();
});

it('uninstalls the package, reversing the install', function(): void {
    Process::fake();
    File::ensureDirectoryExists(lang_path('vendor/shared-secrets/en'));

    $this->artisan('shared-secrets:install')
        ->expectsConfirmation('Would you like to publish the config file?', 'yes')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsConfirmation(REGISTER_IN_TEST_PANEL, 'yes')
        ->assertSuccessful();

    $this->artisan('shared-secrets:uninstall')
        ->expectsConfirmation('Do you want to continue?', 'yes')
        ->expectsConfirmation('Would you like to drop the Shared Secrets database tables?', 'yes')
        ->expectsConfirmation('Delete 1 published Shared Secrets migration file(s)?', 'yes')
        ->expectsConfirmation('Delete the published config file config/shared-secrets.php?', 'yes')
        ->expectsConfirmation('Delete the published translations in lang/vendor/shared-secrets?', 'yes')
        ->assertSuccessful();

    $provider = file_get_contents($this->providerFile);

    expect($provider)->not->toContain('SharedSecretsPlugin')
        ->and($provider)->toContain('SomeOtherPlugin::make(),')
        ->and(Schema::hasTable('shared_secrets'))->toBeFalse()
        ->and(Schema::hasTable('shared_secret_events'))->toBeFalse()
        ->and(glob($this->migrationsPath . '/*_create_shared_secrets_tables.php'))->toBe([])
        ->and($this->configFile)->not->toBeFile()
        ->and(lang_path('vendor/shared-secrets'))->not->toBeDirectory();

    Process::assertRan('composer remove arzcode/shared-secrets');
});

it('keeps the tables, the migration and the config when each deletion is declined', function(): void {
    Process::fake();

    $this->artisan('shared-secrets:install')
        ->expectsConfirmation('Would you like to publish the config file?', 'yes')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsConfirmation(REGISTER_IN_TEST_PANEL, 'yes')
        ->assertSuccessful();

    $this->artisan('shared-secrets:uninstall')
        ->expectsConfirmation('Do you want to continue?', 'yes')
        ->expectsConfirmation('Would you like to drop the Shared Secrets database tables?', 'no')
        ->expectsConfirmation('Delete 1 published Shared Secrets migration file(s)?', 'no')
        ->expectsConfirmation('Delete the published config file config/shared-secrets.php?', 'no')
        ->assertSuccessful();

    expect(file_get_contents($this->providerFile))->not->toContain('SharedSecretsPlugin')
        ->and(Schema::hasTable('shared_secrets'))->toBeTrue()
        ->and(glob($this->migrationsPath . '/*_create_shared_secrets_tables.php'))->toHaveCount(1)
        ->and($this->configFile)->toBeFile();

    Process::assertRan('composer remove arzcode/shared-secrets');
});

it('changes nothing when the uninstall is not confirmed', function(): void {
    Process::fake();
    file_put_contents($this->providerFile, PanelProviders::addPlugin(file_get_contents($this->providerFile)));

    $this->artisan('shared-secrets:uninstall')
        ->expectsConfirmation('Do you want to continue?', 'no')
        ->assertSuccessful();

    expect(file_get_contents($this->providerFile))->toContain(PanelProviders::ENTRY)
        ->and(Schema::hasTable('shared_secrets'))->toBeTrue();

    Process::assertNothingRan();
});

it('tells the user to finish by hand when composer remove fails', function(): void {
    Process::fake(['*' => Process::result(exitCode: 1)]);

    $this->artisan('shared-secrets:uninstall')
        ->expectsConfirmation('Do you want to continue?', 'yes')
        ->expectsConfirmation('Would you like to drop the Shared Secrets database tables?', 'no')
        ->expectsOutputToContain('`composer remove arzcode/shared-secrets` failed')
        ->assertSuccessful();
});
