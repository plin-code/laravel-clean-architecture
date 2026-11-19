# Architecture validation

Architectural rules are enforced by [phparkitect](https://github.com/phparkitect/arkitect). The package does not depend on it and does not run it: it generates a configuration file from `config/clean-architecture.php`, and your project runs the tool.

```bash
composer require --dev phparkitect/phparkitect
php artisan clean-arch:make-arch-rules
vendor/bin/phparkitect check
```

`clean-arch:make-arch-rules` writes `phparkitect.php` in the project root, built from `directories`, `default_namespace` and `validation.rules`. It refuses to overwrite an existing file, so pass `--force` when you want to regenerate one. The generated file is ordinary PHP: once you need rules the package does not generate, edit it by hand and stop regenerating it.

`phparkitect check` exits 1 when it finds violations, which is what you want in CI. Rules can be disabled one by one, see [Validation rules](#validation-rules).

phparkitect follows inheritance chains. A job extending an abstract base job that implements `ShouldQueue` is reported by `no_jobs_in_infrastructure`, and with `no_commands_in_infrastructure` on, so is a console command extending a project specific base class that itself extends `Illuminate\Console\Command`.

## The autoload guard

The generated file opens with a check that looks out of place until it saves you:

```php
use App\Domain\Shared\BaseModel;
use Illuminate\Console\Command;

if (! class_exists(Command::class) || ! class_exists(BaseModel::class)) {
    throw new RuntimeException(
        'clean-architecture: autoloading does not resolve the application classes, '
        .'so the reflection based rules would pass silently. Run composer dump-autoload.'
    );
}
```

Two of the rules are reflection based. When autoloading does not resolve the classes being scanned, `is_a()` reads an unloadable class as "not a subclass", so those rules report nothing and phparkitect exits 0. In CI that is indistinguishable from a clean run. The guard turns that case into a failure with a message, and it costs nothing because it runs before the scan.

The `BaseModel` it imports is the one `clean-arch:install` generates for your domain layer. If you rename or remove it, the guard fires even with a sound autoloader. That is a false alarm, and it is the right way round to be wrong: a false alarm is loud and the message says what to look at, a silent pass is neither. Point the import at another class of yours and keep it.

## Adopting it on an existing codebase

A codebase that has never been checked usually starts with a long list of violations. Record them once and fail only on new ones:

```bash
vendor/bin/phparkitect generate-baseline
vendor/bin/phparkitect check
```

`generate-baseline` writes `phparkitect-baseline.json` with the violations found today, and `check` picks that file up automatically and exits 0. Regenerate it as you fix things, or pass `--skip-baseline` to see the full list again. Fixing the recorded violations does not require regenerating: the baseline is a list of what to ignore, not a target.

## Validation rules

Every rule can be turned off by name under `validation.rules`. A rule set to `false` is left out of the config written by `clean-arch:make-arch-rules`. All of them except `no_commands_in_infrastructure` and `no_classes_outside_layers` are enabled by default, and a project without a published config file gets the same set.

```php
'validation' => [
    'rules' => [
        'domain_no_application_imports' => true,
        'domain_no_infrastructure_imports' => true,
        'application_no_infrastructure_imports' => true,
        'no_observers_in_domain' => true,
        'no_jobs_in_infrastructure' => true,
        'no_commands_in_infrastructure' => false,
        'no_classes_outside_layers' => false,
    ],
],
```

`no_commands_in_infrastructure` is off by default. A console command is an input adapter, much like an HTTP controller: it reads arguments and options, prints output and calls an action, which holds the business logic. Keeping it in `Application` would force the Application layer to depend on `Illuminate\Console`. Turn the rule on if you prefer commands in `Application/Console/Commands`.

Some projects send mail and notifications straight from an action, importing `App\Infrastructure\Mail` or `App\Infrastructure\Notifications` without an interface in between. Turning `application_no_infrastructure_imports` off to accept that would also hide the imports you still want reported. List the accepted namespaces under `validation.application_infrastructure_allowed` instead:

```php
'validation' => [
    'application_infrastructure_allowed' => ['Mail', 'Notifications'],
],
```

Values are relative to the infrastructure layer, so they keep working with custom `directories` and `default_namespace`. With the example above an action importing `App\Infrastructure\Mail\ArticleMail` passes, while one importing `App\Infrastructure\Http\Controllers\Controller` or `App\Infrastructure\Filament\ArticleResource` is still reported. The list is empty by default, and an empty list generates exactly the same `phparkitect.php` as before. An invalid entry (not a string, or empty) makes `clean-arch:make-arch-rules` fail without writing the file. After changing the key, regenerate the file with `php artisan clean-arch:make-arch-rules --force`.

### No classes outside the layers

The other rules only read the three layer directories, so code left in `app/Http`, `app/Models`, `app/Actions` or `app/Filament` is never checked and `phparkitect check` stays green on an app that is half Laravel folders and half layers. `no_classes_outside_layers` closes that gap: every class under the root namespace has to reside in one of the three layers, or in a namespace listed under `validation.outside_layers_allowed`.

```php
'validation' => [
    'rules' => [
        'no_classes_outside_layers' => true,
    ],
    'outside_layers_allowed' => ['Providers'],
],
```

The rule is off by default, because a Laravel app reports its whole `app/Http` and `app/Models`, and everything else it ships outside the layers, until that code has been moved, for example with [`clean-arch:install --migrate-app`](migrating-an-existing-app.md#migrating-an-existing-app-into-the-layers).

The root is the top level directory the three layers share, `app` with the defaults, and its namespace is derived like the layer namespaces, so `App` or the configured `default_namespace`. Layers nested deeper, such as `app/Core/Domain`, still have `app` as their root, so `app/Http` keeps being checked. When the layers do not share a top level directory (for example `src/Domain` next to `app/Application`), or one of them sits directly in the project root, `clean-arch:make-arch-rules` fails with a message and writes nothing. Turn the rule off for such a layout.

With the rule on, the generated file scans the root directory once instead of the three layer directories. The layer rules still only apply to their own namespaces, so every class is checked once and no violation is reported twice.

`validation.outside_layers_allowed` lists namespaces relative to the root namespace that may stay outside the layers. It defaults to `['Providers']`: Laravel registers service providers from `bootstrap/providers.php`, and `make:provider` and the packages that publish a provider write them to `app/Providers`. Add the folders a framework or package discovers by convention and that you keep where they are, for example `'Policies'` (policy auto discovery looks next to the models), `'Livewire'` or `'Filament'`. An invalid entry (not a string, or empty) makes `clean-arch:make-arch-rules` fail without writing the file.
