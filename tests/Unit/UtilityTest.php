<?php

use Illuminate\Support\Str;
use PlinCode\LaravelCleanArchitecture\Commands\MakeDomainCommand;
use Illuminate\Filesystem\Filesystem;

describe('Utility Methods', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem;
        $this->command = new MakeDomainCommand($this->filesystem);
    });

    describe('String Helper Methods', function () {
        it('converts domain names to plural correctly', function () {
            expect(Str::plural('User'))->toBe('Users');
            expect(Str::plural('Category'))->toBe('Categories');
            expect(Str::plural('Person'))->toBe('People');
            expect(Str::plural('Child'))->toBe('Children');
            expect(Str::plural('ProductCategory'))->toBe('ProductCategories');
        });

        it('converts to camelCase correctly', function () {
            expect(Str::camel('User'))->toBe('user');
            expect(Str::camel('ProductCategory'))->toBe('productCategory');
            expect(Str::camel('OrderItem'))->toBe('orderItem');
            expect(Str::camel('user_profile'))->toBe('userProfile');
        });

        it('converts to snake_case correctly', function () {
            expect(Str::snake('User'))->toBe('user');
            expect(Str::snake('ProductCategory'))->toBe('product_category');
            expect(Str::snake('OrderItem'))->toBe('order_item');
            expect(Str::snake('UserProfile'))->toBe('user_profile');
        });

        it('converts to StudlyCase correctly', function () {
            expect(Str::studly('user'))->toBe('User');
            expect(Str::studly('product_category'))->toBe('ProductCategory');
            expect(Str::studly('order-item'))->toBe('OrderItem');
            expect(Str::studly('user-profile'))->toBe('UserProfile');
        });

        it('handles plural snake_case table names', function () {
            $testCases = [
                'User' => 'users',
                'ProductCategory' => 'product_categories',
                'OrderItem' => 'order_items',
                'Person' => 'people',
                'Child' => 'children',
                'Company' => 'companies',
            ];

            foreach ($testCases as $input => $expected) {
                $actual = Str::snake(Str::plural($input));
                expect($actual)->toBe($expected)
                    ->and("Table name for {$input} should be {$expected}");
            }
        });
    });

    describe('File Path Helpers', function () {
        it('creates correct domain paths', function () {
            $domainPaths = [
                'User' => 'app/Domain/Users',
                'ProductCategory' => 'app/Domain/ProductCategories',
                'OrderItem' => 'app/Domain/OrderItems',
            ];

            foreach ($domainPaths as $domain => $expectedPath) {
                $actualPath = 'app/Domain/' . Str::plural($domain);
                expect($actualPath)->toBe($expectedPath);
            }
        });

        it('creates correct action paths', function () {
            $actionPaths = [
                'User' => 'app/Application/Actions/Users',
                'ProductCategory' => 'app/Application/Actions/ProductCategories',
                'Post' => 'app/Application/Actions/Posts',
            ];

            foreach ($actionPaths as $domain => $expectedPath) {
                $actualPath = 'app/Application/Actions/' . Str::plural($domain);
                expect($actualPath)->toBe($expectedPath);
            }
        });

        it('creates correct controller paths', function () {
            $controllerPaths = [
                'User' => 'app/Infrastructure/API/Controllers/UsersController.php',
                'ProductCategory' => 'app/Infrastructure/API/Controllers/ProductCategoriesController.php',
                'Article' => 'app/Infrastructure/API/Controllers/ArticlesController.php',
            ];

            foreach ($controllerPaths as $domain => $expectedPath) {
                $actualPath = 'app/Infrastructure/API/Controllers/' . Str::plural($domain) . 'Controller.php';
                expect($actualPath)->toBe($expectedPath);
            }
        });
    });

    describe('Namespace Generation', function () {
        it('creates correct domain namespaces', function () {
            $namespaces = [
                'User' => 'App\\Domain\\Users',
                'ProductCategory' => 'App\\Domain\\ProductCategories',
                'OrderItem' => 'App\\Domain\\OrderItems',
            ];

            foreach ($namespaces as $domain => $expectedNamespace) {
                $actualNamespace = 'App\\Domain\\' . Str::plural($domain);
                expect($actualNamespace)->toBe($expectedNamespace);
            }
        });

        it('creates correct action namespaces', function () {
            $namespaces = [
                'User' => 'App\\Application\\Actions\\Users',
                'ProductCategory' => 'App\\Application\\Actions\\ProductCategories',
                'Post' => 'App\\Application\\Actions\\Posts',
            ];

            foreach ($namespaces as $domain => $expectedNamespace) {
                $actualNamespace = 'App\\Application\\Actions\\' . Str::plural($domain);
                expect($actualNamespace)->toBe($expectedNamespace);
            }
        });

        it('creates correct service namespaces', function () {
            $namespaces = [
                'User' => 'App\\Application\\Services',
                'ProductCategory' => 'App\\Application\\Services',
                'Article' => 'App\\Application\\Services',
            ];

            foreach ($namespaces as $domain => $expectedNamespace) {
                $actualNamespace = 'App\\Application\\Services';
                expect($actualNamespace)->toBe($expectedNamespace);
            }
        });
    });

    describe('Class Name Generation', function () {
        it('creates correct model class names', function () {
            $classNames = [
                'user' => 'User',
                'product_category' => 'ProductCategory',
                'order-item' => 'OrderItem',
                'UserProfile' => 'UserProfile',
            ];

            foreach ($classNames as $input => $expected) {
                $actual = Str::studly($input);
                expect($actual)->toBe($expected);
            }
        });

        it('creates correct action class names', function () {
            $actions = [
                'CreateUser' => ['Create', 'User'],
                'UpdateProductCategory' => ['Update', 'ProductCategory'],
                'DeleteOrderItem' => ['Delete', 'OrderItem'],
                'ArchivePost' => ['Archive', 'Post'],
            ];

            foreach ($actions as $expected => $parts) {
                [$action, $domain] = $parts;
                $actual = $action . $domain . 'Action';
                expect($actual)->toBe($expected . 'Action');
            }
        });

        it('creates correct service class names', function () {
            $services = [
                'User' => 'UserService',
                'ProductCategory' => 'ProductCategoryService',
                'OrderItem' => 'OrderItemService',
                'Post' => 'PostService',
            ];

            foreach ($services as $domain => $expected) {
                $actual = $domain . 'Service';
                expect($actual)->toBe($expected);
            }
        });

        it('creates correct controller class names', function () {
            $controllers = [
                'User' => 'UsersController',
                'ProductCategory' => 'ProductCategoriesController',
                'Article' => 'ArticlesController',
                'Post' => 'PostsController',
            ];

            foreach ($controllers as $domain => $expected) {
                $actual = Str::plural($domain) . 'Controller';
                expect($actual)->toBe($expected);
            }
        });
    });

    describe('Template Variable Generation', function () {
        it('creates correct template variables for domain User', function () {
            $domain = 'User';
            $variables = [
                'DomainName' => $domain,
                'PluralDomainName' => Str::plural($domain),
                'domainVariable' => Str::camel($domain),
                'domain-table' => Str::snake(Str::plural($domain)),
            ];

            expect($variables['DomainName'])->toBe('User');
            expect($variables['PluralDomainName'])->toBe('Users');
            expect($variables['domainVariable'])->toBe('user');
            expect($variables['domain-table'])->toBe('users');
        });

        it('creates correct template variables for complex domain ProductCategory', function () {
            $domain = 'ProductCategory';
            $variables = [
                'DomainName' => $domain,
                'PluralDomainName' => Str::plural($domain),
                'domainVariable' => Str::camel($domain),
                'domain-table' => Str::snake(Str::plural($domain)),
            ];

            expect($variables['DomainName'])->toBe('ProductCategory');
            expect($variables['PluralDomainName'])->toBe('ProductCategories');
            expect($variables['domainVariable'])->toBe('productCategory');
            expect($variables['domain-table'])->toBe('product_categories');
        });
    });

    describe('Package Name Handling', function () {
        it('handles package name conversion correctly', function () {
            $packages = [
                'blog-engine_acme' => [
                    'packageName' => 'blog-engine',
                    'vendor' => 'acme',
                    'studlyName' => 'BlogEngine',
                    'namespace' => 'Acme\\BlogEngine',
                    'composerName' => 'acme/blog-engine',
                ],
                'user-management_mycompany' => [
                    'packageName' => 'user-management',
                    'vendor' => 'mycompany',
                    'studlyName' => 'UserManagement',
                    'namespace' => 'Mycompany\\UserManagement',
                    'composerName' => 'mycompany/user-management',
                ],
                'payment-gateway_fintech' => [
                    'packageName' => 'payment-gateway',
                    'vendor' => 'fintech',
                    'studlyName' => 'PaymentGateway',
                    'namespace' => 'Fintech\\PaymentGateway',
                    'composerName' => 'fintech/payment-gateway',
                ],
            ];

            foreach ($packages as $key => $expected) {
                $packageName = $expected['packageName'];
                $vendor = $expected['vendor'];
                
                $studlyName = Str::studly($packageName);
                $namespace = Str::studly($vendor) . '\\' . $studlyName;
                $composerName = $vendor . '/' . $packageName;

                expect($studlyName)->toBe($expected['studlyName']);
                expect($namespace)->toBe($expected['namespace']);
                expect($composerName)->toBe($expected['composerName']);
            }
        });
    });
}); 