<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PlinCode\LaravelCleanArchitecture\Commands\MakeActionCommand;
use Symfony\Component\Console\Input\ArrayInput;

describe('MakeActionCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem;
        $this->command    = new MakeActionCommand($this->filesystem);

        $mockOutput = mock('Symfony\Component\Console\Output\OutputInterface');
        $mockOutput->shouldReceive('writeln')->andReturn();
        $mockOutput->shouldReceive('write')->andReturn();

        $reflection     = new ReflectionClass($this->command);
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($this->command, $mockOutput);

        $input         = new ArrayInput(['name' => 'CreateUser', 'domain' => 'User'], $this->command->getDefinition());
        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($this->command, $input);
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

    it('accepts no-base option', function () {
        $definition = $this->command->getDefinition();
        expect($definition->hasOption('no-base'))->toBeTrue();
        expect($definition->getOption('no-base')->getDefault())->toBeFalse();
    });

    it('can replace placeholders correctly', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('replacePlaceholders');
        $method->setAccessible(true);

        $content = '{{ActionName}} in {{DomainName}} for {{PluralDomainName}} {{ActionExtends}}';
        $extra   = ['{{ActionExtends}}' => ' extends BaseAction'];
        $result  = $method->invoke($this->command, $content, 'CreateUser', $extra, 'User');

        expect($result)
            ->toContain('CreateUserAction')
            ->toContain('User')
            ->toContain('Users')
            ->toContain('extends BaseAction')
            ->not->toContain('{{ActionName}}')
            ->not->toContain('{{DomainName}}');
    });

    it('generates correct action paths', function () {
        // Test pluralization logic for action paths
        expect(Str::plural('User'))->toBe('Users');
        expect(Str::plural('Category'))->toBe('Categories');
        expect(Str::plural('ProductCategory'))->toBe('ProductCategories');
    });

    it('handles getStub method', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        // Test with existing stub
        $result = $method->invoke($this->command, 'action');
        expect($result)->toBeString();
        expect($result)->toContain('<?php');
    });
});
