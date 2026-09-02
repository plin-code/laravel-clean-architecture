![Laravel Clean Architecture](https://raw.githubusercontent.com/plin-code/laravel-clean-architecture/main/art/banner.png)

# Laravel Clean Architecture

A Laravel package to easily implement Clean Architecture in your projects.

<p align="center">
    <a href="https://packagist.org/packages/plin-code/laravel-clean-architecture"><img src="https://img.shields.io/packagist/v/plin-code/laravel-clean-architecture.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/plin-code/laravel-clean-architecture"><img src="https://img.shields.io/packagist/php-v/plin-code/laravel-clean-architecture.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/plin-code/laravel-clean-architecture"><img src="https://badge.laravel.cloud/badge/plin-code/laravel-clean-architecture?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/plin-code/laravel-clean-architecture/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/plin-code/laravel-clean-architecture/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/plin-code/laravel-clean-architecture"><img src="https://img.shields.io/packagist/dt/plin-code/laravel-clean-architecture.svg?style=flat-square" alt="Total Downloads"></a>
</p>

## ✨ Features

- 🎯 **Domain-Driven Design** - Organize your code with DDD principles
- ⚡ **Quick Setup** - Get started with Clean Architecture in minutes
- 🧩 **Auto-Generation** - Generate complete domains with one command
- 🏛️ **Layer Separation** - Clear separation between Domain, Application, and Infrastructure
- 🔧 **Customizable** - Flexible configuration to fit your project needs
- 🧪 **Test-Ready** - Pre-built test templates for immediate testing
- 📚 **Well-Documented** - Comprehensive documentation and examples
- 🎨 **Modern PHP** - Built for PHP 8.3+ with latest Laravel features

## 📋 Requirements

- 🐘 PHP 8.3+
- ⚡ Laravel 12.x / 13.x

## 📦 Installation

```bash
composer require plin-code/laravel-clean-architecture
```

## ⚙️ Configuration

Publish the configuration files and stubs:

```bash
php artisan vendor:publish --provider="PlinCode\LaravelCleanArchitecture\CleanArchitectureServiceProvider"
```

## 🎯 Usage

### 🏗️ Installing Clean Architecture structure

```bash
php artisan clean-arch:install
```

This command will create:
- 📁 Folder structure for Domain, Application and Infrastructure layers
- 🧩 Base classes (BaseModel, BaseAction, BaseService, etc.)
- ⚙️ Configuration file
- 📖 Documentation

### 🆕 Creating a new domain

```bash
php artisan clean-arch:make-domain User
```

This command will generate:
- 🏛️ Domain model with events
- 📊 Status enums
- 🔔 Domain events (Created, Updated, Deleted)
- ⚡ Actions (Create, Update, Delete, GetById)
- 🔧 Service
- 🌐 API Controller
- 📝 Form Requests (Create, Update)
- 📤 API Resource
- 🗃️ Database migration
- 🧪 Feature tests

After generating the core files, `make-domain` prompts interactively for optional components. You can choose to also generate an Observer, Listener, Job, Mail, Notification, and Export for the domain. Each prompt can be answered independently, so you only generate what your domain needs.

### ✅ Architecture validation

Architectural rules are enforced by [phparkitect](https://github.com/phparkitect/arkitect). The package does not depend on it and does not run it: it generates a configuration file from `config/clean-architecture.php`, and your project runs the tool.

```bash
composer require --dev phparkitect/phparkitect
php artisan clean-arch:make-arch-rules
vendor/bin/phparkitect check
```

`clean-arch:make-arch-rules` writes `phparkitect.php` in the project root, built from `directories`, `default_namespace` and `validation.rules`. It refuses to overwrite an existing file, so pass `--force` when you want to regenerate one. The generated file is ordinary PHP: once you need rules the package does not generate, edit it by hand and stop regenerating it.

`phparkitect check` exits 1 when it finds violations, which is what you want in CI. Rules can be disabled one by one, see [Validation rules](#-validation-rules).

Delegating brings something the previous hand written analyser could not do: inheritance chains are followed. A console command extending a project specific base class that itself extends `Illuminate\Console\Command` is now reported, and so is a job extending an abstract base job that implements `ShouldQueue`.

#### 🛡️ The autoload guard

The generated file opens with a check that looks out of place until it saves you:

```php
if (! class_exists(\Illuminate\Console\Command::class)
    || ! class_exists(\App\Domain\Shared\BaseModel::class)) {
    throw new RuntimeException(
        'clean-architecture: autoloading does not resolve the application classes, '
        . 'so the reflection based rules would pass silently. Run composer dump-autoload.'
    );
}
```

Two of the rules are reflection based. When autoloading does not resolve the classes being scanned, `is_a()` reads an unloadable class as "not a subclass", so those rules report nothing and phparkitect exits 0. In CI that is indistinguishable from a clean run. The guard turns that case into a failure with a message, and it costs nothing because it runs before the scan.

The class it names is the one `clean-arch:install` generates for your domain layer. If you rename or remove it, the guard fires even with a sound autoloader. That is a false alarm, and it is the right way round to be wrong: a false alarm is loud and the message says what to look at, a silent pass is neither. Point the check at another class of yours and keep it.

#### 📋 Adopting it on an existing codebase

A codebase that has never been checked usually starts with a long list of violations. Record them once and fail only on new ones:

```bash
vendor/bin/phparkitect generate-baseline
vendor/bin/phparkitect check
```

`generate-baseline` writes `phparkitect-baseline.json` with the violations found today, and `check` picks that file up automatically and exits 0. Regenerate it as you fix things, or pass `--skip-baseline` to see the full list again. Fixing the recorded violations does not require regenerating: the baseline is a list of what to ignore, not a target.

### 🛠️ Available commands

- `clean-arch:install` - 🏗️ Install Clean Architecture structure
- `clean-arch:make-domain {name} {--no-base}` - 🆕 Create a complete new domain
- `clean-arch:make-action {name} {domain} {--no-base}` - ⚡ Create a new action
- `clean-arch:make-service {name} {--no-base}` - 🔧 Create a new service
- `clean-arch:make-controller {name}` - 🌐 Create a new controller
- `clean-arch:make-observer {name} {domain}` - 👁️ Create a new observer
- `clean-arch:make-listener {name}` - 👂 Create a new listener
- `clean-arch:make-job {name}` - ⏳ Create a new job
- `clean-arch:make-mail {name}` - 📧 Create a new mailable
- `clean-arch:make-notification {name}` - 🔔 Create a new notification
- `clean-arch:make-export {name}` - 📤 Create a new export
- `clean-arch:make-arch-rules {--force}` - 🛡️ Generate a phparkitect config from the configured rules
- `clean-arch:generate-package {name} {vendor}` - 📦 Generate a new package

### 📂 Project structure after `clean-arch:install`

```
app/
├── Domain/                          # Business logic (Eloquent models, enums, events)
├── Application/                     # Use cases and orchestration
│   ├── Actions/
│   ├── Services/
│   ├── Jobs/
│   ├── Listeners/
│   └── Console/Commands/
└── Infrastructure/                  # Framework adapters
    ├── Http/
    │   ├── Controllers/Api/
    │   ├── Middleware/
    │   ├── Requests/
    │   └── Resources/
    ├── UI/
    ├── Mail/
    ├── Notifications/
    ├── Observers/
    ├── Exports/
    ├── Validation/
    └── Exceptions/
```

### 📂 Generated structure after `clean-arch:make-domain User`

```
app/
├── Domain/
│   └── Users/
│       ├── Models/
│       │   └── User.php
│       ├── Enums/
│       │   └── UserStatus.php
│       └── Events/
│           ├── UserCreated.php
│           ├── UserUpdated.php
│           └── UserDeleted.php
├── Application/
│   ├── Actions/
│   │   └── Users/
│   │       ├── CreateUserAction.php
│   │       ├── UpdateUserAction.php
│   │       ├── DeleteUserAction.php
│   │       └── GetByIdUserAction.php
│   └── Services/
│       └── UserService.php
└── Infrastructure/
    └── Http/
        ├── Controllers/
        │   └── Api/
        │       └── UsersController.php
        ├── Requests/
        │   ├── CreateUserRequest.php
        │   └── UpdateUserRequest.php
        └── Resources/
            └── UserResource.php
```

## 🏛️ Clean Architecture Principles

This package implements Clean Architecture principles:

1. **🎯 Domain Layer**: Contains business logic and entities
2. **⚡ Application Layer**: Contains use cases and application logic
3. **🏗️ Infrastructure Layer**: Contains implementation details (controllers, database, etc.)

### 🔗 Dependencies

- **🎯 Domain Layer**: Does not depend on the Application or Infrastructure layers
- **⚡ Application Layer**: Depends only on Domain Layer
- **🏗️ Infrastructure Layer**: Depends on Application and Domain Layers

### 🗄️ The Domain layer depends on Eloquent

This is a deliberate trade-off, and it is worth stating explicitly. `clean-arch:install` generates `App\Domain\Shared\BaseModel`, which extends `Illuminate\Database\Eloquent\Model`, and every model produced by `clean-arch:make-domain` extends it. The Domain layer is therefore free of Application and Infrastructure imports (that is what the generated rules enforce), but it is not free of the framework.

If you need a persistence agnostic domain, this package is not the right starting point.

## 💡 Examples

### 🛍️ Creating a Product domain

```bash
php artisan clean-arch:make-domain Product
```

### 🎮 Using in controller

```php
class ProductsController extends Controller
{
    public function __construct(
        private CreateProductAction $createProductAction,
        private ProductService $productService
    ) {}

    public function store(CreateProductRequest $request): JsonResponse
    {
        $product = $this->createProductAction->execute($request);
        
        return response()->json([
            'data' => new ProductResource($product),
            'message' => 'Product created successfully'
        ], 201);
    }
}
```

## ⚙️ Configuration

`clean-arch:install` writes `config/clean-architecture.php`. You can also publish it on its own:

```bash
php artisan vendor:publish --tag=clean-architecture-config
```

### 📁 Directories

`directories` is read by `clean-arch:install`, which creates the structure at those paths, and by `clean-arch:make-arch-rules`, which turns them into the namespaces and the class sets of the generated config. The layer namespaces are derived from the same values, so `app/Core/Domain` with a `default_namespace` of `Acme` becomes `Acme\Core\Domain`.

```php
'default_namespace' => 'App',

'directories' => [
    'domain' => 'app/Domain',
    'application' => 'app/Application',
    'infrastructure' => 'app/Infrastructure',
],
```

When the config file is not published, the defaults above are used.

### ✅ Validation rules

Every rule can be turned off by name under `validation.rules`. A rule set to `false` is left out of the config written by `clean-arch:make-arch-rules`. All of them are enabled by default, so a project without a published config file keeps the full set.

```php
'validation' => [
    'rules' => [
        'domain_no_application_imports' => true,
        'domain_no_infrastructure_imports' => true,
        'application_no_infrastructure_imports' => true,
        'no_observers_in_domain' => true,
        'no_jobs_in_infrastructure' => true,
        'no_commands_in_infrastructure' => true,
    ],
],
```

`no_commands_in_infrastructure` is the most likely candidate for opting out. A console command is an input adapter, much like an HTTP controller, and keeping it in `Application` forces the Application layer to depend on `Illuminate\Console`. Turn the rule off if you prefer `Infrastructure/Console/Commands`.

### 📝 Custom validation messages

`validation.custom_messages` controls whether `clean-arch:make-domain` generates the `messages()` method in the form requests it creates. It defaults to `true`.

```php
'validation' => [
    'custom_messages' => false,
],
```

Set it to `false` and the generated `Create*Request` and `Update*Request` classes will omit the `messages()` method entirely. The default `rules()` and `authorize()` methods are unaffected, and the output remains valid PHP either way.

Note the two keys live under `validation` but serve different purposes. The `rules` subgroup is read by `clean-arch:make-arch-rules`, while `custom_messages` is read by `clean-arch:make-domain` at generation time. They are kept together so that a single published config file is the only place to look.

### 🏗️ Optional base classes

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

## 🛠️ Development

This package uses several tools to maintain code quality:

### 🔧 Code Quality Tools

- **🎨 Laravel Pint** - Code formatting and style fixing
- **🔍 PHPStan** - Static analysis for finding bugs
- **🧪 PEST** - Modern testing framework built on PHPUnit
- **🎭 Orchestra Testbench** - Laravel package testing

### 📜 Available Scripts

```bash
# 🧪 Run tests
composer test

# 📊 Run tests with coverage
composer test-coverage

# 🎨 Fix code style
composer format

# 👀 Check code style without fixing
composer format-test

# 🔍 Run static analysis
composer analyse

# ✨ Run all quality checks
composer quality
```

### 🚀 Development Setup

1. 📥 Clone the repository
2. 📦 Install dependencies: `composer install`
3. ✨ Run quality checks: `composer quality`

## 🤝 Contributing

Pull requests are welcome! 🎉 For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate and follow our [Contributing Guidelines](CONTRIBUTING.md). 📝

## 📄 License

[MIT](https://choosealicense.com/licenses/mit/) 📜 