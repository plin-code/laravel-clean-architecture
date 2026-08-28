<?php

use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Commands\MakeServiceCommand;
use Symfony\Component\Console\Input\ArrayInput;

describe('MakeServiceCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem;
        $this->command    = new MakeServiceCommand($this->filesystem);

        $mockOutput = mock('Symfony\Component\Console\Output\OutputInterface');
        $mockOutput->shouldReceive('writeln')->andReturn();
        $mockOutput->shouldReceive('write')->andReturn();

        $reflection     = new ReflectionClass($this->command);
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($this->command, $mockOutput);

        $input         = new ArrayInput(['name' => 'User'], $this->command->getDefinition());
        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($this->command, $input);
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

    it('accepts no-base option', function () {
        $definition = $this->command->getDefinition();
        expect($definition->hasOption('no-base'))->toBeTrue();
        expect($definition->getOption('no-base')->getDefault())->toBeFalse();
    });

    it('can replace placeholders correctly', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('replacePlaceholders');
        $method->setAccessible(true);

        $content = 'class {{DomainName}}Service{{ServiceExtends}} for {{PluralDomainName}}';
        $extra   = ['{{ServiceExtends}}' => ' extends BaseService'];
        $result  = $method->invoke($this->command, $content, 'User', $extra);

        expect($result)
            ->toContain('class UserService extends BaseService')
            ->toContain('Users')
            ->not->toContain('{{DomainName}}')
            ->not->toContain('{{PluralDomainName}}');
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
