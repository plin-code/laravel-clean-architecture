<?php

use Illuminate\Support\Facades\File;

/**
 * `make-job` and `make-domain` wrap the given name in a fixed prefix,
 * `Process`, both in the class name and in the file name. The prefix is
 * configurable through `generation.job_prefix`, since not every job is a
 * "process something" job, and it defaults to the historical `Process`.
 */
describe('Configurable job prefix', function () {

    afterEach(function () {
        foreach ([app_path('Domain'), app_path('Application'), app_path('Infrastructure')] as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }
    });

    it('defaults to the Process prefix', function () {
        $this->artisan('clean-arch:make-job', ['name' => 'Article'])->assertExitCode(0);

        $path = app_path('Application/Jobs/ProcessArticleJob.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::get($path))->toContain('class ProcessArticleJob implements ShouldQueue');
    });

    it('uses the configured prefix', function () {
        config()->set('clean-architecture.generation.job_prefix', 'Handle');

        $this->artisan('clean-arch:make-job', ['name' => 'Article'])->assertExitCode(0);

        $path = app_path('Application/Jobs/HandleArticleJob.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::get($path))->toContain('class HandleArticleJob implements ShouldQueue');
        expect(File::exists(app_path('Application/Jobs/ProcessArticleJob.php')))->toBeFalse();
    });

    it('strips the configured prefix from the given name instead of the default one', function () {
        config()->set('clean-architecture.generation.job_prefix', 'Handle');

        $this->artisan('clean-arch:make-job', ['name' => 'HandleArticleJob'])->assertExitCode(0);

        expect(File::exists(app_path('Application/Jobs/HandleArticleJob.php')))->toBeTrue();
        expect(File::exists(app_path('Application/Jobs/HandleHandleArticleJobJob.php')))->toBeFalse();
    });

    it('generates the job under its own name when the prefix is empty', function (?string $prefix) {
        config()->set('clean-architecture.generation.job_prefix', $prefix);

        $this->artisan('clean-arch:make-job', ['name' => 'Article'])->assertExitCode(0);

        $path = app_path('Application/Jobs/ArticleJob.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::get($path))->toContain('class ArticleJob implements ShouldQueue');
    })->with([null, '']);

    it('leaves no placeholder behind in the generated job', function () {
        config()->set('clean-architecture.generation.job_prefix', '');

        $this->artisan('clean-arch:make-job', ['name' => 'Article'])->assertExitCode(0);

        expect(File::get(app_path('Application/Jobs/ArticleJob.php')))->not->toContain('{{JobPrefix}}');
    });

    it('is honoured by make-domain as well', function () {
        config()->set('clean-architecture.generation.job_prefix', 'Handle');

        $this->artisan('clean-arch:make-domain', ['name' => 'Article'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'no')
            ->expectsConfirmation('Would you like to generate a Listener?', 'no')
            ->expectsConfirmation('Would you like to generate a Job?', 'yes')
            ->expectsConfirmation('Would you like to generate a Mail?', 'no')
            ->expectsConfirmation('Would you like to generate a Notification?', 'no')
            ->expectsConfirmation('Would you like to generate an Export?', 'no')
            ->assertExitCode(0);

        $path = app_path('Application/Jobs/HandleArticleJob.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::get($path))->toContain('class HandleArticleJob implements ShouldQueue');
    });
});
