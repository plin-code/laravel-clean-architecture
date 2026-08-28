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
