<?php

use Illuminate\Support\Facades\File;

describe('MakeArchRulesCommand', function () {
    beforeEach(function () {
        $this->path = base_path('phparkitect.php');

        if (File::exists($this->path)) {
            File::delete($this->path);
        }
    });

    afterEach(function () {
        if (File::exists($this->path)) {
            File::delete($this->path);
        }
    });

    it('writes a phparkitect config that parses as valid php', function () {
        $this->artisan('clean-arch:make-arch-rules')->assertExitCode(0);

        expect(File::exists($this->path))->toBeTrue();

        $contents = File::get($this->path);

        expect(token_get_all($contents, TOKEN_PARSE))->toBeArray()
            ->and($contents)->not->toContain('{{');
    });

    it('writes the rules and the autoload guard into the config', function () {
        $this->artisan('clean-arch:make-arch-rules')->assertExitCode(0);

        expect(File::get($this->path))
            ->toContain('throw new RuntimeException')
            ->toContain("new ResideInOneOfTheseNamespaces('App\\\\Domain')")
            ->toContain("new NotHaveNameMatching('*Observer')")
            ->not->toContain("new IsNotA('Illuminate\\\\Console\\\\Command')")
            ->toContain("ClassSet::fromDir(__DIR__.'/app/Domain')");
    });

    it('refuses to overwrite an existing config without force', function () {
        File::put($this->path, '<?php // handwritten');

        $this->artisan('clean-arch:make-arch-rules')->assertExitCode(1);

        expect(File::get($this->path))->toBe('<?php // handwritten');
    });

    it('overwrites an existing config with force', function () {
        File::put($this->path, '<?php // handwritten');

        $this->artisan('clean-arch:make-arch-rules', ['--force' => true])->assertExitCode(0);

        expect(File::get($this->path))->toContain('Rule::allClasses()');
    });

    it('rejects an invalid infrastructure allowlist without writing the config', function (mixed $allowed) {
        config()->set('clean-architecture.validation.application_infrastructure_allowed', $allowed);

        $this->artisan('clean-arch:make-arch-rules')
            ->expectsOutputToContain('validation.application_infrastructure_allowed')
            ->assertExitCode(1);

        expect(File::exists($this->path))->toBeFalse();
    })->with([
        'a string'          => ['Mail'],
        'an integer entry'  => [[42]],
        'an empty entry'    => [['']],
        'a separator entry' => [['\\']],
    ]);

    it('keeps an existing config untouched when the allowlist is invalid, even with force', function () {
        File::put($this->path, '<?php // handwritten');
        config()->set('clean-architecture.validation.application_infrastructure_allowed', ['Mail', '']);

        $this->artisan('clean-arch:make-arch-rules', ['--force' => true])->assertExitCode(1);

        expect(File::get($this->path))->toBe('<?php // handwritten');
    });

    it('ignores the allowlist when the application rule is disabled', function () {
        config()->set('clean-architecture.validation.rules.application_no_infrastructure_imports', false);
        config()->set('clean-architecture.validation.application_infrastructure_allowed', 'Mail');

        $this->artisan('clean-arch:make-arch-rules')->assertExitCode(0);

        expect(File::get($this->path))->not->toContain("new ResideInOneOfTheseNamespaces('App\\\\Application')");
    });

    it('follows the configured directories when building the class sets', function () {
        config()->set('clean-architecture.directories.domain', 'src/Domain');

        $this->artisan('clean-arch:make-arch-rules')->assertExitCode(0);

        expect(File::get($this->path))->toContain("ClassSet::fromDir(__DIR__.'/src/Domain')");
    });

    it('does not tell how to install phparkitect when the app has it', function () {
        // The testbench app links the package vendor, where phparkitect is installed.
        $this->artisan('clean-arch:make-arch-rules')
            ->expectsOutput('Run "vendor/bin/phparkitect check".')
            ->doesntExpectOutputToContain('composer require')
            ->assertExitCode(0);
    });

    it('tells how to install phparkitect when the app does not have it', function () {
        $app = sys_get_temp_dir() . '/clean-arch-no-phparkitect-' . uniqid();
        File::ensureDirectoryExists($app);
        $this->app->setBasePath($app);

        try {
            $this->artisan('clean-arch:make-arch-rules')
                ->expectsOutput('Install phparkitect with "composer require --dev phparkitect/phparkitect", then run "vendor/bin/phparkitect check".')
                ->assertExitCode(0);
        } finally {
            File::deleteDirectory($app);
        }
    });

    it('imports the classes the autoload guard checks, in pint order', function () {
        $this->artisan('clean-arch:make-arch-rules')->assertExitCode(0);

        expect(File::get($this->path))
            ->toContain("use App\\Domain\\Shared\\BaseModel;\nuse Arkitect\\ClassSet;")
            ->toContain("use Arkitect\\Rules\\Rule;\nuse Illuminate\\Console\\Command;\n");
    });

    it('points the guard at the configured domain namespace', function () {
        config()->set('clean-architecture.directories.domain', 'app/Core/Domain');
        config()->set('clean-architecture.default_namespace', 'Acme');

        $this->artisan('clean-arch:make-arch-rules')->assertExitCode(0);

        expect(File::get($this->path))->toContain("use Acme\\Core\\Domain\\Shared\\BaseModel;\nuse Arkitect\\ClassSet;");
    });

    it('writes the outside layers rule and scans the root directory when enabled', function () {
        config()->set('clean-architecture.validation.rules.no_classes_outside_layers', true);

        $this->artisan('clean-arch:make-arch-rules')->assertExitCode(0);

        $contents = File::get($this->path);

        expect($contents)
            ->toContain("->that(new ResideInOneOfTheseNamespaces('App'))")
            ->toContain("ClassSet::fromDir(__DIR__.'/app')")
            ->not->toContain("ClassSet::fromDir(__DIR__.'/app/Domain')")
            ->and(token_get_all($contents, TOKEN_PARSE))->toBeArray();
    });

    it('fails without writing when the layers share no top level directory', function () {
        config()->set('clean-architecture.validation.rules.no_classes_outside_layers', true);
        config()->set('clean-architecture.directories.domain', 'src/Domain');

        $this->artisan('clean-arch:make-arch-rules')
            ->expectsOutputToContain('src/Domain, app/Application, app/Infrastructure')
            ->assertExitCode(1);

        expect(File::exists($this->path))->toBeFalse();
    });

    it('rejects an invalid outside layers allowlist without writing the config', function (mixed $allowed) {
        config()->set('clean-architecture.validation.rules.no_classes_outside_layers', true);
        config()->set('clean-architecture.validation.outside_layers_allowed', $allowed);

        $this->artisan('clean-arch:make-arch-rules')
            ->expectsOutputToContain('validation.outside_layers_allowed')
            ->assertExitCode(1);

        expect(File::exists($this->path))->toBeFalse();
    })->with([
        'a string'         => ['Providers'],
        'an empty entry'   => [['Providers', '']],
        'an integer entry' => [[42]],
    ]);

    it('ignores the outside layers allowlist and the directories while the rule is off', function () {
        config()->set('clean-architecture.validation.outside_layers_allowed', 'Providers');
        config()->set('clean-architecture.directories.domain', 'src/Domain');

        $this->artisan('clean-arch:make-arch-rules')->assertExitCode(0);
    });
});
