---
name: clean-architecture-development
description: Build features in a Laravel app that uses plin-code/laravel-clean-architecture, generating domains, actions, services and infrastructure classes with the clean-arch commands and keeping the layer rules green.
---

# Clean Architecture development

## When to use this skill

Use it when the application has `app/Domain`, `app/Application` and
`app/Infrastructure` (or the paths configured in
`config/clean-architecture.php`), and you are adding a feature, a model, a use
case, a controller, a mailable or a notification, or you are fixing a
`phparkitect check` failure.

## Before you generate anything

Read `config/clean-architecture.php` first. `directories` and
`default_namespace` decide where files land and which namespaces they declare,
`generation.model_directory` decides whether the model sits in `Models` or
directly in the domain folder, `generation.job_prefix` (`Process` by default)
prefixes generated jobs and `generation.web_controller_path`
(`Http/Controllers/Web` by default) decides where `make-controller --web`
writes, while API controllers always go to `Http/Controllers/Api`. Never
hardcode `App\Domain`: derive it from the config.

On a fresh Laravel app, run `php artisan clean-arch:install --user-in-domain`
to move the User model into the Domain layer before generating anything else.

On an app that already has code in app/Http, app/Actions, app/Concerns or the
other Laravel folders, such as a starter kit app, run
`php artisan clean-arch:install --migrate-app`, then `composer dump-autoload`.
It moves those folders into the layers, rewrites the namespaces in PHP and the
Wayfinder imports in resources/js, and lists what it left outside the layers:
app/Providers on purpose, app/Models when it holds more than User, and folders
discovered by convention such as app/Policies or app/Livewire. It refuses to
change anything when a destination exists, unless `--force` is passed.
Regenerate the Wayfinder actions afterwards with `php artisan wayfinder:generate`,
passing the options the Vite plugin uses.

## Adding a feature

1. Generate the domain once per aggregate:

   php artisan clean-arch:make-domain Article

   It writes the model, the domain events, the CRUD actions, the requests, the
   resource, the API controller, the migration, a factory and a feature test. Add
   `--no-base` to skip the base classes when the project does not use them.
   After that it asks whether to also generate an observer, a listener, a job,
   a mail, a notification and an export, and `--no-interaction` declines them
   all. Add the `Route::apiResource()` line it prints to routes/api.php:
   the generated test calls the routes under `/api`.

2. Add the use cases that are not CRUD as actions, one class one job:

   php artisan clean-arch:make-action PublishArticle Article

3. Put side effects in the infrastructure layer:

   php artisan clean-arch:make-mail Article
   php artisan clean-arch:make-notification Article
   php artisan clean-arch:make-observer Article Article

4. Wire the observer in a service provider, not in the domain model.

These generators assume a CRUD on a domain model. `make-domain` writes the
whole set with fixed columns, `make-mail`, `make-notification`, `make-job`,
`make-listener`, `make-export` and `make-controller` take the name of a domain
and import its model, and `make-action` writes a body that creates the model.
When the class is not that (a mail named after an event, a controller that
renders one page, a domain that needs only its model, migration and factory),
do not generate and then delete or rewrite: create the class straight in its
layer with `php artisan make:class`, such as
`php artisan make:class Infrastructure/Mail/RegistrationConfirmedMail`, and
the migration and factory with Laravel's own generators. `make-action` is
still worth running for the file, the namespace and the base class: replace
its body.

The single file commands (`make-action`, `make-service`, `make-controller`,
`make-observer`, `make-listener`, `make-job`, `make-mail`, `make-notification`,
`make-export`, `make-arch-rules`) refuse to overwrite an existing file and exit
1. `install`, `make-domain` and `generate-package` skip the files that already
exist, write the rest and exit 0. Pass `--force` when overwriting is what you
want.

## Where each class belongs

- Eloquent models, enums and domain events: `App\Domain`.
- Actions, services, jobs and listeners, which orchestrate use cases:
  `App\Application`.
- Controllers, console commands, requests, resources, mail, notifications,
  observers and exports: `App\Infrastructure`. A console command is an input
  adapter like a controller, the business logic goes in an action. Laravel
  only discovers commands in app/Console/Commands, so register the
  infrastructure ones in bootstrap/app.php with `->withCommands([...])`.

Four kinds of class have no `clean-arch` generator. Create them straight in
their layer, never in a Laravel default folder to move them afterwards:

