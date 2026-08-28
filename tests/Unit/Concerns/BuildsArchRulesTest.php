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

    it('forbids console commands in the infrastructure layer', function () {
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
        config()->set('clean-architecture.validation.rules.no_commands_in_infrastructure', false);

        $rules = buildArchRules();

        expect($rules)->toHaveCount(5)
            ->and(implode("\n", $rules))->not->toContain('Illuminate\\\\Console\\\\Command');
    });

    it('emits an autoload guard naming a framework class and an application class', function () {
        expect(buildAutoloadGuard())
            ->toContain('class_exists(\\Illuminate\\Console\\Command::class)')
            ->toContain('App\\Domain\\Shared\\BaseModel')
            ->toContain('throw new RuntimeException');
    });

    it('points the guard at the configured domain namespace', function () {
        config()->set('clean-architecture.directories.domain', 'app/Core/Domain');
        config()->set('clean-architecture.default_namespace', 'Acme');

        expect(buildAutoloadGuard())->toContain('Acme\\Core\\Domain\\Shared\\BaseModel');
    });

    it('explains in a comment why the guard exists and how to change it', function () {
        expect(buildAutoloadGuard())
            ->toContain('// ')
            ->toContain('exits 0');
    });

    it('follows the configured namespace and directories', function () {
        config()->set('clean-architecture.default_namespace', 'Acme');
        config()->set('clean-architecture.directories.domain', 'app/Core/Domain');

        expect(buildArchRules()[0])
            ->toContain("new ResideInOneOfTheseNamespaces('Acme\\\\Core\\\\Domain')");
    });
});
