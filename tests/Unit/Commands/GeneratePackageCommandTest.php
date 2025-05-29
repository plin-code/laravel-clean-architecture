<?php

use PlinCode\LaravelCleanArchitecture\Commands\GeneratePackageCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;

describe('GeneratePackageCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem();
        $this->command = new GeneratePackageCommand($this->filesystem);
        $this->tempPath = sys_get_temp_dir() . '/test-package-' . uniqid();
        
        // Mock the output to avoid writeln errors
        $mockOutput = mock('Symfony\Component\Console\Output\OutputInterface');
        $mockOutput->shouldReceive('writeln')->andReturn();
        $mockOutput->shouldReceive('write')->andReturn();
        
        $reflection = new ReflectionClass($this->command);
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($this->command, $mockOutput);
    });

    afterEach(function () {
        if (is_dir($this->tempPath)) {
            $this->filesystem->deleteDirectory($this->tempPath);
        }
    });

    it('has correct command signature and description', function () {
        expect($this->command->getName())->toBe('clean-arch:generate-package');
        expect($this->command->getDescription())->toBe('Generate a new Laravel package with Clean Architecture structure');
    });

    it('has required arguments', function () {
        $definition = $this->command->getDefinition();
        
        expect($definition->hasArgument('name'))->toBeTrue();
        expect($definition->hasArgument('vendor'))->toBeTrue();
        expect($definition->getArgument('name')->getDescription())->toBe('The name of the package');
        expect($definition->getArgument('vendor')->getDescription())->toBe('The vendor name');
    });

    it('has correct options', function () {
        $definition = $this->command->getDefinition();
        
        expect($definition->hasOption('path'))->toBeTrue();
        expect($definition->hasOption('force'))->toBeTrue();
        expect($definition->getOption('path')->getDescription())->toBe('Custom path for the package');
        expect($definition->getOption('force')->getDescription())->toBe('Overwrite existing files');
    });

    it('can create package structure', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createPackageStructure');
        $method->setAccessible(true);

        $packageName = 'test-package';
        $studlyName = 'TestPackage';
        $namespace = 'TestVendor\\TestPackage';
        $vendor = 'test-vendor';

        // Mock the filesystem to avoid actual file creation
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturn(true);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('stub content');

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, $this->tempPath, $packageName, $studlyName, $namespace, $vendor);
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can create package composer.json', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createPackageComposer');
        $method->setAccessible(true);

        $packageName = 'test-package';
        $studlyName = 'TestPackage';
        $namespace = 'TestVendor\\TestPackage';
        $vendor = 'test-vendor';

        // Create temp directory
        $this->filesystem->makeDirectory($this->tempPath, 0755, true);

        $method->invoke($this->command, $this->tempPath, $packageName, $studlyName, $namespace, $vendor);

        expect(file_exists($this->tempPath . '/composer.json'))->toBeTrue();
        
        $composerContent = json_decode(file_get_contents($this->tempPath . '/composer.json'), true);
        expect($composerContent['name'])->toBe('test-vendor/test-package');
        expect($composerContent['description'])->toBe('Laravel package for TestPackage');
        expect($composerContent['type'])->toBe('library');
    });

    it('can create service provider', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createPackageServiceProvider');
        $method->setAccessible(true);

        $studlyName = 'TestPackage';
        $namespace = 'TestVendor\\TestPackage';

        // Create a mock stub content
        $stubContent = '<?php namespace {{Namespace}}; class {{StudlyName}}ServiceProvider {}';
        
        // Mock filesystem to return stub content
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn($stubContent);
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, $this->tempPath, $studlyName, $namespace);
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can create package model', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createPackageModel');
        $method->setAccessible(true);

        $studlyName = 'TestPackage';
        $namespace = 'TestVendor\\TestPackage';

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace {{Namespace}}; class {{StudlyName}} {}');
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, $this->tempPath, $studlyName, $namespace);
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can create package service', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createPackageService');
        $method->setAccessible(true);

        $studlyName = 'TestPackage';
        $namespace = 'TestVendor\\TestPackage';

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace {{Namespace}}; class {{StudlyName}}Service {}');
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, $this->tempPath, $studlyName, $namespace);
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can create package readme', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createPackageReadme');
        $method->setAccessible(true);

        $packageName = 'test-package';
        $studlyName = 'TestPackage';
        $vendor = 'test-vendor';

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('# {{PackageName}} by {{VendorName}}');
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, $this->tempPath, $packageName, $studlyName, $vendor);
        expect($result)->toBeNull(); // Void method returns null
    });

    it('throws exception when stub file not found', function () {
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
}); 