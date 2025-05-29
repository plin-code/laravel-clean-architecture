<?php

use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Commands\MakeServiceCommand;

describe('MakeServiceCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem;
        $this->command    = new MakeServiceCommand($this->filesystem);
    });

    it('has correct command signature and description', function () {
        expect($this->command->getName())->toBe('clean-arch:make-service');
        expect($this->command->getDescription())->toBe('Create a new service in the Application layer');
    });

    it('has required name argument', function () {
        $definition = $this->command->getDefinition();
        expect($definition->hasArgument('name'))->toBeTrue();
    });

    it('accepts force option', function () {
        $definition = $this->command->getDefinition();
        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('can replace placeholders correctly', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('replacePlaceholders');
        $method->setAccessible(true);

        $content = 'class {{DomainName}}Service for {{PluralDomainName}} with {{domainVariable}}';
        $result  = $method->invoke($this->command, $content, 'User');

        expect($result)
            ->toContain('class UserService')
            ->toContain('Users')
            ->toContain('user')
            ->not->toContain('{{DomainName}}')
            ->not->toContain('{{PluralDomainName}}')
            ->not->toContain('{{domainVariable}}');
    });

    it('handles getStub method', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        // Test with existing stub
        $result = $method->invoke($this->command, 'service');
        expect($result)->toBeString();
        expect($result)->toContain('<?php');
    });
});
