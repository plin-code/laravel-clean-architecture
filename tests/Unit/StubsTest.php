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
            'migration.stub',
            'request.stub',
            'service.stub',
            'list-action.stub',
            'get-by-id-action.stub',
            'update-action.stub',
            'delete-action.stub',
            'web-controller.stub',
            'observer.stub',
            'listener.stub',
            'job.stub',
            'mail.stub',
            'notification.stub',
            'export.stub',
            'factory.stub',
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
            ->toContain('namespace {{ApplicationNamespace}}\Actions\{{PluralDomainName}};')
            ->toContain('class {{ActionName}}{{ActionExtends}}')
            ->toContain('public function execute');
    });

    it('action stub carries the optional BaseAction import placeholder', function () {
        $content = file_get_contents($this->stubsPath . '/action.stub');

        expect($content)
            ->toContain('{{ActionBaseImport}}use {{DomainNamespace}}\\')
            ->not->toContain('// {{#base_class}}');
    });

    it('controller stub contains required structure', function () {
        $content = file_get_contents($this->stubsPath . '/controller.stub');

        expect($content)
            ->toContain('namespace {{InfrastructureNamespace}}\Http\Controllers\Api;')
            ->toContain('class {{PluralDomainName}}Controller extends Controller');
    });

    it('service stub contains required structure', function () {
        $content = file_get_contents($this->stubsPath . '/service.stub');

        expect($content)
            ->toContain('namespace {{ApplicationNamespace}}\Services;')
            ->toContain('class {{DomainName}}Service{{ServiceExtends}}');
    });

    it('service stub is a skeleton without query methods', function () {
        $content = file_get_contents($this->stubsPath . '/service.stub');

        expect($content)
            ->toContain('logic shared by several actions')
            ->not->toContain('public function')
            ->not->toContain('{{DomainNamespace}}');
    });

    it('service stub extends BaseService without importing it from its own namespace', function () {
        $content = file_get_contents($this->stubsPath . '/service.stub');

        expect($content)
            ->toContain('{{ServiceExtends}}')
            ->not->toContain('use ')
            ->not->toContain('// {{#base_class}}');
    });

    it('domain model stub contains required content', function () {
        $content = file_get_contents($this->stubsPath . '/domain-model.stub');

        expect($content)
            ->toContain('namespace {{DomainNamespace}}\{{PluralDomainName}}{{ModelNamespace}};')
            ->toContain('class {{DomainName}} extends BaseModel')
            ->toContain('protected $table = \'{{domain-table}}\';')
            ->toContain('protected $fillable')
            ->toContain('protected $casts')
            ->toContain('protected $dispatchesEvents');
    });

    it('request stub contains validation rules', function () {
        $content = file_get_contents($this->stubsPath . '/request.stub');

        expect($content)
            ->toContain('namespace {{InfrastructureNamespace}}\Http\Requests;')
            ->toContain('class {{RequestName}} extends BaseRequest')
            ->toContain('public function rules(): array')
            ->toContain('public function messages(): array');
    });

    it('request stub wraps messages in a custom_messages block', function () {
        $content = file_get_contents($this->stubsPath . '/request.stub');

        expect($content)
            ->toContain('// {{#custom_messages}}')
            ->toContain('// {{/custom_messages}}');
    });

    it('web controller stub contains required structure', function () {
        $content = file_get_contents($this->stubsPath . '/web-controller.stub');

        expect($content)
            ->toContain('namespace {{WebControllerNamespace}};')
            ->toContain('class {{ControllerName}} extends Controller')
            ->toContain('public function index()')
            ->toContain('public function store(')
            ->toContain('public function show(')
            ->toContain('public function update(')
            ->toContain('public function destroy(');
    });

    it('shipped config file does not declare the dead strict_mode key', function () {
        $stub = file_get_contents($this->stubsPath . '/../config/clean-architecture.php');

        expect($stub)
            ->not->toContain('strict_mode')
            ->toContain("'custom_messages' => true");
    });

    it('config stub does not declare the dead strict_mode key', function () {
        $stub = file_get_contents($this->stubsPath . '/config.stub');

        expect($stub)
            ->not->toContain('strict_mode')
            ->toContain("'custom_messages' => true")
            ->toContain("'extend_base_classes' => true")
            ->toContain("'model_directory' => 'Models'");
    });

    it('keeps the shipped config and the install stub in sync', function () {
        $shipped = require __DIR__ . '/../../config/clean-architecture.php';
        $stub    = require $this->stubsPath . '/config.stub';

        expect($stub)->toEqual($shipped);
    });

    it('shipped config declares extend_base_classes', function () {
        $config = file_get_contents($this->stubsPath . '/../config/clean-architecture.php');

        expect($config)
            ->toContain("'extend_base_classes' => true")
            ->not->toContain('strict_mode');
    });

    it('shipped config declares model_directory', function () {
        $config = file_get_contents($this->stubsPath . '/../config/clean-architecture.php');

        expect($config)->toContain("'model_directory' => 'Models'");
    });

    it('readme stub follows the markdown layout prettier writes', function () {
        $content = file_get_contents($this->stubsPath . '/readme.stub');
        $lines   = explode("\n", $content);
        $inFence = false;

        expect($content)->toEndWith("```\n")->not->toEndWith("\n\n");

        foreach ($lines as $index => $line) {
            expect($line)->toBe(rtrim($line), 'Trailing whitespace on line ' . ($index + 1));

            $previous = $lines[$index - 1] ?? '';

            if (str_starts_with($line, '```')) {
                if (! $inFence) {
                    expect($previous)->toBe('', 'A code fence needs a blank line above it, line ' . ($index + 1));
                }

                $inFence = ! $inFence;

                continue;
            }

            if ($inFence || $index === 0) {
                continue;
            }

            if (str_starts_with($line, '#')) {
                expect($previous)->toBe('', 'A heading needs a blank line above it, line ' . ($index + 1))
                    ->and($lines[$index + 1] ?? '')->toBe('', 'A heading needs a blank line below it, line ' . ($index + 1));
            }

            if (preg_match('/^(- |\d+\. )/', $line) && ! preg_match('/^(- |\d+\. )/', $previous)) {
                expect($previous)->toBe('', 'A list needs a blank line above it, line ' . ($index + 1));
            }
        }
    });
});
