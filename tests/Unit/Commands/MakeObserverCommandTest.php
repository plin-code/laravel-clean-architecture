<?php

use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Commands\MakeObserverCommand;

describe('MakeObserverCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem;
        $this->command    = new MakeObserverCommand($this->filesystem);
    });

    it('has correct command signature', function () {
        expect($this->command->getName())->toBe('clean-arch:make-observer');
    });

    it('has required arguments', function () {
        $definition = $this->command->getDefinition();
        expect($definition->hasArgument('name'))->toBeTrue();
        expect($definition->hasArgument('domain'))->toBeTrue();
        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('creates observer in correct path', function () {
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('exists')->andReturn(true);
        $filesystem->shouldReceive('get')->andReturn('<?php // {{DomainName}} {{PluralDomainName}}');
        $filesystem->shouldReceive('isDirectory')->andReturn(false);
        $filesystem->shouldReceive('makeDirectory')->once();
        $filesystem->shouldReceive('put')->once()->withArgs(function ($path, $content) {
            return str_contains($path, 'Infrastructure/Observers/Users/UserObserver.php');
        });

        $command = new MakeObserverCommand($filesystem);
        $command->setLaravel(app());
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput
        ));

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('createObserver');
        $method->setAccessible(true);
        $method->invoke($command, 'User', 'User');
    });

    it('replaces placeholders correctly', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('replacePlaceholders');
        $method->setAccessible(true);

        $content = '{{DomainName}} {{PluralDomainName}} {{domainVariable}}';
        $result  = $method->invoke($this->command, $content, 'User', 'User');

        expect($result)
            ->toContain('User')
            ->toContain('Users')
            ->toContain('user')
            ->not->toContain('{{DomainName}}')
            ->not->toContain('{{PluralDomainName}}')
            ->not->toContain('{{domainVariable}}');
    });
});
