# Architecture tests

[Architecture validation](architecture-validation.md) checks what a class imports. It cannot see the shape of a class that already sits in the right layer: an action with five public methods, a listener that holds a whole use case, a model that declares its casts in two ways.

This page collects Pest tests for those conventions. The package does not generate them: copy the ones you agree with into `tests/Arch` and edit them. They are examples from an application in production, not rules of the package, and each one says what it cannot see.

They assume the default layout, with the layers in `app/Domain`, `app/Application` and `app/Infrastructure` under the `App` namespace. Change the paths and namespaces if your `config/clean-architecture.php` says otherwise.

## Setup

The tests need [Pest](https://pestphp.com). Laravel ships with the Unit and Feature suites only, so add the folder to `phpunit.xml`, otherwise the tests never run:

```xml
<testsuite name="Arch">
    <directory>tests/Arch</directory>
</testsuite>
```

Most tests below take their classes from disk instead of a list written by hand, so a new class is checked the day it is created. Add this helper to `tests/Pest.php`:

```php
/**
 * Classes declared under a folder of app/, read from disk, so a new class
 * is checked the day it is created.
 *
 * The path is built from __DIR__ and not from app_path(): datasets call
 * this while PHPUnit collects the files, before the application exists.
 *
 * @return list<string>
 */
function appClassesIn(string $folder): array
{
    $appPath = dirname(__DIR__).'/app';

    // An application with no listeners yet has nothing to check.
    if (! is_dir($appPath.'/'.$folder)) {
        return [];
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appPath.'/'.$folder, FilesystemIterator::SKIP_DOTS),
    );

    $classes = [];

    foreach ($files as $file) {
        if ($file->getExtension() === 'php') {
            $classes[] = 'App\\'.str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                substr($file->getPathname(), strlen($appPath) + 1),
            );
        }
    }

    sort($classes);

    return $classes;
}
```

Every class the helper returns goes through `class_exists()` before it is checked, so run `composer dump-autoload` after moving files.

## Layers

`tests/Arch/LayersTest.php`. The same five rules `clean-arch:make-arch-rules` writes for phparkitect, with the `arch()` function that comes with Pest. Use them when you would rather run one tool. You lose the baseline of phparkitect, so on a codebase with many violations keep phparkitect until they are fixed.

```php
<?php

arch('the domain does not depend on the outer layers')
    ->expect('App\Domain')
    ->not->toUse(['App\Application', 'App\Infrastructure']);

arch('the application does not depend on the infrastructure')
    ->expect('App\Application')
    ->not->toUse('App\Infrastructure');

arch('observers are not in the domain')
    ->expect('App\Domain')
    ->not->toHaveSuffix('Observer');

// Queued mail and notifications implement ShouldQueue too, and they do
// belong to the infrastructure layer.
arch('queued jobs are not in the infrastructure')
    ->expect('App\Infrastructure')
    ->not->toImplement('Illuminate\Contracts\Queue\ShouldQueue')
    ->ignoring(['App\Infrastructure\Mail', 'App\Infrastructure\Notifications']);
```

When the project lets the application layer send mail or notifications straight from the infrastructure layer, with `validation.application_infrastructure_allowed`, add the same namespaces to the second test:

```php
    ->not->toUse('App\Infrastructure')
    ->ignoring(['App\Infrastructure\Mail', 'App\Infrastructure\Notifications']);
```

## Actions

`tests/Arch/ActionsTest.php`. An action is one use case: a class named after a verb and a subject, such as `PublishArticleAction`, whose only public entry point is `execute()`. A second public method is a second use case, and it belongs to another action.

```php
<?php

// Abstract classes, such as BaseAction, are not use cases.
$actions = fn (): array => array_values(array_filter(
    appClassesIn('Application/Actions'),
    fn (string $class): bool => str_ends_with($class, 'Action')
        && class_exists($class)
        && ! (new ReflectionClass($class))->isAbstract(),
));

it('is named after a verb and a subject', function (string $action) {
    // At least two capitalised words before the suffix: the verb, then the
    // subject. "ExportAction" alone says nothing about the use case.
    expect(class_basename($action))->toMatch('/^[A-Z][a-z]+[A-Z][A-Za-z0-9]*Action$/');
})->with($actions);

it('exposes execute() as its only public method', function (string $action) {
    $publicMethods = array_values(array_filter(
        array_map(
            fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass($action))->getMethods(ReflectionMethod::IS_PUBLIC),
        ),
        fn (string $name): bool => $name !== '__construct',
    ));

    expect($publicMethods)->toBe(['execute'], "{$action} exposes: ".implode(', ', $publicMethods));
})->with($actions);
```

The actions `clean-arch:make-domain` generates pass both tests. The name test wants a lowercase letter after the first capital, so a verb written as an acronym fails it.

## Listeners

`tests/Arch/ListenersTest.php`. A listener turns an event into a call to an action. The use case, with its queries and its writes, belongs to the action, where it can be tested and called from elsewhere. A listener that holds a whole use case passes the layer rules, because it sits in the right layer and imports only what it may, so this test reads the source of each listener and looks for the calls that read from or write to the database.

```php
<?php

/**
 * Method names that run a query or persist a model, on an Eloquent model,
 * a relation or a builder.
 */
$queryOrWrite = '/^(query|newQuery|where\w*|orWhere\w*|find\w*|first\w*|sole|pluck|exists|doesntExist|chunk\w*|lazy\w*|cursor|paginate|simplePaginate|save\w*|update\w*|delete\w*|forceDelete\w*|restore\w*|create\w*|insert\w*|upsert|increment\w*|decrement\w*|fill|forceFill|touch\w*|sync\w*|attach|detach|associate|dissociate)$/i';

$listeners = fn (): array => array_values(array_filter(appClassesIn('Application/Listeners'), 'class_exists'));

it('neither queries nor writes', function (string $listener) use ($queryOrWrite) {
    $tokens = array_values(array_filter(
        PhpToken::tokenize((string) file_get_contents((string) (new ReflectionClass($listener))->getFileName())),
        fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
    ));

    $found = [];

    foreach ($tokens as $i => $token) {
        $previous = $tokens[$i - 1] ?? null;
        $next = $tokens[$i + 1] ?? null;

        if ($token->is([T_STRING, T_NAME_FULLY_QUALIFIED])
            && ltrim($token->text, '\\') === 'DB'
            && $next?->is(T_DOUBLE_COLON)) {
            $found[] = "DB:: on line {$token->line}";
        }

        // A name after -> or :: and before an opening parenthesis is a call.
        if ($token->is(T_STRING)
            && $previous?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON])
            && $next?->text === '('
            && preg_match($queryOrWrite, $token->text) === 1) {
            $found[] = "{$token->text}() on line {$token->line}";
        }
    }

    expect($found)->toBe([], "{$listener} queries or writes: ".implode(', ', $found));
})->with($listeners);
```

It matches method names, not types. A method of your own called `update()` is reported, and a query behind a name that is not on the list is not. Edit the list when either happens.

## Models

`tests/Arch/ModelsTest.php`. Laravel reads the casts from the `casts()` method and from the `$casts` property. A codebase that uses both makes every reader check two places.

```php
<?php

use Illuminate\Database\Eloquent\Model;

$models = fn (): array => array_values(array_filter(
    appClassesIn('Domain'),
    fn (string $class): bool => class_exists($class)
        && is_subclass_of($class, Model::class)
        && ! (new ReflectionClass($class))->isAbstract(),
));

it('declares its casts in casts() and not in the $casts property', function (string $model) {
    // Eloquent declares $casts itself, so the property always exists: what
    // matters is that no class of the application declares it again.
    $declaredBy = (new ReflectionProperty($model, 'casts'))->getDeclaringClass()->getName();

    expect(str_starts_with($declaredBy, 'App\\'))->toBeFalse("{$declaredBy} declares the \$casts property");
})->with($models);
```

The models this package generates declare the `$casts` property, so they fail this test until you move the casts into the method. Copy it only if `casts()` is the convention of your project.

## What is not here

- Form requests without `rules()`. The `BaseRequest` that `clean-arch:install` writes declares `rules()` as abstract, so PHP already refuses a request without it.
- Where policies and interfaces live, and a common parent for domain exceptions. The package has not taken a position on these yet, and a test would take it by accident.

## A test with nothing to check passes

These tests fail when a class breaks a convention, and they pass when they find no class at all. A folder that was renamed, or a namespace that no longer matches, leaves a test green for the wrong reason. Once the application has a few actions, add a test that fails when the list is empty:

```php
it('finds the actions on disk', function () use ($actions) {
    expect($actions())->not->toBeEmpty();
});
```
