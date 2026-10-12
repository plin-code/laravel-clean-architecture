<?php

namespace PlinCode\LaravelCleanArchitecture\Concerns;

use Illuminate\Support\Str;

/**
 * Helpers shared by commands that render stubs before writing files.
 *
 * The using class must expose a `$files` Filesystem property and use
 * `ResolvesArchitectureDirectories`, which provides the layer namespaces.
 *
 * The package ships stubs with optional regions delimited by comment
 * markers, `// {{#name}}` and `// {{/name}}`. A command can keep or drop a
 * region depending on configuration, without keeping a separate stub for
 * every variant.
 */
trait RendersStubs
{
    abstract protected function layerNamespace(string $layer): string;

    abstract protected function modelDirectorySegment(): string;

    /**
     * Read a stub shipped with the package, with the layer namespaces resolved.
     *
     * Stubs refer to the layers through `{{DomainNamespace}}`,
     * `{{ApplicationNamespace}}` and `{{InfrastructureNamespace}}`, so the
     * generated classes follow `directories` and `default_namespace` instead
     * of assuming `App\Domain` and its siblings.
     *
     * They refer to the model's own subfolder through `{{ModelNamespace}}`,
     * which carries its own leading backslash so it resolves cleanly both
     * with a segment configured (`\Models`) and without one (an empty
     * string), never leaving a double or trailing backslash behind.
     *
     * @throws \Exception when the stub does not exist.
     */
    protected function getStub(string $stub): string
    {
        $stubPath = __DIR__ . "/../../stubs/{$stub}.stub";

        if (! $this->files->exists($stubPath)) {
            throw new \Exception("Stub file not found: {$stubPath}");
        }

        $modelSegment = $this->modelDirectorySegment();

        return str_replace(
            ['{{DomainNamespace}}', '{{ApplicationNamespace}}', '{{InfrastructureNamespace}}', '{{ModelNamespace}}'],
            [
                rtrim($this->layerNamespace('domain'), '\\'),
                rtrim($this->layerNamespace('application'), '\\'),
                rtrim($this->layerNamespace('infrastructure'), '\\'),
                $modelSegment === '' ? '' : '\\' . $modelSegment,
            ],
            $this->files->get($stubPath)
        );
    }

    /**
     * Apply the placeholders every domain oriented stub shares.
     */
    protected function replaceDomainPlaceholders(string $content, string $name): string
    {
        return str_replace(
            ['{{DomainName}}', '{{PluralDomainName}}', '{{domainVariable}}'],
            [$name, Str::plural($name), Str::camel($name)],
            $content
        );
    }

    /**
     * Keep or drop a named block delimited by `// {{#name}}` / `// {{/name}}` markers.
     *
     * When `$keep` is true only the two marker lines are removed: the content
     * between them is preserved. When `$keep` is false the markers and
     * everything they wrap are removed, and runs of blank lines left behind
     * are collapsed to a single blank line so the generated file stays tidy.
     *
     * A stub without the requested block is returned unchanged.
     */
    protected function applyOptionalBlock(string $stub, string $name, bool $keep): string
    {
        $startPattern = '// {{#' . $name . '}}';
        $endPattern   = '// {{/' . $name . '}}';

        $lines   = explode("\n", $stub);
        $result  = [];
        $inBlock = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === $startPattern) {
                $inBlock = true;

                continue;
            }

            if ($trimmed === $endPattern && $inBlock) {
                $inBlock = false;

                continue;
            }

            if ($inBlock && ! $keep) {
                continue;
            }

            $result[] = $line;
        }

        $rendered = implode("\n", $result);

        if (! $keep) {
            $rendered = preg_replace('/\n{3,}/', "\n\n", $rendered) ?? $rendered;
        }

        return $rendered;
    }

    /**
     * Replacements driving the optional `BaseService` extension.
     *
     * The import placeholder carries its own newline, so dropping it leaves no
     * blank line behind in the generated file.
     *
     * @return array<string, string>
     */
    protected function baseServiceReplacements(bool $extend): array
    {
        return [
            '{{ServiceBaseImport}}' => $extend ? "use {$this->layerNamespace('application')}Services\\BaseService;\n" : '',
            '{{ServiceExtends}}'    => $extend ? ' extends BaseService' : '',
        ];
    }

    /**
     * Replacements driving the optional `BaseAction` extension.
     *
     * @return array<string, string>
     */
    protected function baseActionReplacements(bool $extend): array
    {
        return [
            '{{ActionBaseImport}}' => $extend ? "use {$this->layerNamespace('application')}Actions\\BaseAction;\n" : '',
            '{{ActionExtends}}'    => $extend ? ' extends BaseAction' : '',
        ];
    }

    /**
     * Whether generated classes should extend their application-layer base class.
     *
     * Driven by the `generation.extend_base_classes` config value (default true)
     * and overridden by the `--no-base` option, which always wins.
     */
    protected function shouldExtendBaseClasses(bool $noBaseOption = false): bool
    {
        return (bool) config('clean-architecture.generation.extend_base_classes', true)
            && ! $noBaseOption;
    }

    /**
     * Prefix `make-job` puts in front of the generated job, in the class name
     * as well as in the file name.
     *
     * Configured through `generation.job_prefix` (default `Process`). A
     * `null` or empty value generates the job under its own name.
     */
    protected function jobPrefix(): string
    {
        $configured = config('clean-architecture.generation.job_prefix', 'Process');

        return is_string($configured) ? trim($configured) : '';
    }

    /**
     * Strip a trailing suffix a given name already carries, so a command
     * that bakes a fixed suffix into its stub or filename (`Mail`, `Action`,
     * `Controller`) does not double it up. `UserMail` stays `UserMail`
     * instead of becoming `UserMailMail`.
     *
     * The comparison is case sensitive, matching the rest of the package's
     * naming conventions, so `usermail` is left untouched.
     */
    protected function stripSuffix(string $name, string $suffix): string
    {
        if ($name === $suffix || ! Str::endsWith($name, $suffix)) {
            return $name;
        }

        return substr($name, 0, -strlen($suffix));
    }

    /**
     * Strip a leading prefix a given name already carries, so a command that
     * bakes a fixed prefix into its stub or filename (`Process`) does not
     * double it up. `ProcessGeocodeStore` stays `GeocodeStore` instead of
     * becoming `ProcessProcessGeocodeStore`.
     *
     * Like `stripSuffix`, the comparison is case sensitive.
     */
    protected function stripPrefix(string $name, string $prefix): string
    {
        if ($name === $prefix || ! Str::startsWith($name, $prefix)) {
            return $name;
        }

        return substr($name, strlen($prefix));
    }
}
