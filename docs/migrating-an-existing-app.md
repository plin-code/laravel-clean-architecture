# Moving an existing app into the layers

`clean-arch:install` has two options that move code Laravel already generated into the layers. `--user-in-domain` moves the `User` model alone. `--migrate-app` moves the folders the layers replace, and `User` with them when it is the only model. The last section lists what to take care of when the app is already in production.

## Moving the `User` model into the Domain

```bash
php artisan clean-arch:install --user-in-domain
composer dump-autoload
```

Laravel keeps `User` in `app/Models`, outside the directories the generated phparkitect rules check. This option moves it into the Domain layer of a **fresh** app, following `directories.domain` and `generation.model_directory`, so with the defaults you get `app/Domain/Users/Models/User.php` declaring `App\Domain\Users\Models\User`.

It also:
- keeps `User` an `Authenticatable`, it does not extend `BaseModel` (whose `SoftDeletes` needs a `deleted_at` column the `users` table does not have)
- adds `newFactory()` to the model and a `$model` property to `database/factories/UserFactory.php`, since the factory resolver derives both names from the old namespace
- rewrites `App\Models\User` in the PHP files of `app/`, `bootstrap/` (without `bootstrap/cache`), `config/`, `database/`, `routes/` and `tests/`, in code and inside strings, and prints every file it changed
- registers `Relation::morphMap(['user' => User::class])` in `AppServiceProvider::boot()`, so polymorphic `*_type` columns store `user` instead of a namespace
- renames the `App.Models.User.{id}` broadcast channel in `routes/channels.php`
- removes `app/Models` once it is empty, and `app/Http` when all it holds is the default base controller and no file in `app/` or `routes/` uses it

It does not touch `vendor/`, `node_modules/` or `storage/`, and it does not migrate data. If `resources/js` or `resources/ts` listens on `App.Models.User.{id}`, the command warns and names the files. Those Echo listeners have to be renamed by hand.

