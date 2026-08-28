# Changelog

All notable changes to `laravel-clean-architecture` will be documented in this file.

## [2.2.0] - 2026-08-28

### Added

- `clean-arch:make-arch-rules` writes a `phparkitect.php` built from `directories`, `default_namespace` and `validation.rules`. The package neither depends on phparkitect nor runs it: install it in your own `require-dev` and run `vendor/bin/phparkitect check`. The generated rules follow inheritance chains, so a command extending a project base command is reported where `clean-arch:validate` misses it. The file opens with a `class_exists` guard, because the reflection based rules report nothing and exit 0 when autoloading does not resolve, which in CI reads as a pass. Pass `--force` to overwrite an existing file

### Deprecated

- `clean-arch:validate` prints a deprecation warning and will be removed in 3.0.0. It still runs every enabled rule and its exit codes are unchanged, so a pipeline that uses it keeps working until then. Replace it with `clean-arch:make-arch-rules` plus `vendor/bin/phparkitect check`, and use `vendor/bin/phparkitect generate-baseline` if the codebase already has violations to record

### Removed

- `no_duplicate_services_directory`, the rule that failed the validation when `app/Infrastructure/Services` existed. It checked a directory rather than a property of a class, so it has no equivalent in the rule set the package now generates for phparkitect, and keeping it would mean keeping a hand written check next to a delegated one. It came from a convention of this package, not from a principle of the architecture: a project that wants it back can assert `is_dir()` in three lines of its own. The `validation.rules` key is gone from `config/clean-architecture.php`, and a leftover key in a published config file is ignored

## [2.1.0] - 2026-08-28

### Fixed

- `make-domain` generated actions and requests that were not valid PHP. The placeholder keys were passed to `str_replace()` without their braces, so `class {{ActionName}}` became `class {{CreateUserAction}}` instead of `class CreateUserAction`. Every `Create*Action`, `Update*Action`, `Delete*Action`, `GetById*Action`, `Create*Request` and `Update*Request` produced by the command was affected
- `clean-arch:validate` reported controllers, resources and requests as violations when their name merely contained `Command`, `Job` or `Observer`. A `CommandPaletteController` is no longer a console command. Detection is now based on the class (`extends Illuminate\Console\Command`, `implements ShouldQueue`) rather than on the file name, and observers match the exact `Observer.php` suffix
- `clean-arch:validate` counted trait `use` statements inside a class body, commented out lines and occurrences inside strings as namespace imports. Imports are now extracted with `token_get_all()`, including grouped and aliased ones
- `directories` in the configuration file was never read. Changing `directories.domain` produced no effect and no error. It is now honoured by `clean-arch:validate` and `clean-arch:install`, with the previous hardcoded values as defaults when the config is not published. Layer namespaces are derived from the same values
- The CI workflow had been failing since March: it called workflows that did not declare `on: workflow_call`, and its quality gate read a job that was not among its dependencies. Pull requests opened against a branch other than `main` or `develop` received no checks at all

### Added

- `validation.rules` lets each rule of `clean-arch:validate` be turned off by name. Every rule stays enabled by default, so behaviour does not change on upgrade. `no_commands_in_infrastructure` is the likely candidate for opting out: a console command is an input adapter like an HTTP controller, and keeping it in `Application` forces that layer to depend on `Illuminate\Console`
- `validation.custom_messages` controls whether generated form requests carry a `messages()` method with hardcoded English strings. Useful for projects that rely on Laravel translations
- `generation.extend_base_classes` and the `--no-base` flag on `make-domain`, `make-service` and `make-action` generate services and actions without extending `BaseService` and `BaseAction`. The flag wins over the config

### Changed