- Readonly DTOs: the `DTOs` folder of the application layer, for example
  `php artisan make:class Application/DTOs/RegistrationData`.
- Policies of a domain model: a `Policies` folder next to the model, for
  example `php artisan make:class Domain/Articles/Policies/ArticlePolicy`.
  Laravel's policy discovery walks up from the model namespace and finds it
  there, with or without the `Models` subfolder, so nothing has to be
  registered. `make:policy` writes to app/Policies, outside the layers. A
  policy only imports domain classes.
- Domain exceptions: an `Exceptions` folder next to the model, for example
  `App\Domain\Articles\Exceptions\ArticleAlreadyPublishedException`, created
  with `php artisan make:class Domain/Articles/Exceptions/ArticleAlreadyPublishedException`.
  Make it extend PHP's `DomainException`, so the domain imports nothing from
  the other layers, throw it from the action, and map it to an HTTP response
  in the infrastructure layer, for example in `->withExceptions()` of
  bootstrap/app.php. Never throw the exceptions `install` writes to
  `App\Infrastructure\Exceptions` from an action or a model: importing them
  breaks the layer rules.
- Filament panels: a panel is an input adapter like a controller, so its
  resources, pages and widgets go in a `Filament` folder of the infrastructure
  layer. Point `discoverResources()`, `discoverPages()` and
  `discoverWidgets()` of the panel provider at that folder and namespace
  before generating anything: `make:filament-resource` then writes there by
  itself. Its resource namespace option is ignored for a namespace the
  panel does not discover, and the command falls back to app/Filament,
  overwriting what is there. The panel provider stays in app/Providers.

An action never receives the HTTP request: the controller passes
`$request->validated()`, an array, and models already resolved. A readonly DTO
is fine when several callers must normalise the same input.

Generated actions do not open a transaction. When an invariant only holds if
several writes, or a read and a write, happen atomically, the action opens it
itself, with `lockForUpdate()` when it reads before writing. Callers may still
wrap it in a larger transaction. Mail, events and jobs go in
`DB::afterCommit()`, so nothing leaves the system before the data is committed.

The domain must not import the application layer nor the infrastructure layer.
The application layer must not import the infrastructure layer.

When an action needs an outer service, or the domain needs something it does
not own such as a feature flag, add an interface in the layer that needs it,
implement it one layer out and bind the two in a service provider. Do not widen
`validation.application_infrastructure_allowed` to skip the interface.

## Keeping the rules green

    php artisan clean-arch:make-arch-rules --force
    vendor/bin/phparkitect check

`check` exits 1 on violations. Two ways out, in order of preference:

1. Move the class to the layer it belongs to, or put an interface in the
   application layer and the implementation in the infrastructure layer.
2. Only when the project has decided that actions send mail or notifications
   straight from the infrastructure layer, list the namespace in
   `validation.application_infrastructure_allowed` (values are relative to the
   infrastructure layer, for example `['Mail', 'Notifications']`), then
   regenerate the rules with `--force`. Everything else in the infrastructure
   layer stays reported. This is a choice of the project, written in its
   config: when the list is empty, do not add to it to make a violation go
   away, use the interface of point 1. For a mail that is an interface in the
   application layer, such as `RegistrationNotifier`, with the class that
   sends the mail in the infrastructure layer.

Do not turn a rule off in `validation.rules` to silence a single violation: that
hides every other violation of the same rule.

When `no_classes_outside_layers` is on, a class left in app/Http, app/Models,
app/Actions or any other folder outside the layers fails the check. Move it
into the layer it belongs to and update its namespace and every reference.
Only a folder that the framework or a package discovers by convention, such as
Providers or Policies, goes in `validation.outside_layers_allowed`, relative to
the root namespace.

## Adopting the rules on an existing codebase

    vendor/bin/phparkitect generate-baseline
    vendor/bin/phparkitect check

The baseline records today's violations and `check` then fails only on new ones.

## Installing this skill with Boost

Boost asks whether to add a newly installed package only when `boost:update`
runs in an interactive terminal. Run without interaction, or from a Composer
script such as `post-update-cmd`, it skips the question and leaves the package
out. Add `plin-code/laravel-clean-architecture` to the packages list of
boost.json, then run `php artisan boost:update` again: Boost installs the
guideline and this skill, and records the skill name in boost.json itself.
