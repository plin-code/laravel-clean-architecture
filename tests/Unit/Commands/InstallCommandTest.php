<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use PlinCode\LaravelCleanArchitecture\Commands\InstallCleanArchitectureCommand;

describe('InstallCleanArchitectureCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem();
        $this->command = new InstallCleanArchitectureCommand($this->filesystem);
        
        // Mock the output to avoid writeln errors
        $mockOutput = mock('Symfony\Component\Console\Output\OutputInterface');
        $mockOutput->shouldReceive('writeln')->andReturn();
        $mockOutput->shouldReceive('write')->andReturn();
        
        $reflection = new ReflectionClass($this->command);
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($this->command, $mockOutput);
    });

    it('has correct command signature and description', function () {
        expect($this->command->getName())->toBe('clean-arch:install');
        expect($this->command->getDescription())->toBe('Install Clean Architecture structure in Laravel project');
    });

    it('accepts force option', function () {
        $definition = $this->command->getDefinition();
        expect($definition->hasOption('force'))->toBeTrue();
        expect($definition->getOption('force')->getDescription())->toBe('Overwrite existing files');
    });

    it('handles missing stub files gracefully', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        // Mock filesystem to return false for exists
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(false);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        expect(function () use ($method) {
            $method->invoke($this->command, 'non-existent-stub');
        })->toThrow(Exception::class, 'Stub file not found');
    });

    it('can get stub content when file exists', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        $stubContent = '<?php // Test stub content';

        // Mock filesystem to return stub content
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn($stubContent);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'test-stub');
        expect($result)->toBe($stubContent);
    });

    it('can create directory structure', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createDirectoryStructure');
        $method->setAccessible(true);

        // Mock filesystem
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        // Test that the method executes without throwing
        $result = $method->invoke($this->command);
        expect($result)->toBeNull(); // Void methods return null
    });

    it('can generate base classes', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createBaseClasses');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php // Base class content');
        $mockFilesystem->shouldReceive('put')->andReturn(true);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command);
        expect($result)->toBeNull();
    });

    it('handles composer.json updates', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('updateComposerAutoload');
        $method->setAccessible(true);

        // Mock filesystem
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('{"autoload": {"psr-4": {}}}');
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command);
        expect($result)->toBeNull();
    });

    it('can create configuration file', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createConfigFile');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php return [];');
        $mockFilesystem->shouldReceive('put')->andReturn(true);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command);
        expect($result)->toBeNull();
    });

    it('can create readme file', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createReadme');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('# Clean Architecture README');
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command);
        expect($result)->toBeNull();
    });

    it('can create base model', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createBaseModel');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php // Base model content');
        $mockFilesystem->shouldReceive('put')->andReturn(true);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command);
        expect($result)->toBeNull();
    });

    it('can create exception classes', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createExceptionClasses');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php // Exception content');
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command);
        expect($result)->toBeNull();
    });
});