- `getStub()` and the shared placeholder replacement move from the individual commands into the `RendersStubs` trait. In `make-export`, `make-job`, `make-listener`, `make-mail` and `make-notification` the shared method is now called `replaceDomainPlaceholders()`, which matters only if you subclassed one of them and overrode `replacePlaceholders()`
- The README no longer claims the Domain layer is free of frameworks. Generated models extend `App\Domain\Shared\BaseModel`, which extends `Illuminate\Database\Eloquent\Model`, and the trade-off is now stated explicitly
- The generated `CLEAN_ARCHITECTURE.md` describes the directories `clean-arch:install` actually creates: `Console/Commands`, plus `Exports` and `Validation` which were created but undocumented, and `UI` which does not contain `Web` until `make-controller --web` creates it

### Removed

- Configuration keys that were declared but never read by any code: `auto_discovery`, `logging`, `stubs.path` and `validation.strict_mode`. None of them had any effect, so removing them changes no behaviour

### Known limitations

- `clean-arch:validate` inspects the class as written. A console command extending a project specific base class that itself extends `Illuminate\Console\Command` is not reported, and neither is a job extending an abstract base job that implements `ShouldQueue`. Following the chain would mean loading application code inside a validation command

## [2.0.2] - 2026-04-04

### Changed

- Generated actions no longer receive a form request. `execute()` now takes an array, so the Application layer stops depending on `App\Infrastructure\Http\Requests`. Generated controllers pass `$request->validated()`
- Generated actions no longer open a database transaction of their own, and `BaseAction::executeInTransaction()` is removed. Transaction control belongs to the caller

## [2.0.1] - 2026-03-31

### Fixed

- README badges updated to flat-square style consistent with other plin-code packages
- Fixed PHP version in features section (8.3+, not 8.4+)
- Added missing database migration to `make-domain` generated files documentation
- Added full `install` directory structure to README
- Added `clean-arch:validate` output example to README

## [2.0.0] - 2026-03-31

### Breaking Changes

- Domain models now live under `Domain/{Name}/Models/` instead of directly under `Domain/{Name}/`
- Domain enums now live under `Domain/{Name}/Enums/` and domain events under `Domain/{Name}/Events/` (nested subdirectory structure is now enforced)
- Infrastructure HTTP files are now consolidated under `Infrastructure/Http/` (previously `Infrastructure/API/`)
- PHP 8.3+ is now the minimum (previously 8.3 only)

### Added

- Laravel 13 support alongside Laravel 12

- Interactive prompts in `make-domain` for optional components (Observer, Listener, Job, Mail, Notification, Export)
- `clean-arch:make-observer {name} {domain}` command to generate domain observers
- `clean-arch:make-listener {name}` command to generate event listeners
- `clean-arch:make-job {name}` command to generate queued jobs
- `clean-arch:make-mail {name}` command to generate mailables
- `clean-arch:make-notification {name}` command to generate notifications
- `clean-arch:make-export {name}` command to generate exports
- `clean-arch:validate` command for architectural dependency validation (CI-friendly, returns exit code 1 on violations)

### Changed

- `clean-arch:generate-package` updated to reflect v2 nested directory structure

## [Unreleased]

### Added
- Initial release of Laravel Clean Architecture package
- `clean-arch:install` command to setup Clean Architecture structure
- `clean-arch:make-domain` command to generate complete domain structure
- `clean-arch:make-action` command to generate individual actions
- `clean-arch:make-service` command to generate services
- `clean-arch:make-controller` command to generate controllers
- `clean-arch:generate-package` command to generate new packages
- Complete set of stub templates for all components
- Base classes for Model, Action, Service, Controller, Request
- Domain events and enums support
- Custom exceptions for domain, validation, and business logic
- API Resources for consistent JSON responses
- Feature tests templates
- Configuration file for package customization
- Comprehensive documentation and README

### Features
- Clean Architecture implementation following DDD principles
- Automatic generation of Domain, Application, and Infrastructure layers
- Support for Laravel 12.x
- PHP 8.3+ compatibility
- English validation messages and documentation
- Comprehensive test coverage templates

## [1.0.0] - 2024-12-XX

### Added
- Initial stable release 