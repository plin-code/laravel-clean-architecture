<?php

namespace PlinCode\LaravelCleanArchitecture\Concerns;

/**
 * Translate the rules of config/clean-architecture.php into phparkitect
 * expressions, as lines of PHP source.
 *
 * The package does not run phparkitect, it writes a configuration file that
 * the application runs on its own. Keeping the text generation here, away from
 * the filesystem, makes it testable with plain string assertions.
 *
 * @see ResolvesArchitectureDirectories
 */
trait BuildsArchRules
{
    /**
     * Provided by ResolvesArchitectureDirectories.
     */
    abstract protected function layerNamespace(string $layer): string;

    /**
     * Provided by ResolvesArchitectureDirectories.
     */
    abstract protected function layerDirectory(string $layer): string;

    /**
     * One entry per enabled rule, each a complete `$rules[] = ...;` statement.
     *
     * @return array<int, string>
     */
    protected function archRules(): array
    {
        $domain         = $this->archNamespace('domain');
        $application    = $this->archNamespace('application');
        $infrastructure = $this->archNamespace('infrastructure');

        $definitions = [
            'domain_no_application_imports' => [
                $domain,
                "new NotDependsOnTheseNamespaces({$this->archLiteral($application)})",
                'the domain layer must not depend on the application layer',
            ],
            'domain_no_infrastructure_imports' => [
                $domain,
                "new NotDependsOnTheseNamespaces({$this->archLiteral($infrastructure)})",
                'the domain layer must not depend on the infrastructure layer',
            ],
            'application_no_infrastructure_imports' => [
                $application,
                "new NotDependsOnTheseNamespaces({$this->archLiteral($infrastructure)})",
                'the application layer must not depend on the infrastructure layer',
            ],
            'no_observers_in_domain' => [
                $domain,
                "new NotHaveNameMatching('*Observer')",
                'observers react to framework events and belong to the infrastructure layer',
            ],
            'no_jobs_in_infrastructure' => [
                $infrastructure,
                "new IsNotA({$this->archLiteral('Illuminate\\Contracts\\Queue\\ShouldQueue')})",
                'queued jobs orchestrate use cases and belong to the application layer',
            ],
            'no_commands_in_infrastructure' => [
                $infrastructure,
                "new IsNotA({$this->archLiteral('Illuminate\\Console\\Command')})",
                'console commands orchestrate use cases and belong to the application layer',
            ],
        ];

        $rules = [];

        foreach ($definitions as $rule => [$namespace, $expression, $because]) {
            if (! $this->archRuleEnabled($rule)) {
                continue;
            }

            $rules[] = $this->archRule($namespace, $expression, $because);
        }

        return $rules;
    }

    /**
     * The `$config->add()` calls, one class set per configured layer directory.
     *
     * Scanning the layers instead of the whole application keeps projects that
     * moved the directories working, and leaves the rest of the codebase out of
     * the scan.
     */
    protected function archClassSets(): string
    {
        $calls = [];

        foreach (['domain', 'application', 'infrastructure'] as $layer) {
            $directory = $this->layerDirectory($layer);

            $calls[] = "    \$config->add(ClassSet::fromDir(__DIR__ . '/{$directory}'), ...\$rules);";
        }

        return implode("\n", $calls);
    }

    /**
     * The block that stops phparkitect when autoloading does not resolve.
     *
     * The IsNotA expressions are reflection based. When a class cannot be
     * loaded, is_a() with allow_string returns false instead of raising, so
     * every rule reports nothing and phparkitect exits 0, which in CI reads as
     * a success. The guard turns that into a loud failure before the scan.
     *
     * It names an application class on purpose. If that class is renamed the
     * guard fires with a sound autoloader, which is a false alarm, and the
     * comment in the generated file says how to change the target. A false
     * alarm is noisy, a silent pass is not.
     */
    protected function autoloadGuard(): string
    {
        $target = $this->layerNamespace('domain') . 'Shared\\BaseModel';

        return <<<PHP
                // The IsNotA rules below are reflection based. With autoloading broken they
                // report nothing and phparkitect exits 0, which is indistinguishable from a
                // pass. Fail here instead. Change the application class named below if you
                // renamed or removed it.
                if (! class_exists(\\Illuminate\\Console\\Command::class)
                    || ! class_exists(\\{$target}::class)) {
                    throw new RuntimeException(
                        'clean-architecture: autoloading does not resolve the application classes, '
                        . 'so the reflection based rules would pass silently. Run composer dump-autoload.'
                    );
                }
            PHP;
    }

    /**
     * A rule is enabled unless the config says otherwise, so an unpublished
     * config still generates the full set.
     */
    protected function archRuleEnabled(string $rule): bool
    {
        return config("clean-architecture.validation.rules.{$rule}", true) !== false;
    }

    /**
     * Namespace of a layer without the trailing separator, as phparkitect
     * expects it.
     */
    protected function archNamespace(string $layer): string
    {
        return rtrim($this->layerNamespace($layer), '\\');
    }

    /**
     * A single quoted PHP literal, with the backslashes of a FQCN escaped.
     */
    protected function archLiteral(string $value): string
    {
        return var_export($value, true);
    }

    /**
     * One `$rules[] = Rule::allClasses()...;` statement, indented for the body
     * of the closure in the generated file.
     */
    protected function archRule(string $namespace, string $expression, string $because): string
    {
        return <<<PHP
                \$rules[] = Rule::allClasses()
                    ->that(new ResideInOneOfTheseNamespaces({$this->archLiteral($namespace)}))
                    ->should({$expression})
                    ->because('{$because}');
            PHP;
    }
}
