<?php

use Illuminate\Support\Facades\File;

describe('MakeArchTestsCommand', function () {
    beforeEach(function () {
        $this->directory = base_path('tests/Arch');

        File::deleteDirectory($this->directory);
    });

    afterEach(function () {
        File::deleteDirectory($this->directory);
        File::delete(base_path('phpunit.xml'));
    });

    it('writes the helper and the three tests as valid php', function () {
        $this->artisan('clean-arch:make-arch-tests')->assertExitCode(0);

        foreach (['Classes.php', 'ActionsTest.php', 'ListenersTest.php', 'RequestsTest.php'] as $file) {
            $contents = File::get("{$this->directory}/{$file}");

            expect(token_get_all($contents, TOKEN_PARSE))->toBeArray()
                ->and($contents)->not->toContain('{{');
        }
    });

    it('writes the configured layers into the helper', function () {
        config()->set('clean-architecture.default_namespace', 'Shop');
        config()->set('clean-architecture.directories.application', 'app/UseCases');

        $this->artisan('clean-arch:make-arch-tests')->assertExitCode(0);

        expect(File::get("{$this->directory}/Classes.php"))
            ->toContain("'domain' => ['app/Domain', 'Shop\\Domain']")
            ->toContain("'application' => ['app/UseCases', 'Shop\\UseCases']");
    });

    it('skips an existing test without force and still writes the others', function () {
        File::ensureDirectoryExists($this->directory);
        File::put("{$this->directory}/ActionsTest.php", '<?php // edited by the application');

        $this->artisan('clean-arch:make-arch-tests')
            ->expectsOutputToContain('Skipped: tests/Arch/ActionsTest.php')
            ->assertExitCode(0);

        expect(File::get("{$this->directory}/ActionsTest.php"))->toBe('<?php // edited by the application')
            ->and(File::exists("{$this->directory}/ListenersTest.php"))->toBeTrue();
    });

    it('overwrites an existing test with force', function () {
        File::ensureDirectoryExists($this->directory);
        File::put("{$this->directory}/ActionsTest.php", '<?php // edited by the application');

        $this->artisan('clean-arch:make-arch-tests', ['--force' => true])->assertExitCode(0);

        expect(File::get("{$this->directory}/ActionsTest.php"))->toContain('cleanArchClassesIn');
    });

    it('says to add the folder to a test suite when phpunit.xml does not name it', function () {
        $this->artisan('clean-arch:make-arch-tests')
            ->expectsOutputToContain('Add tests/Arch to a test suite in phpunit.xml')
            ->assertExitCode(0);
    });

    it('says nothing about the test suite when phpunit.xml already names it', function () {
        File::put(base_path('phpunit.xml'), '<testsuite name="Arch"><directory>tests/Arch</directory></testsuite>');

        $this->artisan('clean-arch:make-arch-tests')
            ->doesntExpectOutputToContain('Add tests/Arch to a test suite')
            ->assertExitCode(0);
    });
});
