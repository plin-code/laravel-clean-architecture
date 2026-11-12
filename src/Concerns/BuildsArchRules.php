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
     * Provided by ResolvesArchitectureDirectories.
     */
    abstract protected function directoryNamespace(string $directory): string;

    /**
     * Rules left out of the generated file unless the config turns them on.
     *
     * @var array<int, string>
     */
    protected array $optInArchRules = ['no_commands_in_infrastructure', 'no_classes_outside_layers'];

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
                "new NotDependsOnTheseNamespaces([{$this->archLiteral($application)}])",
                'the domain layer must not depend on the application layer',
            ],
            'domain_no_infrastructure_imports' => [
                $domain,
                "new NotDependsOnTheseNamespaces([{$this->archLiteral($infrastructure)}])",
                'the domain layer must not depend on the infrastructure layer',
            ],
            'application_no_infrastructure_imports' => [
                $application,
                $this->applicationInfrastructureExpression($infrastructure),
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
                // Queued mail and notifications implement ShouldQueue too, and they live in infrastructure.
                ['Illuminate\\Mail\\Mailable', 'Illuminate\\Notifications\\Notification'],
            ],
            'no_commands_in_infrastructure' => [
                $infrastructure,
                "new IsNotA({$this->archLiteral('Illuminate\\Console\\Command')})",
                'this project keeps console commands in the application layer',
            ],
        ];

        $rules = [];

        foreach ($definitions as $rule => $definition) {
            if (! $this->archRuleEnabled($rule)) {
                continue;
            }

            [$namespace, $expression, $because, $except] = $definition + [3 => []];

            $rules[] = $this->archRule($namespace, $expression, $because, $except);
        }

        $root = $this->archRootDirectory();

        if ($this->archRuleEnabled('no_classes_outside_layers') && $root !== null) {
            $rootNamespace = rtrim($this->directoryNamespace($root), '\\');

            $rules[] = $this->archRule(
                $rootNamespace,
                $this->outsideLayersExpression($rootNamespace, [$domain, $application, $infrastructure]),
                'every class of the application belongs to one of the three layers'
            );
        }

        return $rules;
    }

    /**
     * The directory the three layers share, the first segment of their paths
     * (`app` with the defaults), or null when they do not share one.
     *
     * Only the top level directory is used, not the closest common parent, so
     * layers configured under `app/Core` still have `app/Http` and the other
     * Laravel folders checked.
     */
    protected function archRootDirectory(): ?string
    {
        $roots = [];

        foreach (['domain', 'application', 'infrastructure'] as $layer) {
            $segments = explode('/', $this->layerDirectory($layer));

            // A layer at the top of the project would make the base path, vendor included, the root.
            if (count($segments) < 2) {
                return null;
            }

            $roots[$segments[0]] = true;
        }

        return count($roots) === 1 ? (string) array_key_first($roots) : null;
    }

    /**
     * Why 'no_classes_outside_layers' cannot be generated, or null when it can.
     */
    protected function rootDirectoryError(): ?string
    {
        if (! $this->archRuleEnabled('no_classes_outside_layers') || $this->archRootDirectory() !== null) {
            return null;
        }

        $directories = implode(', ', array_map($this->layerDirectory(...), ['domain', 'application', 'infrastructure']));

        return "validation.rules.no_classes_outside_layers needs the three layer directories inside one top level directory, such as app/, got {$directories}. "
            . 'Move the layers under one directory or turn the rule off.';
    }

    /**
     * The layers, followed by the allowed namespaces, as the places a class
     * of the root namespace may reside in.
     *
     * @param  array<int, string>  $layers
     */
    protected function outsideLayersExpression(string $root, array $layers): string
    {
        /** @var array<int, string> $segments */
        $segments = config('clean-architecture.validation.outside_layers_allowed', ['Providers']);
        $allowed  = array_map(
            fn (string $segment): string => $root . '\\' . trim(str_replace('/', '\\', $segment), '\\'),
            $segments
        );

        return 'new ResideInOneOfTheseNamespaces(' . implode(', ', array_map($this->archLiteral(...), [...$layers, ...$allowed])) . ')';
    }

    /**
     * The `$config->add()` calls, one class set per configured layer directory.
     *
     * Scanning the layers instead of the whole application keeps projects that
     * moved the directories working, and leaves the rest of the codebase out of
     * the scan. With 'no_classes_outside_layers' on, the directory the layers
     * share is scanned instead, once, so no class is checked twice.
     */
    protected function archClassSets(): string
    {
        $root = $this->archRootDirectory();

        // The layers sit inside the root, so scanning it alone checks every class once.
        if ($this->archRuleEnabled('no_classes_outside_layers') && $root !== null) {
            return "    \$config->add(ClassSet::fromDir(__DIR__.'/{$root}'), ...\$rules);";
        }

        $calls = [];

        foreach (['domain', 'application', 'infrastructure'] as $layer) {
            $directory = $this->layerDirectory($layer);

            $calls[] = "    \$config->add(ClassSet::fromDir(__DIR__.'/{$directory}'), ...\$rules);";
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
        return <<<'PHP'
                // The IsNotA rules below are reflection based. With autoloading broken they
                // report nothing and phparkitect exits 0, which is indistinguishable from a
                // pass. Fail here instead. Change the BaseModel import above if you renamed
                // or removed that class.
                if (! class_exists(Command::class) || ! class_exists(BaseModel::class)) {
                    throw new RuntimeException(
                        'clean-architecture: autoloading does not resolve the application classes, '
                        .'so the reflection based rules would pass silently. Run composer dump-autoload.'
                    );
                }
            PHP;
    }

    /**
     * The dependency expression of the application rule, with the allowed
     * infrastructure namespaces passed as phparkitect's exclude list.
     *
     * The second argument is only written when the allowlist has entries, so
     * projects that do not use it keep the exact file they had before.
     */
    protected function applicationInfrastructureExpression(string $infrastructure): string
    {
        $allowed = $this->allowedInfrastructureNamespaces($infrastructure);

        if ($allowed === []) {
            return "new NotDependsOnTheseNamespaces([{$this->archLiteral($infrastructure)}])";
        }

        $exclude = implode(', ', array_map($this->archLiteral(...), $allowed));

        return "new NotDependsOnTheseNamespaces([{$this->archLiteral($infrastructure)}], [{$exclude}])";
    }

    /**
     * Full namespaces the application layer may import from the infrastructure
     * layer. The config lists segments relative to that layer, so 'Mail' keeps
     * pointing at the right place when the directories or the root namespace
     * change.
     *
     * @return array<int, string>
     */
    protected function allowedInfrastructureNamespaces(string $infrastructure): array
    {
        // A disabled rule ignores the allowlist, so an invalid value must not break the other rules.
        if (! $this->archRuleEnabled('application_no_infrastructure_imports')) {
            return [];
        }

        /** @var array<int, string> $segments */
        $segments = config('clean-architecture.validation.application_infrastructure_allowed', []);

        return array_values(array_map(
            fn (string $segment): string => $infrastructure . '\\' . trim(str_replace('/', '\\', $segment), '\\'),
            $segments
        ));
    }

    /**
     * Why an allowlist cannot be used, or null when it can.
     *
     * Invalid entries are reported rather than dropped: skipping one silently
     * would flag classes the user believes are allowed, and an entry that
     * trims to nothing would allow the whole layer or root namespace.
     */
    protected function allowlistError(): ?string
    {
        $allowlists = [
            'application_no_infrastructure_imports' => ['application_infrastructure_allowed', [], 'infrastructure namespace segments', 'Mail'],
            'no_classes_outside_layers'             => ['outside_layers_allowed', ['Providers'], 'namespace segments relative to the root namespace', 'Providers'],
        ];

        foreach ($allowlists as $rule => [$name, $default, $description, $example]) {
            if (! $this->archRuleEnabled($rule)) {
                continue;
            }

            $key     = "validation.{$name}";
            $allowed = config("clean-architecture.{$key}", $default);

            if (! is_array($allowed)) {
                return "{$key} must be a list of {$description}, got " . var_export($allowed, true) . '.';
            }

            foreach ($allowed as $segment) {
                if (! is_string($segment) || trim(str_replace('/', '\\', $segment), '\\') === '') {
                    return "{$key} contains an invalid entry " . var_export($segment, true) . ", expected a non empty namespace segment such as '{$example}'.";
                }
            }
        }

        return null;
    }

    /**
     * A rule is enabled unless the config says otherwise, so an unpublished
     * config still generates the default set. The rules in $optInArchRules
     * are the exception and have to be turned on explicitly.
     */
    protected function archRuleEnabled(string $rule): bool
    {
        // The package does not merge its config, so the opt-in rules need their default here too.
        $default = ! in_array($rule, $this->optInArchRules, true);

        return config("clean-architecture.validation.rules.{$rule}", $default) !== false;
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
     * of the closure in the generated file. Classes that are one of $except are
     * left out of the rule.
     *
     * @param  array<int, string>  $except
     */
    protected function archRule(string $namespace, string $expression, string $because, array $except = []): string
    {
        $exclusions = implode('', array_map(
            fn (string $class): string => "\n        ->andThat(new IsNotA({$this->archLiteral($class)}))",
            $except
        ));

        return <<<PHP
                \$rules[] = Rule::allClasses()
                    ->that(new ResideInOneOfTheseNamespaces({$this->archLiteral($namespace)})){$exclusions}
                    ->should({$expression})
                    ->because('{$because}');
            PHP;
    }
}
