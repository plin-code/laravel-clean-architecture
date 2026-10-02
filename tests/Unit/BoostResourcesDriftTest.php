<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

/*
| Every identifier the Boost guideline and skill mention is extracted from the
| text and checked against the code, so renaming a command, an option or a
| config key fails here instead of leaving the agent instructions stale.
*/

function boostDocuments(): array
{
    $root = __DIR__ . '/../../resources/boost';

    return [
        'guidelines' => Blade::render(file_get_contents($root . '/guidelines/core.blade.php')),
        'skill'      => file_get_contents($root . '/skills/clean-architecture-development/SKILL.md'),
    ];
}

function boostPackageConfig(): array
{
    return require __DIR__ . '/../../config/clean-architecture.php';
}

/** @return array<string, Command> */
function boostPackageCommands(): array
{
    return array_filter(
        Artisan::all(),
        fn (string $name) => str_starts_with($name, 'clean-arch:'),
        ARRAY_FILTER_USE_KEY,
    );
}

/** Spans written between backticks, trimmed. */
function boostBacktickSpans(string $text): array
{
    preg_match_all('/`([^`\n]+)`/', $text, $matches);

    return array_values(array_unique(array_map('trim', $matches[1])));
}

/**
 * Commands a fragment of text refers to: full `clean-arch:*` names, and the
 * short forms the prose uses in lists (make-action, generate-package, a
 * backticked `install`).
 */
function boostCommandsIn(string $text): array
{
    preg_match_all('/clean-arch:[a-z]+(?:-[a-z]+)*/', $text, $full);
    preg_match_all('/(?<![\w:\/-])(make-[a-z]+(?:-[a-z]+)*|generate-package)(?![\w-])/', $text, $short);
    preg_match_all('/`(install)`/', $text, $install);

    return array_values(array_unique(array_merge(
        $full[0],
        array_map(fn (string $name) => 'clean-arch:' . $name, array_merge($short[1], $install[1])),
    )));
}

function boostCommandHasOption(Command $command, string $option): bool
{
    return $command->getDefinition()->hasOption($option)
        || $command->getApplication()?->getDefinition()->hasOption($option);
}

/** Config keys at every depth, dotted, plus their last segment alone. */
function boostConfigKeys(): array
{
    $keys = [];

    $walk = function (array $config, string $prefix) use (&$walk, &$keys): void {
        foreach ($config as $key => $value) {
            $keys[$prefix . $key] = true;

            if (is_array($value) && ! array_is_list($value)) {
                $walk($value, $prefix . $key . '.');
            }
        }
    };

    $walk(boostPackageConfig(), '');

    return $keys;
}

function boostIsConfigKey(string $token): bool
{
    $keys = boostConfigKeys();

    if (str_contains($token, '.')) {
        return isset($keys[$token]);
    }

    foreach (array_keys($keys) as $key) {
        if ($key === $token || str_ends_with($key, '.' . $token)) {
            return true;
        }
    }

    return false;
}

dataset('boost documents', ['guidelines', 'skill']);

