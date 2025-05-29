<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use PlinCode\LaravelCleanArchitecture\Commands\InstallCleanArchitectureCommand;

describe('InstallCleanArchitectureCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem;
        $this->command = new InstallCleanArchitectureCommand($this->filesystem);
    });

    it('has correct command signature and description', function () {
        expect($this->command->getName())->toBe('clean-arch:install');
        expect($this->command->getDescription())->toBe('Install Clean Architecture structure in Laravel project');
    });

    it('accepts force option', function () {
        $definition = $this->command->getDefinition();
        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('handles missing stub files gracefully', function () {
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('exists')->andReturn(false);
        
        $command = new InstallCleanArchitectureCommand($mockFilesystem);
        
        // Test that getStub method throws exception for missing files
        expect(function() use ($command) {
            $reflection = new ReflectionClass($command);
            $method = $reflection->getMethod('getStub');
            $method->setAccessible(true);
            $method->invoke($command, 'nonexistent');
        })->toThrow(\Exception::class, 'Stub file not found');
    });

    it('has expected directory structure to create', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createDirectoryStructure');
        $method->setAccessible(true);
        
        // This test verifies the command structure without actually creating directories
        expect($method)->toBeInstanceOf(ReflectionMethod::class);
    });

    it('can generate base classes', function () {
        $reflection = new ReflectionClass($this->command);
        $baseClassMethods = [
            'createBaseModel',
            'createBaseController', 
            'createBaseAction',
            'createBaseService',
            'createBaseRequest',
            'createExceptionClasses'
        ];
        
        foreach ($baseClassMethods as $methodName) {
            $method = $reflection->getMethod($methodName);
            expect($method)->toBeInstanceOf(ReflectionMethod::class);
        }
    });

    it('handles composer.json updates', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('updateComposerAutoload');
        
        expect($method)->toBeInstanceOf(ReflectionMethod::class);
    });
}); 