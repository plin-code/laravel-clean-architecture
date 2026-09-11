<?php

namespace PlinCode\LaravelCleanArchitecture\Concerns;

/**
 * Skip-or-write file logic shared by every generator command.
 *
 * The using class must expose a `$files` Filesystem property. Call
 * `resolveForce()` once at the start of `handle()`, before any file is
 * written, to read the `--force` option into `$force`.
 */
trait WritesFiles
{
    protected bool $force = false;

    /**
     * Read the `--force` option into `$force`.
     */
    protected function resolveForce(): void
    {
        $this->force = (bool) $this->option('force');
    }

    /**
     * Write a file relative to the base path, skipping it when it already
     * exists unless `--force` was passed.
     *
     * Used by commands that write several files: an existing one is skipped,
     * the others are still written, and the command still exits successfully.
     */
    protected function writeFile(string $relativePath, string $content): void
    {
        $this->writePath(base_path($relativePath), $content, $relativePath);
    }

    /**
     * Write a file at an absolute path, skipping it when it already exists
     * unless `--force` was passed.
     */
    protected function writePath(string $path, string $content, string $label): void
    {
        if ($this->files->exists($path) && ! $this->force) {
            $this->info("Skipped: {$label} (already exists, use --force to overwrite)");

            return;
        }

        $this->files->put($path, $content);
        $this->info("Created: {$label}");
    }

    /**
     * Write the single file a command produces, refusing to overwrite an
     * existing one without `--force`.
     *
     * Used by commands that only ever produce one file: instead of skipping
     * silently, nothing is written, an error names the path and `--force`,
     * and the command fails, so an agent or a script notices.
     *
     * @return int `Command::SUCCESS` once written, `Command::FAILURE` when
     *             the file already exists and nothing was written.
     */
    protected function writeOrFail(string $path, string $content, string $label): int
    {
        if ($this->files->exists($path) && ! $this->force) {
            $this->error("{$label} already exists. Run again with --force to overwrite it.");

            return self::FAILURE;
        }

        $this->files->put($path, $content);
        $this->info("Created: {$label}");

        return self::SUCCESS;
    }
}