describe('Boost resources drift', function () {
    it('only names clean-arch commands that are registered', function (string $document) {
        $text     = boostDocuments()[$document];
        $commands = boostCommandsIn($text);

        expect($commands)->not->toBeEmpty();

        foreach ($commands as $name) {
            expect(array_key_exists($name, boostPackageCommands()))->toBeTrue("The text names {$name}, which is not registered.");
        }
    })->with('boost documents');

    it('only passes options that the command defines', function (string $document) {
        $text     = boostDocuments()[$document];
        $commands = boostPackageCommands();
        $checked  = 0;

        foreach (preg_split('/^#+ /m', $text) as $section) {
            $previous = [];

            foreach (preg_split('/\n\s*\n/', $section) as $paragraph) {
                $mentioned = boostCommandsIn($paragraph);
                $unbound   = [];

                foreach (explode("\n", $paragraph) as $line) {
                    // Options of other tools, such as composer require --dev.
                    if (preg_match('#^\s*(?:composer|vendor/bin/|npm|npx|git) #', $line)) {
                        continue;
                    }

                    // An option written after a command on the same line belongs to it.
                    preg_match_all('/clean-arch:[a-z]+(?:-[a-z]+)*|(?<![\w-])--[a-z]+(?:-[a-z]+)*/', $line, $tokens);
                    $current = null;

                    foreach ($tokens[0] as $token) {
                        if (! str_starts_with($token, '--')) {
                            $current = $token;

                            continue;
                        }

                        if ($current === null) {
                            $unbound[] = $token;

                            continue;
                        }

                        expect(array_key_exists($current, $commands))->toBeTrue("$current is not registered.");
                        expect(boostCommandHasOption($commands[$current], substr($token, 2)))
                            ->toBeTrue("{$current} has no {$token} option.");
                        $checked++;
                    }
                }

                // A loose option belongs to the commands of its paragraph, or of
                // the closest paragraph above it in the same section.
                $owners = $mentioned !== [] ? $mentioned : $previous;

                foreach (array_unique($unbound) as $option) {
                    if ($owners === []) {
                        $defined = array_filter(
                            $commands,
                            fn (Command $command) => boostCommandHasOption($command, substr($option, 2)),
                        );

                        expect($defined)->not->toBeEmpty("No clean-arch command has a {$option} option.");
                    }

                    foreach ($owners as $owner) {
                        expect(array_key_exists($owner, $commands))->toBeTrue("$owner is not registered.");
                        expect(boostCommandHasOption($commands[$owner], substr($option, 2)))
                            ->toBeTrue("The text pairs {$option} with {$owner}, which does not define it.");
                    }

                    $checked++;
                }

                if ($mentioned !== []) {
                    $previous = $mentioned;
                }
            }
        }

        expect($checked)->toBeGreaterThan(0);
    })->with('boost documents');

    it('passes as many arguments as the command accepts in its examples', function (string $document) {
        $text     = boostDocuments()[$document];
        $commands = boostPackageCommands();

        preg_match_all('/php artisan (clean-arch:[a-z-]+)((?: +[^\s-][^\s]*)*)/', $text, $matches, PREG_SET_ORDER);

        expect($matches)->not->toBeEmpty();

        foreach ($matches as [$line, $name, $arguments]) {
            expect(array_key_exists($name, $commands))->toBeTrue("{$name} is not registered.");

            $definition = $commands[$name]->getDefinition();
            $count      = count(preg_split('/\s+/', trim($arguments), -1, PREG_SPLIT_NO_EMPTY));

            expect($count)
                ->toBeGreaterThanOrEqual($definition->getArgumentRequiredCount(), "Too few arguments in: {$line}")
                ->toBeLessThanOrEqual($definition->getArgumentCount(), "Too many arguments in: {$line}");
        }
    })->with('boost documents');

    it('only names config keys that exist', function (string $document) {
        $text = boostDocuments()[$document];
        $keys = [];

        foreach (boostBacktickSpans($text) as $span) {
            // snake_case or dotted spans are config keys: default_namespace, validation.rules.
            if (! str_ends_with($span, '.php') && preg_match('/^[a-z]+(?:_[a-z]+)*(?:\.[a-z]+(?:_[a-z]+)*)*$/', $span) && preg_match('/[_.]/', $span)) {
                $keys[] = $span;
            }
        }

        // Keys written in the PHP config examples.
        preg_match_all("/'([a-z]+(?:_[a-z]+)*)'\s*=>/", $text, $snippetKeys);
        $keys = array_values(array_unique(array_merge($keys, $snippetKeys[1])));

        expect($keys)->not->toBeEmpty();

        foreach ($keys as $key) {
            expect(boostIsConfigKey($key))->toBeTrue("The text names config key {$key}, which config/clean-architecture.php does not define.");
        }
    })->with('boost documents');

    it('only uses bare backticked words that the package defines', function (string $document) {
        $text = boostDocuments()[$document];
        // Words that belong to phparkitect, not to this package.
        $external = ['check'];

        foreach (boostBacktickSpans($text) as $span) {
            if (! preg_match('/^[a-z]+$/', $span) || in_array($span, $external, true)) {
                continue;
            }

            $known = boostIsConfigKey($span) || array_key_exists('clean-arch:' . $span, boostPackageCommands());

            expect($known)->toBeTrue("`{$span}` is neither a config key nor a clean-arch command.");
        }
    })->with('boost documents');

    it('quotes the config defaults correctly', function (string $document) {
        $text   = boostDocuments()[$document];
        $config = boostPackageConfig();

        preg_match_all('/`([a-z_.]+)`:[^`]*?`([^`]+)`\s+by\s+default/', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as [, $key, $value]) {
            expect(Arr::get($config, $key))->toBe($value, "The text says {$key} defaults to {$value}.");
        }

        expect(true)->toBeTrue();
    })->with('boost documents');

    it('counts the validation rules correctly', function (string $document) {
        $text    = boostDocuments()[$document];
        $numbers = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10];
        $rules   = boostPackageConfig()['validation']['rules'];

        preg_match_all('/\bthe (' . implode('|', array_keys($numbers)) . ') rules\b/', $text, $matches);

        foreach ($matches[1] as $word) {
            expect(count($rules))->toBe($numbers[$word], "The text says {$word} rules.");
        }

        expect(true)->toBeTrue();
    })->with('boost documents');

    it('only names layer paths and namespaces that the config produces', function (string $document) {
        $text        = boostDocuments()[$document];
        $config      = boostPackageConfig();
        $directories = $config['directories'];
        $namespace   = $config['default_namespace'];
        $layers      = array_map(fn (string $path) => Str::studly(basename($path)), $directories);

        foreach (boostBacktickSpans($text) as $span) {
            if (preg_match('#^[a-z]+(?:/[A-Za-z]+)+$#', $span)) {
                expect(in_array($span, $directories, true))->toBeTrue("{$span} is not a configured layer directory.");
            }
        }

        preg_match_all('/\b([A-Z]\w*)\\\\([A-Z]\w*)/', $text, $matches, PREG_SET_ORDER);

        expect($matches)->not->toBeEmpty();

        foreach ($matches as [$reference, $root, $segment]) {
            if ($root !== $namespace) {
                continue;
            }

            // App\Models is where Laravel keeps User until --user-in-domain moves it.
            expect(in_array($segment, [...array_values($layers), 'Models'], true))
                ->toBeTrue("{$reference} is not a layer namespace derived from the config.");
        }
    })->with('boost documents');

    it('only names package classes and files that exist', function (string $document) {
        $text = boostDocuments()[$document];
        $root = __DIR__ . '/../../';

        preg_match_all('/PlinCode\\\\LaravelCleanArchitecture(?:\\\\\w+)*/', $text, $classes);

        foreach (array_unique($classes[0]) as $class) {
            $exists = class_exists($class) || interface_exists($class) || trait_exists($class)
                || is_dir($root . 'src/' . str_replace('\\', '/', Str::after($class, 'PlinCode\\LaravelCleanArchitecture\\')));

            expect($exists)->toBeTrue("{$class} does not exist in the package.");
        }

        $sources = implode("\n", array_map('file_get_contents', glob($root . 'src/{,*/}*.php', GLOB_BRACE)));

        foreach (boostBacktickSpans($text) as $span) {
            if (str_ends_with($span, '.php')) {
                // Either a file the package ships, or one its commands write.
                expect(file_exists($root . $span) || str_contains($sources, "'{$span}'"))
                    ->toBeTrue("{$span} is neither shipped nor written by the package.");
            }
        }
    })->with('boost documents');
});
