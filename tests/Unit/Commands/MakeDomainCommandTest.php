<?php

use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Commands\MakeDomainCommand;

describe('MakeDomainCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem;
        $this->command    = new MakeDomainCommand($this->filesystem);

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
        $method     = $reflection->getMethod('createDomainModel');
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
        $method     = $reflection->getMethod('createDomainEnums');
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
        $method     = $reflection->getMethod('createDomainEvents');
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
        $method     = $reflection->getMethod('createActions');
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
        $method     = $reflection->getMethod('createService');
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
        $method     = $reflection->getMethod('createController');
        $method->setAccessible(true);

        $writtenPath = null;

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace App\Infrastructure\Http\Controllers\Api; class {{PluralDomainName}}Controller {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturnUsing(function ($path) use (&$writtenPath) {
            $writtenPath = $path;

            return true;
        });

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $method->invoke($this->command, 'User');
        expect($writtenPath)->toContain('Infrastructure/Http/Controllers/Api');
    });

    it('can create requests', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('createRequests');
        $method->setAccessible(true);

        $writtenPaths = [];

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace App\Infrastructure\Http\Requests; class {{RequestName}} {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturnUsing(function ($path) use (&$writtenPaths) {
            $writtenPaths[] = $path;

            return true;
        });

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $method->invoke($this->command, 'User');
        expect($writtenPaths)->each(fn ($path) => $path->toContain('Infrastructure/Http/Requests'));
    });

    it('can create resource', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('createResource');
        $method->setAccessible(true);

        $writtenPath = null;

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace App\Infrastructure\Http\Resources; class {{DomainName}}Resource {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturnUsing(function ($path) use (&$writtenPath) {
            $writtenPath = $path;

            return true;
        });

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $method->invoke($this->command, 'User');
        expect($writtenPath)->toContain('Infrastructure/Http/Resources');
    });

    it('can create tests', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('createTests');
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
        $method     = $reflection->getMethod('replacePlaceholders');
        $method->setAccessible(true);

        // Use the actual placeholders that the method expects
        $content = 'class {{DomainName}} extends {{BaseClass}} { protected $table = "{{domain-table}}"; var {{domainVariable}}; plural {{PluralDomainName}}; }';
        $name    = 'User';
        $extra   = ['BaseClass' => 'Model'];

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
        $method     = $reflection->getMethod('getTableName');
        $method->setAccessible(true);

        expect($method->invoke($this->command, 'User'))->toBe('users');
        expect($method->invoke($this->command, 'BlogPost'))->toBe('blog_posts');
        expect($method->invoke($this->command, 'Category'))->toBe('categories');
    });

    it('can get stub content', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('getStub');
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

    it('creates domain model in nested Models directory', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('createDomainModel');
        $method->setAccessible(true);

        $writtenPath = null;

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php namespace App\Domain\{{PluralDomainName}}\Models; class {{DomainName}} {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturnUsing(function ($path) use (&$writtenPath) {
            $writtenPath = $path;

            return true;
        });

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $method->invoke($this->command, 'User');
        expect($writtenPath)->toContain('Domain/Users/Models/User.php');
    });

    it('can create observer', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('createObserver');
        $method->setAccessible(true);

        $writtenPath = null;

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php class {{DomainName}}Observer {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturnUsing(function ($path) use (&$writtenPath) {
            $writtenPath = $path;

            return true;
        });

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $method->invoke($this->command, 'User');
        expect($writtenPath)->toContain('Infrastructure/Observers/Users/UserObserver.php');
    });

    it('can create listener', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('createListener');
        $method->setAccessible(true);

        $writtenPath = null;

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php class {{DomainName}}EventListener {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturnUsing(function ($path) use (&$writtenPath) {
            $writtenPath = $path;

            return true;
        });

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $method->invoke($this->command, 'User');
        expect($writtenPath)->toContain('Application/Listeners/UserEventListener.php');
    });

    it('can create job', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('createJob');
        $method->setAccessible(true);

        $writtenPath = null;

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php class Process{{DomainName}}Job {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturnUsing(function ($path) use (&$writtenPath) {
            $writtenPath = $path;

            return true;
        });

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $method->invoke($this->command, 'User');
        expect($writtenPath)->toContain('Application/Jobs/ProcessUserJob.php');
    });

    it('can create mail', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('createMail');
        $method->setAccessible(true);

        $writtenPath = null;

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php class {{DomainName}}Mail {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturnUsing(function ($path) use (&$writtenPath) {
            $writtenPath = $path;

            return true;
        });

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $method->invoke($this->command, 'User');
        expect($writtenPath)->toContain('Infrastructure/Mail/UserMail.php');
    });

    it('can create notification', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('createNotification');
        $method->setAccessible(true);

        $writtenPath = null;

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php class {{DomainName}}Notification {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturnUsing(function ($path) use (&$writtenPath) {
            $writtenPath = $path;

            return true;
        });

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $method->invoke($this->command, 'User');
        expect($writtenPath)->toContain('Infrastructure/Notifications/UserNotification.php');
    });

    it('can create export', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('createExport');
        $method->setAccessible(true);

        $writtenPath = null;

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('get')->andReturn('<?php class {{DomainName}}Export {}');
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $mockFilesystem->shouldReceive('makeDirectory')->andReturn(true);
        $mockFilesystem->shouldReceive('put')->andReturnUsing(function ($path) use (&$writtenPath) {
            $writtenPath = $path;

            return true;
        });

        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $method->invoke($this->command, 'User');
        expect($writtenPath)->toContain('Infrastructure/Exports/UserExport.php');
    });

    it('throws exception when stub file not found', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('getStub');
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