The command refuses to change anything when `app/Models` holds other models (pass `--force` to move `User` anyway), when both the source and the destination exist, or when neither does. For an app that is already running in production, read [Moving `User` into the Domain in an existing app](#moving-user-into-the-domain-in-an-existing-app) first.

Laravel's own generators (`make:model`, `make:controller`, `make:request`) recreate `app/Models` and `app/Http`. After this option, use the `clean-arch:make-*` commands instead.

## Migrating an existing app into the layers

```bash
php artisan clean-arch:install --migrate-app
composer dump-autoload
```

Without options, `clean-arch:install` creates the layers next to the folders Laravel ships with and leaves those folders alone, so an app ends up with both `app/Http/Controllers` and `app/Infrastructure/Http/Controllers`. `--migrate-app` moves the code of those folders into the layers, rewrites the namespaces and every reference, and prints each file it moved, changed or removed. It works on a fresh app, on an app created from a starter kit (the React, Vue and Livewire kits ship folders such as `app/Http/Controllers/Settings`, `app/Http/Middleware`, `app/Http/Requests/Settings`, `app/Actions/Fortify` and `app/Concerns`), and on an app where some of these folders do not exist, which are skipped.

| From | To |
|---|---|
| `app/Http/Controllers` | `Infrastructure/Http/Controllers` |
| `app/Http/Middleware` | `Infrastructure/Http/Middleware` |
| `app/Http/Requests` | `Infrastructure/Http/Requests` |
| `app/Http/Resources` | `Infrastructure/Http/Resources` |
| `app/Console/Commands` | `Infrastructure/Console/Commands` |
| `app/Mail`, `app/Notifications`, `app/Observers`, `app/Exports` | `Infrastructure/Mail`, `Infrastructure/Notifications`, `Infrastructure/Observers`, `Infrastructure/Exports` |
| `app/Actions`, `app/Services`, `app/Jobs`, `app/Listeners`, `app/Concerns` | `Application/Actions`, `Application/Services`, `Application/Jobs`, `Application/Listeners`, `Application/Concerns` |
| `app/Models/User.php` | `Domain/Users/Models/User.php`, as with `--user-in-domain`, when `User` is the only model |

The destinations follow `directories`, so with `app/Core/Infrastructure` the controllers land in `app/Core/Infrastructure/Http/Controllers`. Subfolders are kept: `app/Http/Controllers/Settings/ProfileController.php` becomes `app/Infrastructure/Http/Controllers/Settings/ProfileController.php` declaring `App\Infrastructure\Http\Controllers\Settings`.

It also:
- deletes the empty abstract `Controller` Laravel ships, so its subclasses extend the base controller `clean-arch:install` writes and the app keeps a single base controller
- rewrites the old namespaces in the PHP files of `app/`, `bootstrap/` (without `bootstrap/cache`), `config/`, `database/`, `routes/` and `tests/`, in code, inside strings and in fully qualified names
- adds `->withCommands([...])` for moved console commands and `->withEvents(discover: [...])` for moved listeners to `bootstrap/app.php`, since Laravel only discovers them in `app/Console/Commands` and `app/Listeners`. When `bootstrap/app.php` has no `return Application::configure(...)` line, or already calls `withEvents()` (a second call would replace its discovery paths), it prints the exact lines to add instead
- rewrites [Wayfinder](https://github.com/laravel/wayfinder) imports in `resources/js` and `resources/ts`, `@/actions/App/Http/Controllers/...` becoming `@/actions/App/Infrastructure/Http/Controllers/...`. The generated `actions`, `routes` and `wayfinder` folders are left to Wayfinder: run `php artisan wayfinder:generate --with-form` (or the next Vite build) to regenerate them
- removes the folders it emptied, then lists every PHP file still outside the layers

What stays where it is, and why:
- `app/Providers`: `bootstrap/providers.php` registers the providers there, and `validation.outside_layers_allowed` allows it by default.
- `app/Models` when it holds other models besides `User`: models of one namespace name each other without imports, factories and policies are found from the model namespace, and polymorphic columns store the class name. Moving them is a decision per model, so all of them stay, `User` included, and the command says so. Move them by hand (the layout `clean-arch:make-domain` generates is a reference), or move `User` alone with `--user-in-domain --force`.
- `app/Policies`: policy auto discovery looks in `App\Policies`. Keep it, and add `'Policies'` to `validation.outside_layers_allowed`.
- Every other folder, such as `app/Events`, `app/Enums`, `app/Rules`, `app/Livewire`, `app/View` or `app/Filament`: Livewire, Blade components and Filament find their classes by namespace or by path, so these folders are listed and left for you to decide.

Before moving anything the command checks every destination. When one already exists, including the base classes `clean-arch:install` writes (`Http/Controllers/Controller.php`, `Http/Requests/BaseRequest.php`, `Actions/BaseAction.php`, `Services/BaseService.php`), it prints every conflict and exits 1 without changing a file. A base controller with code of its own is such a conflict. `--force` replaces the destination with the file of the app, and, as always with `--force`, also overwrites the files `clean-arch:install` generates. `vendor/`, `node_modules/` and `storage/` are never touched.

What it does not do:
- It does not rewrite paths written as strings, such as `app_path('Http/Controllers')` or `__DIR__.'/../app/Console/Commands'`, nor files that are not PHP outside the Wayfinder imports (Blade views, `phpunit.xml`, `phpstan.neon`).
- It does not sort the `use` statements it rewrites. Run your formatter afterwards.
- It does not migrate data. A job or a listener already serialized in the queue names its old class, so drain the queue before deploying, as for [moving `User`](#moving-user-into-the-domain-in-an-existing-app).
- It does not run `composer dump-autoload`, Wayfinder or your tests. Run them, then turn on [`no_classes_outside_layers`](architecture-validation.md#no-classes-outside-the-layers) to keep the app inside the layers.

## Moving `User` into the Domain in an existing app

`clean-arch:install --user-in-domain` is written for a fresh app. On an app that already has data, queued jobs and third party packages, renaming the `User` class is a data migration as much as a code change. The table below lists what breaks and what to do about it.

| What breaks | Why | Remedy |
|---|---|---|
| `User::factory()` | The factory name is derived from the model namespace, so the resolver looks for `Database\Factories\Domain\Users\Models\UserFactory` | Add `newFactory()` to the model, returning `\Database\Factories\UserFactory::new()`, and a `protected $model = User::class` to the factory |
| Polymorphic `*_type` columns | Rows hold the old class name, `App\Models\User` | Register `Relation::morphMap(['user' => User::class])`, then run a migration that updates every `*_type` column holding `App\Models\User` to `user`. Do it in the same deploy as the code change |
| Broadcast notification channel | The private channel is named after the notifiable class, `private-App.Models.User.{id}` | Rename the channel in `routes/channels.php` and in the Echo listeners, or keep the old name by returning it from `receivesBroadcastNotificationsOn()` on the model until the frontend catches up |
| Jobs already queued | A serialized job holds the old class name and fails to unserialize | Drain the queue before deploying, or keep a class alias for one release |
| Package config naming the model | Filament, Cashier, Sanctum, Permission and others store the FQCN in their own config or tables | Search `config/` for `App\Models\User` after the move, and check any package table that stores a model class |

Two more notes:

- `enforceMorphMap()` is stricter than `morphMap()`, it throws on any polymorphic model that is not mapped. It is the right call once every polymorphic model of the app is in the map, and a source of exceptions while packages such as an activity log or a media library still register their own.
- Laravel's generators recreate `app/Models` and `app/Http`. Use the `clean-arch:make-*` commands once the move is done.
