# Development Guide

Guide for developers contributing to Hexagonal Maker Bundle.

---

## Development Environment

### Requirements

- PHP 8.1+
- Composer
- Git

### Setup

```bash
# Clone repository
git clone https://github.com/ahmed-bhs/hexagonal-maker-bundle
cd hexagonal-maker-bundle

# Install dependencies
composer install
```

---

## Running Tests

```bash
# All tests
vendor/bin/phpunit

# With coverage
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage

# Specific test
vendor/bin/phpunit tests/Unit/Maker/MakeEntityTest.php
```

---

## Code Quality

### PHPStan (Static Analysis)

```bash
vendor/bin/phpstan analyze

# Level 8 (strictest)
vendor/bin/phpstan analyze --level=8
```

### Code Style

```bash
# Check PSR-12 compliance
vendor/bin/php-cs-fixer fix --dry-run

# Fix automatically
vendor/bin/php-cs-fixer fix
```

---

## Project Structure

```
src/
├── Maker/               # Maker command classes
│   ├── MakeEntity.php
│   ├── MakeCommand.php
│   └── ...
├── Generator/           # Code generators
├── Template/            # File templates
└── HexagonalMakerBundle.php
```

---

## Creating a New Maker

See full guide in repository documentation.

---

## Submitting Changes

1. Create feature branch
2. Make changes
3. Add tests
4. Run quality checks
5. Submit PR

---

For detailed instructions, see the main [Contributing Guide](overview.md).
