<?php

use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Commands\ValidateArchitectureCommand;

describe('ValidateArchitectureCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem;
        $this->command    = new ValidateArchitectureCommand($this->filesystem);

        $mockOutput = mock('Symfony\Component\Console\Output\OutputInterface');
        $mockOutput->shouldReceive('writeln')->andReturn();
        $mockOutput->shouldReceive('write')->andReturn();

        $reflection     = new ReflectionClass($this->command);
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($this->command, $mockOutput);
    });

    it('has correct command signature', function () {
        expect($this->command->getName())->toBe('clean-arch:validate');
    });

    it('has correct description', function () {
        expect($this->command->getDescription())->toBe('Validate Clean Architecture dependency rules');
    });

    it('returns empty violations for non-existent directory', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('checkImportViolations');
        $method->setAccessible(true);

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $violations = $method->invoke($this->command, 'app/Domain', 'App\\Application\\');
        expect($violations)->toBeArray()->toBeEmpty();
    });

    it('returns empty violations for non-existent directory in file pattern check', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('checkFilePatternViolations');
        $method->setAccessible(true);

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $violations = $method->invoke($this->command, 'app/Domain', '*Observer*');
        expect($violations)->toBeArray()->toBeEmpty();
    });

    it('detects duplicate services directory when it exists', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('checkDirectoryNotExists');
        $method->setAccessible(true);

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(true);
        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'app/Infrastructure/Services');
        expect($result)->toBeTrue();
    });

    it('passes when services directory does not exist in infrastructure', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('checkDirectoryNotExists');
        $method->setAccessible(true);

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'app/Infrastructure/Services');
        expect($result)->toBeFalse();
    });
});
