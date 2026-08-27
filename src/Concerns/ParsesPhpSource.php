<?php

namespace PlinCode\LaravelCleanArchitecture\Concerns;

/**
 * Minimal PHP source analysis built on token_get_all().
 *
 * It is intentionally limited to what the architecture checks need: the real
 * namespace imports of a file and the resolved parent class / interfaces of the
 * types it declares. No reflection and no autoloading are involved, so files
 * belonging to the host application never get executed.
 */
trait ParsesPhpSource
{
    /**
     * Tokens that carry a piece of a (possibly qualified) name.
     *
     * @var list<int>
     */
    protected array $nameTokens = [
        T_STRING,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
        T_NS_SEPARATOR,
    ];

    /**
     * Tokens carrying no semantic value for this analysis.
     *
     * @var list<int>
     */
    protected array $insignificantTokens = [
        T_WHITESPACE,
        T_COMMENT,
        T_DOC_COMMENT,
    ];

    /**
     * Extract the namespace imports of a file.
     *
     * Only top level `use` statements are returned, so trait imports inside a
     * class body, closure `use` clauses, commented out lines and occurrences
     * inside strings are all ignored. Grouped imports and aliases are expanded.
     *
     * @return list<array{name: string, alias: string, line: int}>
     */
    protected function extractUseStatements(string $contents): array
    {
        $tokens     = token_get_all($contents);
        $statements = [];
        $depth      = 0;
        $previous   = null;

        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;
                }

                $previous = $token;

                continue;
            }

            if (in_array($token[0], $this->insignificantTokens, true)) {
                continue;
            }

            if ($token[0] === T_CURLY_OPEN || $token[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
                $previous = $token;

                continue;
            }

            // A `use` keyword preceded by `)` is a closure binding, not an import.
            if ($token[0] === T_USE && $depth === 0 && $previous !== ')') {
                $i        = $this->collectUseStatement($tokens, $i, $statements);
                $previous = ';';

                continue;
            }

            $previous = $token;
        }

        return $statements;
    }

    /**
     * Resolve the parent class and the interfaces of the types declared in a file.
     *
     * @return array{extends: list<string>, implements: list<string>}
     */
    protected function parseClassSignature(string $contents): array
    {
        $imports = [];

        foreach ($this->extractUseStatements($contents) as $statement) {
            $imports[strtolower($statement['alias'])] = $statement['name'];
        }

        $tokens     = token_get_all($contents);
        $namespace  = '';
        $extends    = [];
        $implements = [];

        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                [$declared, $i] = $this->readName($tokens, $i + 1);
                $namespace      = trim($declared, '\\');

                continue;
            }

            if ($token[0] !== T_EXTENDS && $token[0] !== T_IMPLEMENTS) {
                continue;
            }

            $target = $token[0] === T_EXTENDS ? 'extends' : 'implements';

            for ($j = $i + 1; $j < $count; $j++) {
                $next = $tokens[$j];

                if (is_string($next)) {
                    if ($next === ',') {
                        continue;
                    }

                    break;
                }

                if (in_array($next[0], $this->insignificantTokens, true)) {
                    continue;
                }

                if ($next[0] === T_IMPLEMENTS) {
                    $target = 'implements';

                    continue;
                }

                if (! in_array($next[0], $this->nameTokens, true)) {
                    break;
                }

                [$name, $j] = $this->readName($tokens, $j);
                $resolved   = $this->resolveName($name, $namespace, $imports);

                if ($target === 'extends') {
                    $extends[] = $resolved;
                } else {
                    $implements[] = $resolved;
                }
            }

            $i = $j;
        }

        return ['extends' => $extends, 'implements' => $implements];
    }

    /**
     * Read the names declared by a single `use` statement.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     * @param  list<array{name: string, alias: string, line: int}>  $statements
     * @return int Index of the token closing the statement.
     */
    protected function collectUseStatement(array $tokens, int $index, array &$statements): int
    {
        $prefix    = '';
        $current   = '';
        $alias     = '';
        $line      = is_array($tokens[$index]) ? $tokens[$index][2] : 1;
        $readAlias = false;

        for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === '{') {
                    $prefix  = $current;
                    $current = '';

                    continue;
                }

                if ($token === ',' || $token === '}' || $token === ';') {
                    $this->pushUseStatement($statements, $prefix . $current, $alias, $line);
                    $current   = '';
                    $alias     = '';
                    $readAlias = false;

                    if ($token === '}') {
                        $prefix = '';
                    }

                    if ($token === ';') {
                        return $i;
                    }

                    continue;
                }

                continue;
            }

            if (in_array($token[0], $this->insignificantTokens, true)) {
                continue;
            }

            if ($token[0] === T_AS) {
                $readAlias = true;

                continue;
            }

            if (! in_array($token[0], $this->nameTokens, true)) {
                continue;
            }

            if ($readAlias) {
                $alias = $token[1];

                continue;
            }

            if ($current === '') {
                $line = $token[2];
            }

            $current .= $token[1];
        }

        return $count - 1;
    }

    /**
     * @param  list<array{name: string, alias: string, line: int}>  $statements
     */
    protected function pushUseStatement(array &$statements, string $name, string $alias, int $line): void
    {
        $name = trim($name, '\\');

        if ($name === '') {
            return;
        }

        $segments = explode('\\', $name);

        $statements[] = [
            'name'  => $name,
            'alias' => $alias !== '' ? $alias : (string) end($segments),
            'line'  => $line,
        ];
    }

    /**
     * Read a (possibly qualified) name starting at the given index.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     * @return array{0: string, 1: int} The name and the index of its last token.
     */
    protected function readName(array $tokens, int $index): array
    {
        $name = '';
        $last = $index;

        for ($i = $index, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                break;
            }

            if ($name === '' && in_array($token[0], $this->insignificantTokens, true)) {
                continue;
            }

            if (! in_array($token[0], $this->nameTokens, true)) {
                break;
            }

            $name .= $token[1];
            $last = $i;
        }

        return [rtrim($name, '\\'), $last];
    }

    /**
     * Turn a name written in a class declaration into a fully qualified one.
     *
     * @param  array<string, string>  $imports
     */
    protected function resolveName(string $name, string $namespace, array $imports): string
    {
        $segments = explode('\\', ltrim($name, '\\'));
        $first    = strtolower($segments[0]);

        if (str_starts_with($name, '\\')) {
            return implode('\\', $segments);
        }

        if (isset($imports[$first])) {
            $segments[0] = $imports[$first];

            return implode('\\', $segments);
        }

        return $namespace !== '' ? $namespace . '\\' . implode('\\', $segments) : implode('\\', $segments);
    }
}
