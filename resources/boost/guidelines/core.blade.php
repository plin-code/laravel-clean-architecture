## Laravel Clean Architecture

This package generates and enforces a three layer structure: `App\Domain`,
`App\Application`, `App\Infrastructure`. Generate code with its commands instead
of writing the folders by hand, so namespaces, base classes and file names stay
consistent with the configured paths.

### Layers and dependency direction

- `App\Domain`: Eloquent models, enums, domain events, must not depend on
  `App\Application` or `App\Infrastructure`.
- `App\Application`: actions, services, jobs, listeners and console commands,
  the use cases, must not depend on `App\Infrastructure`, unless the namespace
  is listed in `validation.application_infrastructure_allowed`.
- `App\Infrastructure`: controllers, requests, resources, mail, notifications,
  observers, exports. Depends on the layers above, never the other way round.
- Observers belong to `App\Infrastructure`, not to the domain. Console commands
  and queued jobs orchestrate use cases and belong to `App\Application`.

### Generating code

@verbatim
<code-snippet name="Create a full domain and its pieces" lang="bash">
php artisan clean-arch:install
php artisan clean-arch:make-domain Article
php artisan clean-arch:make-action PublishArticle Article
php artisan clean-arch:make-service Article
php artisan clean-arch:make-controller Article
php artisan clean-arch:make-mail Article
php artisan clean-arch:make-notification Article
php artisan clean-arch:make-observer Article Article
php artisan clean-arch:make-job Article
php artisan clean-arch:make-listener Article
php artisan clean-arch:make-export Article
</code-snippet>
@endverbatim

`clean-arch:make-domain` writes the model, the events, the actions, the
requests, the resource, the controller, the migration and the test in one go.
Pass `--no-base` to skip the base classes. After that it asks, one confirm at a
time, whether to also generate an observer, a listener, a job, a mail, a
notification and an export, and `--no-interaction` declines all six.

Single file commands (make-action, make-service, make-controller,
make-observer, make-listener, make-job, make-mail, make-notification,
make-export, make-arch-rules) refuse to overwrite an existing file and exit 1.
`clean-arch:install`, `clean-arch:make-domain` and `clean-arch:generate-package`
skip existing files instead, write the rest, and exit 0. Either way, pass
`--force` to overwrite.

On a fresh Laravel app, pass `--user-in-domain` to `clean-arch:install` to move
`User` into `App\Domain` instead of leaving it under `App\Models`.

### Checking the architecture

The package does not analyse code itself, it writes a phparkitect
configuration that the application runs.

@verbatim
<code-snippet name="Generate and run the rules" lang="bash">
composer require --dev phparkitect/phparkitect
php artisan clean-arch:make-arch-rules
vendor/bin/phparkitect check
</code-snippet>
@endverbatim

Regenerate `phparkitect.php` with `clean-arch:make-arch-rules --force` after
changing any key under `validation` in the config, otherwise the file keeps the
old rules.

### Configuration that changes where code is written

Read `config/clean-architecture.php` before assuming paths or namespaces:

- `directories.domain`, `directories.application`, `directories.infrastructure`:
  where each layer lives. The namespaces are derived from these paths.
- `default_namespace`: the root namespace, `App` by default.
- `generation.model_directory`: the subfolder of the domain model, `Models` by
  default. An empty value puts the model directly in the domain folder.
- `validation.rules`: the six rules written into `phparkitect.php`, each can be
  turned off by name.
- `validation.application_infrastructure_allowed`: infrastructure namespaces the
  application layer may import, relative to the infrastructure layer. Empty by
  default.

@verbatim
<code-snippet name="Allow mail and notifications from the application layer" lang="php">
'validation' => [
    'application_infrastructure_allowed' => ['Mail', 'Notifications'],
],
</code-snippet>
@endverbatim
