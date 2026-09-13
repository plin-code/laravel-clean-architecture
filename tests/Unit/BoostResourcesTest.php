<?php

use Illuminate\Support\Facades\Blade;

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
});
