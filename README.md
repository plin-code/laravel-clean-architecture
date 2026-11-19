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

## 📚 Documentation

This README covers installation, the commands and the structure they generate. Three guides go deeper:

- [Moving an existing app into the layers](docs/migrating-an-existing-app.md): `--user-in-domain`, `--migrate-app`, and what to check on an app already in production.
- [Architecture validation](docs/architecture-validation.md): the phparkitect rules, the autoload guard and the baseline.
- [Configuration](docs/configuration.md): every key of `config/clean-architecture.php`.

## 🎯 Usage

### 🏗️ Installing Clean Architecture structure

```bash
php artisan clean-arch:install
```

This command will create:
- 📁 Folder structure for Domain, Application and Infrastructure layers
- 🧩 Base classes (`BaseModel`, `Controller`, `BaseRequest`, `BaseAction`, `BaseService`) and three exceptions in `Infrastructure/Exceptions`
- ⚙️ Configuration file, `config/clean-architecture.php`
- 📖 Documentation, `CLEAN_ARCHITECTURE.md` in the project root

Files that already exist are skipped, so the command is safe to run again. Pass `--force` to overwrite them.

#### 👤 Moving the `User` model into the Domain

```bash
php artisan clean-arch:install --user-in-domain
composer dump-autoload
```

