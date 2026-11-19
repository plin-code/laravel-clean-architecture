# Configuration

`clean-arch:install` writes `config/clean-architecture.php`. It skips any file that already exists, so editing the config or a generated base class is safe to keep across reinstalls. Pass `--force` to overwrite them instead. You can also publish the config on its own:

```bash
php artisan vendor:publish --tag=clean-architecture-config
```

## Directories

`directories` is read by `clean-arch:install` and the `make-*` commands, which write the generated classes at those paths, and by `clean-arch:make-arch-rules`, which turns them into the namespaces and the class sets of the generated config. The layer namespaces are derived from the same values, so `app/Core/Domain` with a `default_namespace` of `Acme` becomes `Acme\Core\Domain`.

```php
'default_namespace' => 'App',

'directories' => [
    'domain' => 'app/Domain',
    'application' => 'app/Application',
    'infrastructure' => 'app/Infrastructure',
],
```

When the config file is not published, the defaults above are used.

The package does not add PSR-4 entries for you. The only change `clean-arch:install` makes to `composer.json` is restoring the `"App\\": "app/"` entry when it is missing. Laravel maps `App\` to `app/` in `composer.json`, which covers every directory under `app/` as long as `default_namespace` stays `App`. Any other combination needs a PSR-4 entry of its own, otherwise the classes are generated but cannot be loaded:

| Configuration | Generated namespace | Entry to add under `autoload.psr-4` |
|---|---|---|
| `app/Core/Domain` with `App` | `App\Core\Domain` | none |
| `app/Domain` with `Acme` | `Acme\Domain` | `"Acme\\": "app/"` |
| `src/Domain` with `App` | `App\src\Domain` | `"App\\src\\": "src/"` |

Two prefixes can point to the same directory, so `"Acme\\": "app/"` sits next to Laravel's `"App\\": "app/"`. Run `composer dump-autoload` after editing `composer.json`.

## Validation

`validation.rules`, `validation.application_infrastructure_allowed` and `validation.outside_layers_allowed` drive the generated phparkitect file. They are described in [Validation rules](architecture-validation.md#validation-rules).

### Custom validation messages

`validation.custom_messages` controls whether `clean-arch:make-domain` generates the `messages()` method in the form requests it creates. It defaults to `true`.

```php
'validation' => [
    'custom_messages' => false,
],
```

Set it to `false` and the generated `Create*Request` and `Update*Request` classes will omit the `messages()` method entirely. The default `rules()` and `authorize()` methods are unaffected, and the output remains valid PHP either way.

Note the keys under `validation` serve different purposes. The `rules` subgroup, `application_infrastructure_allowed` and `outside_layers_allowed` are read by `clean-arch:make-arch-rules`, while `custom_messages` is read by `clean-arch:make-domain` at generation time. They are kept together so that a single published config file is the only place to look.

## Optional base classes

`generation.extend_base_classes` (default `true`) controls whether generated services extend `BaseService` and generated actions extend `BaseAction`. Set it to `false` to produce standalone classes:

```php
'generation' => [
    'extend_base_classes' => false,
],
```

You can also override the config per invocation with `--no-base` on `make-domain`, `make-service`, or `make-action`. The flag always wins:

```bash
php artisan clean-arch:make-service Order --no-base
php artisan clean-arch:make-action CreateOrder Order --no-base
php artisan clean-arch:make-domain Brand --no-base
```

The `BaseService` and `BaseAction` classes created by `clean-arch:install` remain in `Application/Services` and `Application/Actions` regardless. Only the `extends` clause and its `use` statement are omitted.

## Model directory

`generation.model_directory` (default `'Models'`) controls the subfolder the domain model is generated under, inside each domain's directory. It is read by every command that writes or imports the model, `make-domain` and `clean-arch:install --user-in-domain` as well as the standalone `make-action`, `make-export`, `make-job`, `make-mail`, `make-notification` and `make-observer`, so a domain stays consistent no matter which command touches it next.

```php
'generation' => [
    'model_directory' => 'Models',
],
```

Set it to `null` or an empty string to generate the model directly inside the domain directory, with no subfolder:

```php
'generation' => [
    'model_directory' => null,
],
```

`clean-arch:make-domain Product` then writes `app/Domain/Products/Product.php`, declaring `App\Domain\Products\Product`, instead of `app/Domain/Products/Models/Product.php` declaring `App\Domain\Products\Models\Product`.

Any other single segment replaces `Models`, for example `'Entities'` produces `app/Domain/Products/Entities/Product.php` declaring `App\Domain\Products\Entities\Product`. Surrounding slashes are trimmed, so `'/Entities/'` behaves the same as `'Entities'`.

## Job prefix

`generation.job_prefix` (default `'Process'`) controls the prefix `make-job` and `make-domain` put in front of the generated job, in the class name as well as in the file name. `clean-arch:make-job Article` writes `app/Application/Jobs/ProcessArticleJob.php` declaring `ProcessArticleJob`.

```php
'generation' => [
    'job_prefix' => 'Handle',
],
```

The same command then writes `HandleArticleJob.php`. Set it to `null` or an empty string to generate the job under its own name, `ArticleJob.php`. The prefix is also what the command strips from the name you pass, so `make-job HandleArticleJob` stays `HandleArticleJob` instead of becoming `HandleHandleArticleJobJob`.

## Web controller path

`generation.web_controller_path` (default `'Http/Controllers/Web'`) controls where `make-controller --web` writes, relative to the infrastructure layer directory, and the namespace the generated controller declares. API controllers are not affected: `--api` always writes to `Http/Controllers/Api`.

```php
'generation' => [
    'web_controller_path' => 'Http/Controllers',
],
```

`clean-arch:make-controller Report --web` then writes `app/Infrastructure/Http/Controllers/ReportController.php` declaring `App\Infrastructure\Http\Controllers`. Backslashes and surrounding slashes are accepted, so `'Web\Controllers'` and `'/Web/Controllers/'` behave the same. Set it to `null` or an empty string to write the controller in the infrastructure layer itself.
