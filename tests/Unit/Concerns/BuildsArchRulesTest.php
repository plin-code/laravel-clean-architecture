<?php

use PlinCode\LaravelCleanArchitecture\Concerns\BuildsArchRules;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;

/**
 * Call the protected rule builder on an anonymous class that uses the trait.
 *
 * @return array<int, string>
 */
function buildArchRules(): array
{
    $subject = new class
    {
        use BuildsArchRules;
        use ResolvesArchitectureDirectories;
    };

    $method = new ReflectionMethod($subject, 'archRules');
    $method->setAccessible(true);

    return $method->invoke($subject);
}

/**
 * Call the protected guard builder on an anonymous class that uses the trait.
 */
function buildAutoloadGuard(): string
{
    $subject = new class
    {
        use BuildsArchRules;
        use ResolvesArchitectureDirectories;
    };

    $method = new ReflectionMethod($subject, 'autoloadGuard');
    $method->setAccessible(true);

    return $method->invoke($subject);
}

/**
 * Call the protected class set builder on an anonymous class that uses the trait.
 */
function buildArchClassSets(): string
{
    $subject = new class
    {
        use BuildsArchRules;
        use ResolvesArchitectureDirectories;
    };

    $method = new ReflectionMethod($subject, 'archClassSets');
    $method->setAccessible(true);

    return $method->invoke($subject);
}

