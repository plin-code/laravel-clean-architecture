<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Commands\MakeExportCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

describe('MakeExportCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem;
        $this->command    = new MakeExportCommand($this->filesystem);
    });

    it('has correct command signature', function () {
        expect($this->command->getName())->toBe('clean-arch:make-export');
    });

    it('has required arguments', function () {
        $definition = $this->command->getDefinition();
        expect($definition->hasArgument('name'))->toBeTrue();
        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('creates export in correct path', function () {
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('exists')->andReturn(true);
        $filesystem->shouldReceive('get')->andReturn('<?php // {{DomainName}} {{PluralDomainName}}');
        $filesystem->shouldReceive('isDirectory')->andReturn(false);
        $filesystem->shouldReceive('makeDirectory')->once();
        $filesystem->shouldReceive('put')->once()->withArgs(function ($path, $content) {
            return str_contains($path, 'Infrastructure/Exports/UserExport.php');
        });

        $command = new MakeExportCommand($filesystem);
        $command->setLaravel(app());
        $command->setOutput(new OutputStyle(
            new ArrayInput([]),
            new NullOutput
        ));

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('createExport');
        $method->setAccessible(true);
        $method->invoke($command, 'User');
    });

    it('replaces placeholders correctly', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('replacePlaceholders');
        $method->setAccessible(true);

        $content = '{{DomainName}} {{PluralDomainName}} {{domainVariable}}';
        $result  = $method->invoke($this->command, $content, 'User');

        expect($result)
            ->toContain('User')
            ->toContain('Users')
            ->toContain('user')
            ->not->toContain('{{DomainName}}')
            ->not->toContain('{{PluralDomainName}}')
            ->not->toContain('{{domainVariable}}');
    });
});
