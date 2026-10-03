<?php

use Arzcode\SharedSecrets\Support\PanelProviders;

it('adds a plugins call to a panel provider that has none', function(): void {
    $contents = <<<'PHP'
        <?php

        namespace App\Providers\Filament;

        use Filament\Panel;

        class AdminPanelProvider
        {
            public function panel(Panel $panel): Panel
            {
                return $panel
                    ->id('admin');
            }
        }
        PHP;

    $patched = PanelProviders::addPlugin($contents);

    expect($patched)
        ->toContain("use Filament\\Panel;\nuse Arzcode\\SharedSecrets\\SharedSecretsPlugin;\n")
        ->toContain("->id('admin')\n            ->plugins([\n                SharedSecretsPlugin::make(),\n            ]);")
        ->and(PanelProviders::parses($patched))->toBeTrue();
});

it('ignores brackets and quotes inside comments when adding the plugin', function(): void {
    $contents = <<<'PHP'
        <?php

        class AdminPanelProvider
        {
            public function panel($panel)
            {
                return $panel
                    /* don't close ]; here */
                    # nor ']' here
                    ->plugins([
                        A::make(), // ]
                    ]);
            }
        }
        PHP;

    $patched = PanelProviders::addPlugin($contents);

    expect(PanelProviders::parses($patched))->toBeTrue()
        ->and($patched)->toContain("A::make(), // ]\n                SharedSecretsPlugin::make(),\n            ]);");
});

it('adds the missing comma after the last entry, not after its trailing comment', function(): void {
    $contents = "<?php\n\nclass P\n{\n    public function panel(\$panel)\n    {\n        return \$panel\n            ->plugins([\n                A::make() // last\n            ]);\n    }\n}\n";

    $patched = PanelProviders::addPlugin($contents);

    expect(PanelProviders::parses($patched))->toBeTrue()
        ->and($patched)->toContain("A::make(), // last\n                SharedSecretsPlugin::make(),\n            ]);");
});

it('returns null when the file has no panel chain to add the plugin to', function(): void {
    expect(PanelProviders::addPlugin("<?php\n\nclass P {}\n"))->toBeNull();
});

it('removes only the plugin entry, keeping the other plugins', function(string $before, string $after): void {
    $wrap = fn(string $plugins): string => "<?php\n\nuse Arzcode\\SharedSecrets\\SharedSecretsPlugin;\n\nclass P\n{\n    public function panel(\$panel)\n    {\n        return \$panel\n{$plugins};\n    }\n}\n";

    $removed = PanelProviders::removePlugin($wrap($before));

    expect($removed)->toBe(str_replace("use Arzcode\\SharedSecrets\\SharedSecretsPlugin;\n", '', $wrap($after)))
        ->and(PanelProviders::parses($removed))->toBeTrue();
})->with([
    'single line, last'  => ['            ->plugins([A::make(), SharedSecretsPlugin::make()])', '            ->plugins([A::make()])'],
    'single line, first' => ['            ->plugins([SharedSecretsPlugin::make(), A::make()])', '            ->plugins([A::make()])'],
    'own line, chained'  => [
        "            ->plugins([\n                A::make(),\n                SharedSecretsPlugin::make()\n                    ->navigationGroup(fn() => __('Tools')),\n                B::make(),\n            ])",
        "            ->plugins([\n                A::make(),\n                B::make(),\n            ])",
    ],
    'only plugin'         => ["            ->id('a')\n            ->plugins([\n                SharedSecretsPlugin::make(),\n            ])", "            ->id('a')"],
    'plugin call'         => ["            ->id('a')\n            ->plugin(SharedSecretsPlugin::make())\n            ->path('a')", "            ->id('a')\n            ->path('a')"],
    'plugin call, inline' => ["            ->id('a')->plugin(SharedSecretsPlugin::make()->revealPath('s'))->path('a')", "            ->id('a')->path('a')"],
]);

it('reports a broken file as not parsing', function(): void {
    expect(PanelProviders::parses("<?php\n\nclass P { public function panel() { return [ ; } }\n"))->toBeFalse();
});
