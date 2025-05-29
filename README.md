# 🏗️ Laravel Clean Architecture Package

A Laravel package to easily implement Clean Architecture in your projects. 🚀

[![🧪 Tests](https://github.com/plin-code/laravel-clean-architecture/workflows/Tests/badge.svg)](https://github.com/plin-code/laravel-clean-architecture/actions)
[![🎨 Code Style](https://github.com/plin-code/laravel-clean-architecture/workflows/Code%20Style/badge.svg)](https://github.com/plin-code/laravel-clean-architecture/actions)
[![🔍 Static Analysis](https://github.com/plin-code/laravel-clean-architecture/workflows/Static%20Analysis/badge.svg)](https://github.com/plin-code/laravel-clean-architecture/actions)
[![📦 Latest Stable Version](https://poser.pugx.org/plin-code/laravel-clean-architecture/v/stable)](https://packagist.org/packages/plin-code/laravel-clean-architecture)
[![📄 License](https://poser.pugx.org/plin-code/laravel-clean-architecture/license)](https://packagist.org/packages/plin-code/laravel-clean-architecture)

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
- ⚡ Laravel 11.x or 12.x

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
- 🧪 Feature tests

### 🛠️ Available commands

- `clean-arch:install` - 🏗️ Install Clean Architecture structure
- `clean-arch:make-domain {name}` - 🆕 Create a complete new domain
- `clean-arch:make-action {name} {domain}` - ⚡ Create a new action
- `clean-arch:make-service {name}` - 🔧 Create a new service
- `clean-arch:make-controller {name}` - 🌐 Create a new controller
- `clean-arch:generate-package {name} {vendor}` - 📦 Generate a new package

### 📂 Generated structure

```
app/
├── Application/
│   ├── Actions/
│   │   └── Users/
│   │       ├── CreateUserAction.php
│   │       ├── UpdateUserAction.php
│   │       ├── DeleteUserAction.php
│   │       └── GetByIdUserAction.php
│   └── Services/
│       └── UserService.php
├── Domain/
│   └── Users/
│       ├── User.php
│       ├── Enums/
│   │   └── UserStatus.php
│   └── Events/
│       ├── UserCreated.php
│       ├── UserUpdated.php
│       └── UserDeleted.php
└── Infrastructure/
    └── API/
        ├── Controllers/
        │   └── UsersController.php
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

- **🎯 Domain Layer**: Does not depend on any other layer
- **⚡ Application Layer**: Depends only on Domain Layer
- **🏗️ Infrastructure Layer**: Depends on Application and Domain Layers

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

The configuration file `config/clean-architecture.php` allows you to customize:

- 🏷️ Default namespace
- 📁 Directory paths
- ✅ Validation options
- 📊 Logging settings

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