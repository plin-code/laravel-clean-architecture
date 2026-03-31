<?php

use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Commands\MakeControllerCommand;

describe('MakeControllerCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem;
        $this->command    = new MakeControllerCommand($this->filesystem);

        // Mock the output to avoid writeln errors
        $mockOutput = mock('Symfony\Component\Console\Output\OutputInterface');
        $mockOutput->shouldReceive('writeln')->andReturn();
        $mockOutput->shouldReceive('write')->andReturn();

        $reflection     = new ReflectionClass($this->command);
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($this->command, $mockOutput);
    });

    it('has correct command signature and description', function () {
        expect($this->command->getName())->toBe('clean-arch:make-controller');
        expect($this->command->getDescription())->toBe('Create a new controller in the Infrastructure layer');
    });

    it('has required name argument', function () {
        $definition = $this->command->getDefinition();
        expect($definition->hasArgument('name'))->toBeTrue();
    });

    it('has api and web options', function () {
        $definition = $this->command->getDefinition();
        expect($definition->hasOption('api'))->toBeTrue();
        expect($definition->hasOption('web'))->toBeTrue();
        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('can replace placeholders correctly', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('replacePlaceholders');
        $method->setAccessible(true);

        $content = 'class {{ControllerName}} for {{DomainName}} and {{PluralDomainName}} with {{domainVariable}}';
        $result  = $method->invoke($this->command, $content, 'User');

        expect($result)
            ->toContain('class UserController')
            ->toContain('User')
            ->toContain('Users')
            ->toContain('user')
            ->not->toContain('{{ControllerName}}')
            ->not->toContain('{{DomainName}}');
    });

    it('handles getStub method for API controller', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        // Test with existing stub
        $result = $method->invoke($this->command, 'controller');
        expect($result)->toBeString();
        expect($result)->toContain('<?php');
    });

    it('handles getStub method for Web controller', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        // Test with existing stub
        $result = $method->invoke($this->command, 'web-controller');
        expect($result)->toBeString();
        expect($result)->toContain('<?php');
    });

    it('creates api controller in Http/Controllers/Api directory', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('createApiController');
        $method->setAccessible(true);

        $writtenPath    = null;
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php // controller stub');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturnUsing(function ($path) use (&$writtenPath) {
            $writtenPath = $path;
            return true;
        });

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);
        $method->invoke($this->command, 'Users');

        expect($writtenPath)->toContain('Infrastructure/Http/Controllers/Api/UsersController.php');
    });
});
