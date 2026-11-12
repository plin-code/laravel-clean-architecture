<?php

use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

/**
 * Call the protected import sorter on an anonymous class that uses the trait.
 */
function sortGeneratedImports(string $path, string $content): string
{
    $subject = new class
    {
        use WritesFiles;
    };

    $method = new ReflectionMethod($subject, 'sortImports');

    return $method->invoke($subject, $path, $content);
}

describe('WritesFiles', function () {
    it('sorts the imports the way pint does, whatever the rendered names', function () {
        $content = <<<'PHP'
            <?php

            namespace Zeta\Domain\Users\Models;

            use Zeta\Domain\Shared\BaseModel;
            use Zeta\Domain\Users\Enums\UserStatus;
            use Database\Factories\UserFactory;
            use Illuminate\Database\Eloquent\Factories\HasFactory;

            class User extends BaseModel
            {
                use HasFactory;
            }
            PHP;

        expect(sortGeneratedImports('app/User.php', $content))->toContain(<<<'PHP'
            use Database\Factories\UserFactory;
            use Illuminate\Database\Eloquent\Factories\HasFactory;
            use Zeta\Domain\Shared\BaseModel;
            use Zeta\Domain\Users\Enums\UserStatus;

            class User extends BaseModel
            {
                use HasFactory;
            }
            PHP);
    });

    it('compares namespace separators as spaces and ignores case', function () {
        $content = "<?php\n\nuse Arkitect\\Rules\\Rule;\nuse Arkitect\\CLI\\Config;\nuse Arkitect\\ClassSet;\n";

        expect(sortGeneratedImports('phparkitect.php', $content))
            ->toBe("<?php\n\nuse Arkitect\\ClassSet;\nuse Arkitect\\CLI\\Config;\nuse Arkitect\\Rules\\Rule;\n");
    });

    it('leaves files that are not php alone', function () {
        $content = "```php\nuse Zeta\\B;\nuse Alpha\\A;\n```\n";

        expect(sortGeneratedImports('CLEAN_ARCHITECTURE.md', $content))->toBe($content);
    });
});
