<?php

namespace PlinCode\LaravelCleanArchitecture\Concerns;

/**
 * Helpers shared by commands that render stubs before writing files.
 *
 * The package ships stubs with optional regions delimited by comment
 * markers, `// {{#name}}` and `// {{/name}}`. A command can keep or drop a
 * region depending on configuration, without keeping a separate stub for
 * every variant.
 */
trait RendersStubs
{
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
            '{{ServiceBaseImport}}' => $extend ? "use App\\Application\\Services\\BaseService;\n" : '',
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
            '{{ActionBaseImport}}' => $extend ? "use App\\Application\\Actions\\BaseAction;\n" : '',
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
}
