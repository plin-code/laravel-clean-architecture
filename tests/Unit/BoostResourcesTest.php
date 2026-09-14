<?php

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Yaml\Yaml;

describe('Boost guidelines', function () {
    beforeEach(function () {
        $this->guidelines = __DIR__ . '/../../resources/boost/guidelines/core.blade.php';
    });

    it('ships a single core guidelines file', function () {
        expect(file_exists($this->guidelines))->toBeTrue();
    });

    it('renders as blade without leaving directives behind', function () {
        $rendered = Blade::render(file_get_contents($this->guidelines));

        expect($rendered)->not->toContain('@verbatim')
            ->and($rendered)->not->toContain('@endverbatim')
            ->and($rendered)->not->toContain('{{');
    });

    it('names the three layers and the dependency direction', function () {
        $rendered = Blade::render(file_get_contents($this->guidelines));

        expect($rendered)->toContain('App\Domain')
            ->toContain('App\Application')
            ->toContain('App\Infrastructure')
            ->toContain('must not depend on');
    });

    it('names the commands an agent needs to generate code', function () {
        $rendered = Blade::render(file_get_contents($this->guidelines));

        foreach ([
            'clean-arch:install',
            'clean-arch:make-domain',
            'clean-arch:make-action',
            'clean-arch:make-arch-rules',
        ] as $command) {
            expect($rendered)->toContain($command);
        }
    });

    it('names the config keys that change where code is written', function () {
        $rendered = Blade::render(file_get_contents($this->guidelines));

        expect($rendered)->toContain('directories')
            ->toContain('default_namespace')
            ->toContain('generation.model_directory')
            ->toContain('validation.application_infrastructure_allowed');
    });

    it('wraps its code examples in code-snippet tags', function () {
        $rendered = Blade::render(file_get_contents($this->guidelines));

        expect($rendered)->toContain('<code-snippet')
            ->toContain('</code-snippet>');
    });

    it('keeps the guidelines short enough to stay in context', function () {
        $rendered = Blade::render(file_get_contents($this->guidelines));

        expect(strlen($rendered))->toBeLessThan(6000);
    });

    it('suggests laravel boost without requiring it', function () {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'), true);

        expect($composer['suggest']['laravel/boost'] ?? null)->toBeString()
            ->and($composer['require']['laravel/boost'] ?? null)->toBeNull()
            ->and($composer['require-dev']['laravel/boost'] ?? null)->toBeNull();
    });
});

describe('Boost skill', function () {
    beforeEach(function () {
        $this->skill = __DIR__ . '/../../resources/boost/skills/clean-architecture-development/SKILL.md';
    });

    it('ships a skill file in a folder named after the skill', function () {
        expect(file_exists($this->skill))->toBeTrue();
    });

    it('declares the frontmatter boost requires', function () {
        $content = file_get_contents($this->skill);

        expect($content)->toStartWith("---\n");

        preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches);

        $frontmatter = Yaml::parse($matches[1] ?? '');

        expect($frontmatter)->toBeArray()
            ->and($frontmatter['name'] ?? null)->toBe('clean-architecture-development')
            ->and($frontmatter['description'] ?? null)->toBeString()
            ->and($frontmatter['description'] ?? '')->not->toBe('');
    });

    it('tells the agent when to use the skill and how to generate a domain', function () {
        $content = file_get_contents($this->skill);

        expect($content)->toContain('## When to use this skill')
            ->toContain('clean-arch:make-domain')
            ->toContain('clean-arch:make-arch-rules');
    });

    it('does not repeat the guidelines verbatim', function () {
        $guidelines = Blade::render(file_get_contents(
            __DIR__ . '/../../resources/boost/guidelines/core.blade.php'
        ));

        expect(file_get_contents($this->skill))->not->toBe($guidelines);
    });
});
