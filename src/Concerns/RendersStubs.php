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
}