Laravel keeps `User` in `app/Models`, outside the directories the generated phparkitect rules check. This option moves it into the Domain layer of a **fresh** app (`app/Domain/Users/Models/User.php` with the defaults) and fixes the factory, the references to the class, the morph map and the broadcast channel. See [Moving the `User` model into the Domain](docs/migrating-an-existing-app.md#moving-the-user-model-into-the-domain) for everything it changes and when it refuses to run.

#### 🚚 Migrating an existing app into the layers

```bash
php artisan clean-arch:install --migrate-app
composer dump-autoload
```

Without options, `clean-arch:install` creates the layers next to the folders Laravel ships with and leaves those folders alone. `--migrate-app` moves the code of `app/Http`, `app/Console/Commands`, `app/Actions`, `app/Services`, `app/Jobs`, `app/Listeners` and the other folders the layers replace, rewrites the namespaces and every reference, and prints each file it moved, changed or removed. When a destination already exists it changes nothing and exits 1. See [Migrating an existing app into the layers](docs/migrating-an-existing-app.md#migrating-an-existing-app-into-the-layers) for the full map, what stays where it is, and what the command does not do.

### 🆕 Creating a new domain

```bash
php artisan clean-arch:make-domain Product
```

This command will generate:
- 🏛️ Domain model with events
- 📊 Status enums
- 🔔 Domain events (Created, Updated, Deleted)
- ⚡ Actions (Create, Update, Delete, GetById, List)
- 🌐 API Controller
- 📝 Form Requests (Create, Update)
- 📤 API Resource
- 🗃️ Database migration
- 🏭 Model factory in `database/factories`, which the model names in `newFactory()`
- 🧪 Feature tests

After generating the core files, `make-domain` prompts interactively for optional components. You can choose to also generate an Observer, Listener, Job, Mail, Notification, and Export for the domain. Each prompt can be answered independently, so you only generate what your domain needs.

`make-domain` does not touch your routes. When it finishes it prints the `Route::apiResource()` line for the generated controller, which the generated feature test calls under `/api`. Add it to `routes/api.php`. A fresh Laravel app has no such file: `php artisan install:api` creates it and registers it in `bootstrap/app.php`, and it also installs Sanctum.

Do not generate a `User` domain on an app that still has Laravel's own `User`: the model, the factory and the `users` migration already exist. Move that model with [`--user-in-domain`](#-moving-the-user-model-into-the-domain) instead.

### 🧩 Generating single classes

Each class `make-domain` writes can also be generated on its own. With the default configuration:

| Command | File written |
|---|---|
| `clean-arch:make-action PublishArticle Article` | `app/Application/Actions/Articles/PublishArticleAction.php` |
| `clean-arch:make-service Pricing` | `app/Application/Services/PricingService.php` |
| `clean-arch:make-controller Article` | `app/Infrastructure/Http/Controllers/Api/ArticlesController.php` |
| `clean-arch:make-controller Report --web` | `app/Infrastructure/Http/Controllers/Web/ReportController.php` |
| `clean-arch:make-observer Article Article` | `app/Infrastructure/Observers/Articles/ArticleObserver.php` |
| `clean-arch:make-listener Article` | `app/Application/Listeners/ArticleEventListener.php` |
| `clean-arch:make-job Article` | `app/Application/Jobs/ProcessArticleJob.php` |
| `clean-arch:make-mail Article` | `app/Infrastructure/Mail/ArticleMail.php` |
| `clean-arch:make-notification Article` | `app/Infrastructure/Notifications/ArticleNotification.php` |
| `clean-arch:make-export Article` | `app/Infrastructure/Exports/ArticleExport.php` |

The second argument of `make-action` and `make-observer` is the domain the class belongs to. A suffix the name already carries is not doubled, so `make-mail ArticleMail` and `make-mail Article` write the same file.

`make-controller` generates an API controller unless `--web` is passed, and both when `--api` and `--web` are passed together. The API controller takes the plural name, the web one keeps the name as given. A name containing `/` or `\` is rejected: nested controller directories are not supported.

### ✅ Architecture validation

Architectural rules are enforced by [phparkitect](https://github.com/phparkitect/arkitect). The package does not depend on it and does not run it: it generates a configuration file from `config/clean-architecture.php`, and your project runs the tool.

```bash
composer require --dev phparkitect/phparkitect
php artisan clean-arch:make-arch-rules
vendor/bin/phparkitect check
```

`clean-arch:make-arch-rules` writes `phparkitect.php` in the project root, built from `directories`, `default_namespace` and `validation.rules`. It refuses to overwrite an existing file, so pass `--force` when you want to regenerate one. The generated file is ordinary PHP: once you need rules the package does not generate, edit it by hand and stop regenerating it.

The [architecture validation guide](docs/architecture-validation.md) covers the rules and how to turn them off, the autoload guard at the top of the generated file, and adopting the check on an existing codebase with a baseline.

### 🛠️ Available commands

- `clean-arch:install {--force} {--user-in-domain} {--migrate-app}`: 🏗️ Install Clean Architecture structure, optionally moving the `User` model into the Domain layer, or the code of the Laravel folders into the layers
- `clean-arch:make-domain {name} {--force} {--no-base}`: 🆕 Create a complete new domain
- `clean-arch:make-action {name} {domain} {--force} {--no-base}`: ⚡ Create a new action
- `clean-arch:make-service {name} {--force} {--no-base}`: 🔧 Create an empty service, for logic shared by several actions
- `clean-arch:make-controller {name} {--api} {--web} {--force}`: 🌐 Create a new controller
- `clean-arch:make-observer {name} {domain} {--force}`: 👁️ Create a new observer
- `clean-arch:make-listener {name} {--force}`: 👂 Create a new listener
- `clean-arch:make-job {name} {--force}`: ⏳ Create a new job
- `clean-arch:make-mail {name} {--force}`: 📧 Create a new mailable
- `clean-arch:make-notification {name} {--force}`: 🔔 Create a new notification
- `clean-arch:make-export {name} {--force}`: 📤 Create a new export
- `clean-arch:make-arch-rules {--force}`: 🛡️ Generate a phparkitect config from the configured rules
- `clean-arch:generate-package {name} {vendor} {--path=} {--force}`: 📦 Generate a new package in `packages/{vendor}/{name}`, or in `--path`

Every `make-*` command and `clean-arch:generate-package` follow the same rule for the files they write. A command that only ever writes one file (`make-action`, `make-service`, `make-controller`, `make-observer`, `make-listener`, `make-job`, `make-mail`, `make-notification`, `make-export`) refuses to overwrite an existing target, prints an error naming the path, and exits with a failure code, so a script or an AI agent rerunning a generator notices instead of losing hand written code. A command that writes several files (`make-domain`, `clean-arch:generate-package`) skips the ones that already exist and still writes the rest, printing a `Skipped:` line for each. Pass `--force` on any of them to overwrite instead.

### 📂 Project structure after `clean-arch:install`

```
app/
├── Domain/                          # Business logic (Eloquent models, enums, events)
│   └── Shared/                      # BaseModel
├── Application/                     # Use cases and orchestration
│   ├── Actions/
│   ├── Services/
│   ├── Jobs/
│   └── Listeners/
└── Infrastructure/                  # Framework adapters
    ├── Http/
    │   ├── Controllers/
    │   │   ├── Api/
    │   │   └── Web/
    │   ├── Middleware/
    │   ├── Requests/
    │   └── Resources/
    ├── Mail/
    ├── Notifications/
    ├── Observers/
    ├── Exports/
    ├── Validation/
    ├── Console/Commands/
    └── Exceptions/
```

Laravel only auto-discovers commands in `app/Console/Commands`, so register the commands of the Infrastructure layer in `bootstrap/app.php` (adjust the path if you changed `directories.infrastructure`):

```php
->withCommands([
    __DIR__.'/../app/Infrastructure/Console/Commands',
])
```

<a id="-generated-structure-after-clean-archmake-domain-user"></a>

### 📂 Generated structure after `clean-arch:make-domain Product`

The model sits in a `Models` subfolder by default. Set `generation.model_directory` to change or drop it, see [Model directory](docs/configuration.md#model-directory).

```
app/
├── Domain/
│   └── Products/
│       ├── Models/
│       │   └── Product.php
│       ├── Enums/
│       │   └── ProductStatus.php
│       └── Events/
│           ├── ProductCreated.php
│           ├── ProductUpdated.php
│           └── ProductDeleted.php
├── Application/
│   └── Actions/
│       └── Products/
│           ├── CreateProductAction.php
│           ├── UpdateProductAction.php
│           ├── DeleteProductAction.php
│           ├── GetByIdProductAction.php
│           └── ListProductsAction.php
└── Infrastructure/
    └── Http/
        ├── Controllers/
        │   └── Api/
        │       └── ProductsController.php
        ├── Requests/
        │   ├── CreateProductRequest.php
        │   └── UpdateProductRequest.php
        └── Resources/
            └── ProductResource.php
```

Outside `app/`, the command writes `database/factories/ProductFactory.php`, a `create_products_table` migration and `tests/Feature/Products/ProductsTest.php`.

## 🏛️ Clean Architecture Principles

This package implements Clean Architecture principles:

1. **🎯 Domain Layer**: Contains business logic and entities
2. **⚡ Application Layer**: Contains use cases and application logic
3. **🏗️ Infrastructure Layer**: Contains implementation details (controllers, database, etc.)

### 🔗 Dependencies

- **🎯 Domain Layer**: Does not depend on the Application or Infrastructure layers
- **⚡ Application Layer**: Depends only on Domain Layer
- **🏗️ Infrastructure Layer**: Depends on Application and Domain Layers

### 🔌 Crossing a layer with a contract

When an inner layer needs something an outer layer provides, it declares an interface and the outer layer implements it. The binding lives in a service provider, so the inner layer only ever sees the interface.

- **External service**: the interface sits in `App\Application\Contracts` (for example `PaymentGateway`), the adapter in `App\Infrastructure` (`StripePaymentGateway`). Switching provider means a new adapter and a new binding.
- **Domain asking the outside**: the domain needs an answer it does not own, such as a feature flag. The interface sits in the domain (`App\Domain\Orders\Contracts\DiscountsGate`), the implementation in `App\Application`.
- **Shared capability**: several models implement the same interface (`HasSlug`), so one service handles all of them.

```php
// AppServiceProvider::register()
$this->app->bind(PaymentGateway::class, StripePaymentGateway::class);
```

A class with a single implementation, used only inside its own layer, needs no contract.

### 🗄️ The Domain layer depends on Eloquent

This is a deliberate trade-off, and it is worth stating explicitly. `clean-arch:install` generates `App\Domain\Shared\BaseModel`, which extends `Illuminate\Database\Eloquent\Model`, and every model produced by `clean-arch:make-domain` extends it. The Domain layer is therefore free of Application and Infrastructure imports (that is what the generated rules enforce), but it is not free of the framework.

If you need a persistence agnostic domain, this package is not the right starting point.

### 🔒 Transactions

Generated actions do not open a transaction. When a use case has an invariant that only holds if several writes, or a read and a write, happen atomically, the action opens it itself (with `lockForUpdate()` when it reads before writing). Callers may still wrap it in a larger transaction, since Laravel nests them with savepoints. Mail, events and jobs go in `DB::afterCommit()`, so nothing leaves the system before the data is committed.

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
        private CreateProductAction $createProductAction
    ) {}

    public function store(CreateProductRequest $request): JsonResponse
    {
        $product = $this->createProductAction->execute($request->validated());
        
        return response()->json([
            'data' => new ProductResource($product),
            'message' => 'Product created successfully'
        ], 201);
    }
}
```

An action never receives the HTTP request. The controller passes `$request->validated()`, an array, and models already resolved. A readonly DTO is fine when several callers must normalise the same input.

### 📍 Classes without a generator

DTOs, policies, domain exceptions and Filament panels have no `clean-arch` command. Create them straight in their layer, not in a Laravel default folder to move them afterwards.

| Class | Where | How |
|---|---|---|
| Readonly DTO | `app/Application/DTOs` | `php artisan make:class Application/DTOs/RegistrationData` |
| Policy of a domain model | a `Policies` folder next to the model, `app/Domain/Articles/Policies` | `php artisan make:class Domain/Articles/Policies/ArticlePolicy` |
| Domain exception | an `Exceptions` folder next to the model, `app/Domain/Articles/Exceptions` | `php artisan make:class Domain/Articles/Exceptions/ArticleAlreadyPublishedException` |
| Filament resources, pages, widgets | `app/Infrastructure/Filament` | `make:filament-resource`, after pointing the panel at that folder |

Laravel's policy discovery walks up from the model namespace, so `App\Domain\Articles\Policies\ArticlePolicy` is found for `App\Domain\Articles\Models\Article` (and for `App\Domain\Articles\Article`) without registering anything. `make:policy` writes to `app/Policies`, outside the layers. A policy only imports domain classes, so it breaks no rule.

A domain exception, `App\Domain\Articles\Exceptions\ArticleAlreadyPublishedException`, states a broken business rule. Make it extend PHP's `DomainException` (or another SPL exception), so the domain still imports nothing from the other layers, and have an action throw it. Turning it into an HTTP response is the job of the infrastructure layer, for example with `->withExceptions()` in `bootstrap/app.php`. The `DomainException`, `BusinessLogicException` and `ValidationException` that `clean-arch:install` writes to `Infrastructure/Exceptions` cannot be thrown from an action or a model: importing them breaks `application_no_infrastructure_imports` or `domain_no_infrastructure_imports`.

A Filament panel is an input adapter like a controller. Point the panel provider at the infrastructure layer before generating anything:

```php
->discoverResources(in: app_path('Infrastructure/Filament/Resources'), for: 'App\\Infrastructure\\Filament\\Resources')
->discoverPages(in: app_path('Infrastructure/Filament/Pages'), for: 'App\\Infrastructure\\Filament\\Pages')
->discoverWidgets(in: app_path('Infrastructure/Filament/Widgets'), for: 'App\\Infrastructure\\Filament\\Widgets')
```

`make:filament-resource` then writes there by itself. Its `--resource-namespace` option is ignored for a namespace the panel does not discover: the command falls back to `app/Filament` and overwrites what is there. The panel provider itself stays in `app/Providers`.

<a id="&#xFE0F;-configuration-1"></a>

## ⚙️ Configuration

`clean-arch:install` writes `config/clean-architecture.php`. It skips any file that already exists, so editing the config or a generated base class is safe to keep across reinstalls. Pass `--force` to overwrite them instead. You can also publish the config on its own:

```bash
php artisan vendor:publish --tag=clean-architecture-config
```

| Key | Default | What it controls |
|---|---|---|
| `default_namespace` | `'App'` | Root namespace of the generated code |
| `directories` | `app/Domain`, `app/Application`, `app/Infrastructure` | Where the three layers live |
| `validation.rules` | all on, except `no_commands_in_infrastructure` and `no_classes_outside_layers` | The phparkitect rules `clean-arch:make-arch-rules` writes |
| `validation.application_infrastructure_allowed` | `[]` | Infrastructure namespaces the application layer may import |
| `validation.outside_layers_allowed` | `['Providers']` | Namespaces that may stay outside the layers |
| `validation.custom_messages` | `true` | Whether generated form requests get a `messages()` method |
| `generation.extend_base_classes` | `true` | Whether generated actions and services extend `BaseAction` and `BaseService` |
| `generation.model_directory` | `'Models'` | Subfolder of the domain model |
| `generation.job_prefix` | `'Process'` | Prefix of generated jobs |
| `generation.web_controller_path` | `'Http/Controllers/Web'` | Where `make-controller --web` writes |

Each key is explained in the [configuration guide](docs/configuration.md), the three `validation` keys that drive phparkitect in [Validation rules](docs/architecture-validation.md#validation-rules).

## 👤 Moving `User` into the Domain in an existing app

`clean-arch:install --user-in-domain` is written for a fresh app. On an app that already has data, queued jobs and third party packages, renaming the `User` class is a data migration as much as a code change: the factory, polymorphic `*_type` columns, broadcast channels, queued jobs and package configs all name the old class. [Moving `User` into the Domain in an existing app](docs/migrating-an-existing-app.md#moving-user-into-the-domain-in-an-existing-app) lists what breaks and the remedy for each.

## 🤖 AI guidelines (Laravel Boost)

This package ships AI guidelines and an agent skill for [Laravel Boost](https://github.com/laravel/boost), at the paths the Boost documentation gives package authors:

- `resources/boost/guidelines/core.blade.php`, a short always in context brief on the three layers, the dependency direction and the `clean-arch:*` commands.
- `resources/boost/skills/clean-architecture-development/SKILL.md`, loaded on demand, covering how to generate a domain, where each class belongs, and how to keep `phparkitect check` green.

Install Boost 2.5 or higher with `composer require laravel/boost --dev` then `php artisan boost:install`, selecting this package when Boost asks which third party guidelines and skills to install.

On an app that already has Boost installed, `php artisan boost:update` offers the newly detected package only when it runs in an interactive terminal. Run without interaction, or from a Composer script such as the `post-update-cmd` the Laravel skeleton ships, it skips the question and leaves the package out, so neither the guideline nor the skill is installed. In that case add the package to `packages` in `boost.json` yourself, then run the update again:

```json
"packages": [
    "plin-code/laravel-clean-architecture"
]
```

Boost then installs the guideline and the `clean-architecture-development` skill, and records the skill under `skills` in `boost.json` by itself. This was checked against Boost 2.10.

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
