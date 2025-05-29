# Contributing

Thank you for considering contributing to Laravel Clean Architecture! We welcome contributions from everyone.

## Development setup

1. Fork the repository
2. Clone your fork: `git clone https://github.com/your-username/laravel-clean-architecture.git`
3. Create a new branch: `git checkout -b feature/your-feature-name`
4. Install dependencies: `composer install`

## Code quality

We maintain high code quality standards using several tools:

### Code formatting with Laravel Pint

```bash
# Check code style
composer format-test

# Fix code style
composer format
```

### Static analysis with PHPStan

```bash
# Run static analysis
composer analyse
```

### Running tests with PEST

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage
```

### All quality checks

```bash
# Run all quality checks (formatting, analysis, tests)
composer quality
```

## Code style

- Follow PSR-12 coding standards
- Use Laravel Pint for code formatting
- Write meaningful commit messages
- Add tests for new features
- Update documentation when needed

## Testing with PEST

- Write tests using PEST's modern syntax
- Use descriptive test names with `it('does something', function() {})`
- Group related tests using `describe()` blocks when appropriate
- Use PEST's expectation API: `expect($value)->toBe($expected)`

## Pull request process

1. Ensure all tests pass (`composer test`)
2. Ensure code style is correct (`composer format-test`)
3. Ensure static analysis passes (`composer analyse`)
4. Update the README if needed
5. Submit your pull request

## Testing

- Write tests for any new functionality using PEST
- Ensure all existing tests continue to pass
- Aim for high test coverage
- Use PEST's fluent expectation syntax

## Documentation

- Update README.md if you change functionality
- Add PHPDoc comments to new methods
- Update CHANGELOG.md for significant changes

## Questions?

If you have any questions, please open an issue or start a discussion.

Thank you for contributing! 