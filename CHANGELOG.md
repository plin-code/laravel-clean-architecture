# Changelog

All notable changes to `laravel-clean-architecture` will be documented in this file.

## [2.0.0] - 2026-03-31

### Breaking Changes

- Domain models now live under `Domain/{Name}/Models/` instead of directly under `Domain/{Name}/`
- Domain enums now live under `Domain/{Name}/Enums/` and domain events under `Domain/{Name}/Events/` (nested subdirectory structure is now enforced)
- Infrastructure HTTP files are now consolidated under `Infrastructure/Http/` (previously `Infrastructure/API/`)
- PHP 8.4+ is now required (previously 8.3+)

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