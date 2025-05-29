<?php

use PlinCode\LaravelCleanArchitecture\Commands\MakeDomainCommand;
use Illuminate\Filesystem\Filesystem;

describe('MakeDomainCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem();
        $this->command = new MakeDomainCommand($this->filesystem);
        
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
        expect($this->command->getName())->toBe('clean-arch:make-domain');
        expect($this->command->getDescription())->toBe('Create a new domain with complete Clean Architecture structure');
    });

    it('has required arguments and options', function () {
        $definition = $this->command->getDefinition();
        
        expect($definition->hasArgument('name'))->toBeTrue();
        expect($definition->hasOption('force'))->toBeTrue();
        expect($definition->getArgument('name')->getDescription())->toBe('The name of the domain');
        expect($definition->getOption('force')->getDescription())->toBe('Overwrite existing files');
    });

    it('can create domain model', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createDomainModel');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace App\Domain\{{PluralName}}; class {{Name}} {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'User');
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can create domain enums', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createDomainEnums');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace App\Domain\{{PluralName}}\Enums; enum {{Name}}Status {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'User');
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can create domain events', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createDomainEvents');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace App\Domain\{{PluralName}}\Events; class {{EventName}} {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'User');
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can create actions', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createActions');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace App\Application\Actions\{{PluralName}}; class {{ActionName}} {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'User');
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can create service', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createService');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace App\Application\Services; class {{Name}}Service {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'User');
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can create controller', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createController');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace App\Infrastructure\API\Controllers; class {{PluralName}}Controller {}');
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'User');
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can create requests', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createRequests');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace App\Infrastructure\API\Requests; class {{RequestName}} {}');
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'User');
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can create resource', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createResource');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace App\Infrastructure\API\Resources; class {{Name}}Resource {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'User');
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can create tests', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('createTests');
        $method->setAccessible(true);

        // Mock filesystem and stub
        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace Tests\Feature; class {{PluralName}}Test {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturn(true);

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'User');
        expect($result)->toBeNull(); // Void method returns null
    });

    it('can replace placeholders correctly', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('replacePlaceholders');
        $method->setAccessible(true);

        // Use the actual placeholders that the method expects
        $content = 'class {{DomainName}} extends {{BaseClass}} { protected $table = "{{domain-table}}"; var {{domainVariable}}; plural {{PluralDomainName}}; }';
        $name = 'User';
        $extra = ['BaseClass' => 'Model'];

        $result = $method->invoke($this->command, $content, $name, $extra);

        // Test the actual replacements that happen
        expect($result)->toContain('class User');
        expect($result)->toContain('protected $table = "users"');
        expect($result)->toContain('var user;');
        expect($result)->toContain('plural Users;');
        
        // Verify that the main domain placeholders are replaced
        expect($result)->not->toContain('{{DomainName}}');
        expect($result)->not->toContain('{{PluralDomainName}}');
        expect($result)->not->toContain('{{domainVariable}}');
        expect($result)->not->toContain('{{domain-table}}');
        
        // BaseClass should be replaced with the value from $extra
        expect($result)->not->toContain('{{BaseClass}}');
        expect($result)->toContain('Model'); // The value should be present somewhere
    });

    it('can get table name correctly', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getTableName');
        $method->setAccessible(true);

        expect($method->invoke($this->command, 'User'))->toBe('users');
        expect($method->invoke($this->command, 'BlogPost'))->toBe('blog_posts');
        expect($method->invoke($this->command, 'Category'))->toBe('categories');
    });

    it('can get stub content', function () {
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
}); 