describe('BuildsArchRules', function () {
    it('builds a dependency rule for the domain layer', function () {
        $rules = buildArchRules();

        expect($rules[0])
            ->toContain("new ResideInOneOfTheseNamespaces('App\\\\Domain')")
            ->toContain("new NotDependsOnTheseNamespaces(['App\\\\Application'])");
    });

    it('keeps the domain away from the infrastructure layer', function () {
        expect(buildArchRules()[1])
            ->toContain("new ResideInOneOfTheseNamespaces('App\\\\Domain')")
            ->toContain("new NotDependsOnTheseNamespaces(['App\\\\Infrastructure'])");
    });

    it('keeps the application away from the infrastructure layer', function () {
        expect(buildArchRules()[2])
            ->toContain("new ResideInOneOfTheseNamespaces('App\\\\Application')")
            ->toContain("new NotDependsOnTheseNamespaces(['App\\\\Infrastructure'])");
    });

    it('forbids observers in the domain layer', function () {
        expect(buildArchRules()[3])
            ->toContain("new ResideInOneOfTheseNamespaces('App\\\\Domain')")
            ->toContain("new NotHaveNameMatching('*Observer')");
    });

    it('forbids queued jobs in the infrastructure layer', function () {
        expect(buildArchRules()[4])
            ->toContain("new ResideInOneOfTheseNamespaces('App\\\\Infrastructure')")
            ->toContain("new IsNotA('Illuminate\\\\Contracts\\\\Queue\\\\ShouldQueue')");
    });

    it('leaves queued mail and notifications out of the jobs rule', function () {
        expect(buildArchRules()[4])
            ->toContain("->andThat(new IsNotA('Illuminate\\\\Mail\\\\Mailable'))")
            ->toContain("->andThat(new IsNotA('Illuminate\\\\Notifications\\\\Notification'))");
    });

    it('leaves the console command rule out by default', function () {
        $rules = buildArchRules();

        expect($rules)->toHaveCount(5)
            ->and(implode("\n", $rules))->not->toContain('Illuminate\\\\Console\\\\Command');
    });

    it('forbids console commands in the infrastructure layer when enabled', function () {
        config()->set('clean-architecture.validation.rules.no_commands_in_infrastructure', true);

        expect(buildArchRules()[5])
            ->toContain("new ResideInOneOfTheseNamespaces('App\\\\Infrastructure')")
            ->toContain("new IsNotA('Illuminate\\\\Console\\\\Command')");
    });

    it('gives every rule a reason and a statement terminator', function () {
        foreach (buildArchRules() as $rule) {
            expect($rule)
                ->toStartWith('    $rules[] = Rule::allClasses()')
                ->toContain('->because(')
                ->toEndWith(';');
        }
    });

    it('skips a rule disabled in the config', function () {
        config()->set('clean-architecture.validation.rules.no_observers_in_domain', false);

        $rules = buildArchRules();

        expect($rules)->toHaveCount(4)
            ->and(implode("\n", $rules))->not->toContain("NotHaveNameMatching('*Observer')");
    });

    it('emits an autoload guard checking a framework class and an application class', function () {
        expect(buildAutoloadGuard())
            ->toContain('! class_exists(Command::class) || ! class_exists(BaseModel::class)')
            ->toContain('throw new RuntimeException');
    });

    it('explains in a comment why the guard exists and how to change it', function () {
        expect(buildAutoloadGuard())
            ->toContain('// ')
            ->toContain('exits 0');
    });

    it('generates the application rule unchanged when the allowlist is missing or empty', function (?array $allowed) {
        if ($allowed !== null) {
            config()->set('clean-architecture.validation.application_infrastructure_allowed', $allowed);
        }

        $expected = <<<'PHP'
                $rules[] = Rule::allClasses()
                    ->that(new ResideInOneOfTheseNamespaces('App\\Application'))
                    ->should(new NotDependsOnTheseNamespaces(['App\\Infrastructure']))
                    ->because('the application layer must not depend on the infrastructure layer');
            PHP;

        expect(buildArchRules()[2])->toBe($expected);
    })->with([
        'missing' => [null],
        'empty'   => [[]],
    ]);

    it('excludes the allowed infrastructure namespaces from the application rule', function () {
        config()->set('clean-architecture.validation.application_infrastructure_allowed', ['Mail', 'Notifications']);

        expect(buildArchRules()[2])
            ->toContain("new NotDependsOnTheseNamespaces(['App\\\\Infrastructure'], ['App\\\\Infrastructure\\\\Mail', 'App\\\\Infrastructure\\\\Notifications'])");
    });

    it('resolves the allowed namespaces against the configured infrastructure layer', function () {
        config()->set('clean-architecture.default_namespace', 'Acme');
        config()->set('clean-architecture.directories.infrastructure', 'app/Core/Infrastructure');
        config()->set('clean-architecture.validation.application_infrastructure_allowed', ['Mail']);

        expect(buildArchRules()[2])
            ->toContain("new NotDependsOnTheseNamespaces(['Acme\\\\Core\\\\Infrastructure'], ['Acme\\\\Core\\\\Infrastructure\\\\Mail'])");
    });

    it('trims separators around the allowed segments', function (string $segment) {
        config()->set('clean-architecture.validation.application_infrastructure_allowed', [$segment]);

        expect(buildArchRules()[2])
            ->toContain("new NotDependsOnTheseNamespaces(['App\\\\Infrastructure'], ['App\\\\Infrastructure\\\\Mail'])");
    })->with([
        'backslashes' => ['\\Mail\\'],
        'slashes'     => ['/Mail/'],
    ]);

    it('leaves the other rules unchanged when the allowlist is set', function () {
        $before = buildArchRules();

        config()->set('clean-architecture.validation.application_infrastructure_allowed', ['Mail', 'Notifications']);

        $after = buildArchRules();

        expect($after)->toHaveCount(5);

        foreach ([0, 1, 3, 4] as $index) {
            expect($after[$index])->toBe($before[$index]);
        }
    });

    it('follows the configured namespace and directories', function () {
        config()->set('clean-architecture.default_namespace', 'Acme');
        config()->set('clean-architecture.directories.domain', 'app/Core/Domain');

        expect(buildArchRules()[0])
            ->toContain("new ResideInOneOfTheseNamespaces('Acme\\\\Core\\\\Domain')");
    });

    it('leaves the outside layers rule out by default', function () {
        expect(implode("\n", buildArchRules()))->not->toContain("ResideInOneOfTheseNamespaces('App')");
    });

    it('keeps every class of the root namespace in a layer or an allowed namespace when enabled', function () {
        config()->set('clean-architecture.validation.rules.no_classes_outside_layers', true);

        $expected = <<<'PHP'
                $rules[] = Rule::allClasses()
                    ->that(new ResideInOneOfTheseNamespaces('App'))
                    ->should(new ResideInOneOfTheseNamespaces('App\\Domain', 'App\\Application', 'App\\Infrastructure', 'App\\Providers'))
                    ->because('every class of the application belongs to one of the three layers');
            PHP;

        expect(buildArchRules()[5])->toBe($expected);
    });

    it('resolves the allowed outside namespaces against the root namespace', function () {
        config()->set('clean-architecture.default_namespace', 'Acme');
        config()->set('clean-architecture.validation.rules.no_classes_outside_layers', true);
        config()->set('clean-architecture.validation.outside_layers_allowed', ['/Providers/', 'Livewire']);

        expect(buildArchRules()[5])
            ->toContain("->that(new ResideInOneOfTheseNamespaces('Acme'))")
            ->toContain("'Acme\\\\Providers', 'Acme\\\\Livewire'))");
    });

    it('allows nothing outside the layers with an empty allowlist', function () {
        config()->set('clean-architecture.validation.rules.no_classes_outside_layers', true);
        config()->set('clean-architecture.validation.outside_layers_allowed', []);

        expect(buildArchRules()[5])
            ->toContain("->should(new ResideInOneOfTheseNamespaces('App\\\\Domain', 'App\\\\Application', 'App\\\\Infrastructure'))");
    });

    it('takes the top level directory as the root when the layers are nested', function () {
        config()->set('clean-architecture.validation.rules.no_classes_outside_layers', true);
        config()->set('clean-architecture.directories.domain', 'app/Core/Domain');

        expect(buildArchRules()[5])
            ->toContain("->that(new ResideInOneOfTheseNamespaces('App'))")
            ->toContain("'App\\\\Core\\\\Domain'")
            ->and(buildArchClassSets())->toBe("    \$config->add(ClassSet::fromDir(__DIR__.'/app'), ...\$rules);");
    });

    it('scans the root directory once instead of the layers when the outside layers rule is enabled', function () {
        expect(substr_count(buildArchClassSets(), 'ClassSet::fromDir'))->toBe(3);

        config()->set('clean-architecture.validation.rules.no_classes_outside_layers', true);

        expect(buildArchClassSets())->toBe("    \$config->add(ClassSet::fromDir(__DIR__.'/app'), ...\$rules);");
    });

    it('skips the outside layers rule when the layers share no top level directory', function (array $directories) {
        config()->set('clean-architecture.validation.rules.no_classes_outside_layers', true);

        foreach ($directories as $layer => $directory) {
            config()->set("clean-architecture.directories.{$layer}", $directory);
        }

        expect(buildArchRules())->toHaveCount(5)
            ->and(substr_count(buildArchClassSets(), 'ClassSet::fromDir'))->toBe(3);
    })->with([
        'different roots'  => [['domain' => 'src/Domain']],
        'top level layers' => [['domain' => 'Domain', 'application' => 'Application', 'infrastructure' => 'Infrastructure']],
    ]);
});
