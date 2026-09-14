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
directly in the domain folder. Never hardcode `App\Domain`: derive it from the
config.

On a fresh Laravel app, run `php artisan clean-arch:install --user-in-domain`
to move the User model into the Domain layer before generating anything else.

## Adding a feature

1. Generate the domain once per aggregate:

   php artisan clean-arch:make-domain Article

   It writes the model, the domain events, the CRUD actions, the requests, the
   resource, the API controller, the migration and a feature test. Add
   `--no-base` to skip the base classes when the project does not use them.

2. Add the use cases that are not CRUD as actions, one class one job:

   php artisan clean-arch:make-action PublishArticle Article

3. Put side effects in the infrastructure layer:

   php artisan clean-arch:make-mail Article
   php artisan clean-arch:make-notification Article
   php artisan clean-arch:make-observer Article Article

4. Wire the observer in a service provider, not in the domain model.

The single file commands (`make-action`, `make-service`, `make-controller`,
`make-observer`, `make-listener`, `make-job`, `make-mail`, `make-notification`,
`make-export`, `make-arch-rules`) refuse to overwrite an existing file and exit
1. `install`, `make-domain` and `generate-package` skip the files that already
exist, write the rest and exit 0. Pass `--force` when overwriting is what you
want.

## Where each class belongs

- Eloquent models, enums and domain events: `App\Domain`.
- Actions, services, jobs and listeners, plus console commands, which
  orchestrate use cases: `App\Application`.
- Controllers, requests, resources, mail, notifications, observers and exports:
  `App\Infrastructure`.

The domain must not import the application layer nor the infrastructure layer.
The application layer must not import the infrastructure layer.

## Keeping the rules green

    php artisan clean-arch:make-arch-rules --force
    vendor/bin/phparkitect check

`check` exits 1 on violations. Two ways out, in order of preference:

1. Move the class to the layer it belongs to, or put an interface in the
   application layer and the implementation in the infrastructure layer.
2. If the import is a deliberate side effect, such as sending mail or a
   notification straight from an action, list the namespace in
   `validation.application_infrastructure_allowed` (values are relative to the
   infrastructure layer, for example `['Mail', 'Notifications']`), then
   regenerate the rules with `--force`. Everything else in the infrastructure
   layer stays reported.

Do not turn a rule off in `validation.rules` to silence a single violation: that
hides every other violation of the same rule.

## Adopting the rules on an existing codebase

    vendor/bin/phparkitect generate-baseline
    vendor/bin/phparkitect check

The baseline records today's violations and `check` then fails only on new ones.
