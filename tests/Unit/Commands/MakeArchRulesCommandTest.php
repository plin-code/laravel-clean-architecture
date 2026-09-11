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
            ->toContain("new IsNotA('Illuminate\\\\Console\\\\Command')")
            ->toContain("ClassSet::fromDir(__DIR__ . '/app/Domain')");
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

        expect(File::get($this->path))->toContain("ClassSet::fromDir(__DIR__ . '/src/Domain')");
    });
});
