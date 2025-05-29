<?php

use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Commands\MakeActionCommand;

describe('MakeActionCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem;
        $this->command = new MakeActionCommand($this->filesystem);
    });

    it('has correct command signature and description', function () {
        expect($this->command->getName())->toBe('clean-arch:make-action');
        expect($this->command->getDescription())->toBe('Create a new action in the specified domain');
    });

    it('has required arguments', function () {
        $definition = $this->command->getDefinition();
        expect($definition->hasArgument('name'))->toBeTrue();
        expect($definition->hasArgument('domain'))->toBeTrue();
    });

    it('accepts force option', function () {
        $definition = $this->command->getDefinition();
        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('can replace placeholders correctly', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('replacePlaceholders');
        $method->setAccessible(true);
        
        $content = '{{ActionName}} in {{DomainName}} for {{PluralDomainName}}';
        $result = $method->invoke($this->command, $content, 'CreateUser', 'User');
        
        expect($result)
            ->toContain('CreateUserAction')
            ->toContain('User')
            ->toContain('Users')
            ->not->toContain('{{ActionName}}')
            ->not->toContain('{{DomainName}}');
    });

    it('generates correct action paths', function () {
        // Test pluralization logic for action paths
        expect(\Illuminate\Support\Str::plural('User'))->toBe('Users');
        expect(\Illuminate\Support\Str::plural('Category'))->toBe('Categories');
        expect(\Illuminate\Support\Str::plural('ProductCategory'))->toBe('ProductCategories');
    });

    it('handles getStub method', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getStub');
        $method->setAccessible(true);
        
        // Test with existing stub
        $result = $method->invoke($this->command, 'action');
        expect($result)->toBeString();
        expect($result)->toContain('<?php');
    });
}); 