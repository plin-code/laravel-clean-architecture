<?php

describe('Stub Files', function () {
    beforeEach(function () {
        $this->stubsPath = __DIR__ . '/../../stubs';
    });

    it('has core stub files', function () {
        $coreStubs = [
            'action.stub',
            'base-action.stub',
            'base-controller.stub',
            'base-model.stub',
            'base-request.stub',
            'base-service.stub',
            'controller.stub',
            'domain-model.stub',
            'request.stub',
            'service.stub',
            'web-controller.stub',
        ];

        foreach ($coreStubs as $stub) {
            expect(file_exists($this->stubsPath . '/' . $stub))->toBeTrue()
                ->and("Stub file {$stub} should exist");
        }
    });

    it('PHP stub files contain valid PHP opening tags', function () {
        $phpStubs = [
            'action.stub',
            'base-action.stub',
            'base-controller.stub',
            'base-model.stub',
            'controller.stub',
            'domain-model.stub',
            'request.stub',
            'service.stub',
            'web-controller.stub',
        ];

        foreach ($phpStubs as $stubFile) {
            $content = file_get_contents($this->stubsPath . '/' . $stubFile);

            expect($content)->toContain('<?php')
                ->and("Stub {$stubFile} should contain valid PHP opening tag");
        }
    });

    it('action stub contains required content', function () {
        $content = file_get_contents($this->stubsPath . '/action.stub');

        expect($content)
            ->toContain('namespace App\Application\Actions\{{PluralDomainName}};')
            ->toContain('class {{ActionName}} extends BaseAction')
            ->toContain('public function execute');
    });

    it('controller stub contains required structure', function () {
        $content = file_get_contents($this->stubsPath . '/controller.stub');

        expect($content)
            ->toContain('namespace App\Infrastructure\API\Controllers;')
            ->toContain('class {{PluralDomainName}}Controller extends Controller');
    });

    it('service stub contains required structure', function () {
        $content = file_get_contents($this->stubsPath . '/service.stub');

        expect($content)
            ->toContain('namespace App\Application\Services;')
            ->toContain('class {{DomainName}}Service extends BaseService');
    });

    it('domain model stub contains required content', function () {
        $content = file_get_contents($this->stubsPath . '/domain-model.stub');

        expect($content)
            ->toContain('namespace App\Domain\{{PluralDomainName}};')
            ->toContain('class {{DomainName}} extends BaseModel')
            ->toContain('protected $table = \'{{domain-table}}\';')
            ->toContain('protected $fillable')
            ->toContain('protected $casts')
            ->toContain('protected $dispatchesEvents');
    });

    it('request stub contains validation rules', function () {
        $content = file_get_contents($this->stubsPath . '/request.stub');

        expect($content)
            ->toContain('namespace App\Infrastructure\API\Requests;')
            ->toContain('class {{RequestName}} extends BaseRequest')
            ->toContain('public function rules(): array')
            ->toContain('public function messages(): array');
    });

    it('web controller stub contains required structure', function () {
        $content = file_get_contents($this->stubsPath . '/web-controller.stub');

        expect($content)
            ->toContain('namespace App\Infrastructure\UI\Web\Controllers;')
            ->toContain('class {{ControllerName}} extends Controller')
            ->toContain('public function index()')
            ->toContain('public function store(')
            ->toContain('public function show(')
            ->toContain('public function update(')
            ->toContain('public function destroy(');
    });
});
