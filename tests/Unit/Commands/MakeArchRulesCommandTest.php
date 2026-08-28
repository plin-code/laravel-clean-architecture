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

    it('follows the configured directories when building the class sets', function () {
        config()->set('clean-architecture.directories.domain', 'src/Domain');

        $this->artisan('clean-arch:make-arch-rules')->assertExitCode(0);

        expect(File::get($this->path))->toContain("ClassSet::fromDir(__DIR__ . '/src/Domain')");
    });
});
