# Installation

Complete installation and configuration guide for Hexagonal Maker Bundle.

---

## Requirements

- **PHP:** 8.1 or higher
- **Symfony:** 6.4+ or 7.x
- **Composer:** Latest version

---

## Step 1: Install via Composer

```bash
composer require ahmed-bhs/hexagonal-maker-bundle --dev
```

The `--dev` flag ensures the bundle is only installed in development environment.

---

## Step 2: Enable the Bundle

### With Symfony Flex (Automatic)

If you're using Symfony Flex, the bundle is auto-registered. Skip to Step 3.

### Without Symfony Flex (Manual)

Add the bundle to `config/bundles.php`:

```php
<?php

return [
    // ... other bundles ...
    AhmedBhs\HexagonalMakerBundle\HexagonalMakerBundle::class => ['dev' => true],
];
```

---

## Step 3: Configure Doctrine (Required)

Hexagonal Maker Bundle uses **YAML mapping** to keep domain entities pure (no annotations).

### 3.1 Create Doctrine Configuration

Edit `config/packages/doctrine.yaml`:

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        # Use utf8mb4 for full Unicode support
        charset: utf8mb4
        default_table_options:
            charset: utf8mb4
            collate: utf8mb4_unicode_ci

    orm:
        auto_generate_proxy_classes: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true

        # IMPORTANT: Add YAML mappings for each module
        mappings: {}
            # Example mappings will be added per module
            # See section 3.2 below
```

### 3.2 Add Module Mappings

Each time you create a new module (e.g., `User/Account`), add its mapping:

```yaml
doctrine:
    orm:
        mappings:
            # User Account Module
            UserAccount:
                is_bundle: false
                type: yml
                dir: '%kernel.project_dir%/src/User/Account/Infrastructure/Persistence/Doctrine/Orm/Mapping'
                prefix: 'App\User\Account\Domain\Model'
                alias: UserAccount

            # Blog Post Module (example)
            BlogPost:
                is_bundle: false
                type: yml
                dir: '%kernel.project_dir%/src/Blog/Post/Infrastructure/Persistence/Doctrine/Orm/Mapping'
                prefix: 'App\Blog\Post\Domain\Model'
                alias: BlogPost

            # Add more modules as needed...
```

**Pattern:**
- `dir`: Path to YAML mapping files (Infrastructure layer)
- `prefix`: Namespace of domain entities (Domain layer)
- `alias`: Short alias for DQL queries

---

## Step 4: Configure Symfony Messenger (Optional)

For CQRS commands and queries, configure Symfony Messenger.

Edit `config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        default_bus: command.bus

        buses:
            command.bus:
                middleware:
                    - validation
                    - doctrine_transaction

            query.bus:
                middleware:
                    - validation

            event.bus:
                default_middleware: allow_no_handlers
                middleware:
                    - validation

        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'

        routing:
            # Route commands to command.bus
            'App\*\Application\*\*Command': command.bus

            # Route queries to query.bus
            'App\*\Application\*\*Query': query.bus

            # Route async messages to async transport
            'App\*\Infrastructure\Messaging\*': async
```

### Configure Transport (Optional - for async)

In `.env`:

```env
###> symfony/messenger ###
# For async processing (optional)
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=false
# Or use RabbitMQ, Redis, etc.
# MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2f/messages
###< symfony/messenger ###
```

---

## Step 5: Configure Bundle (Optional)

Create `config/packages/hexagonal_maker.yaml` to customize skeleton templates:

```yaml
hexagonal_maker:
    # Directory where custom skeleton templates are stored
    skeleton_dir: '%kernel.project_dir%/config/skeleton'

    # Root source directory (default: 'src')
    root_dir: 'src'

    # Root namespace (default: 'App')
    root_namespace: 'App'
```

---

## Step 6: Verify Installation

Check that maker commands are available:

```bash
bin/console list make:hexagonal
```

**Expected output:**

```
make:hexagonal:cli-command         Generate a CLI command
make:hexagonal:command             Generate a CQRS command
make:hexagonal:controller          Generate a web controller
make:hexagonal:controller-test     Generate a controller test
make:hexagonal:crud                Generate complete CRUD module
make:hexagonal:domain-event        Generate a domain event
make:hexagonal:entity              Generate a domain entity
make:hexagonal:event-subscriber    Generate an event subscriber
make:hexagonal:exception           Generate a domain exception
make:hexagonal:form                Generate a Symfony form
make:hexagonal:input               Generate an input DTO
make:hexagonal:message-handler     Generate a message handler
make:hexagonal:query               Generate a CQRS query
make:hexagonal:repository          Generate a repository
make:hexagonal:test-config         Generate test configuration
make:hexagonal:use-case            Generate a use case
make:hexagonal:use-case-test       Generate a use case test
make:hexagonal:value-object        Generate a value object
```

---

## Directory Structure

After installation, your project will generate code in this structure:

```
src/
├── Module/              # Recommended: organize by bounded context
│   ├── User/
│   │   └── Account/     # Sub-domain module
│   │       ├── Domain/
│   │       │   ├── Model/
│   │       │   ├── ValueObject/
│   │       │   ├── Exception/
│   │       │   └── Port/
│   │       ├── Application/
│   │       │   ├── Command/
│   │       │   ├── Query/
│   │       │   └── UseCase/
│   │       ├── Infrastructure/
│   │       │   └── Persistence/
│   │       │       └── Doctrine/
│   │       │           ├── DoctrineUserRepository.php
│   │       │           └── Orm/Mapping/
│   │       │               └── User.orm.yml
│   │       └── UI/
│   │           ├── Http/Web/
│   │           │   ├── Controller/
│   │           │   └── Form/
│   │           └── Cli/
│   └── Blog/
│       └── Post/        # Another module
└── Shared/              # Shared kernel (optional)
    ├── Domain/
    ├── Application/
    └── Infrastructure/
```

**Or simpler flat structure:**

```
src/
├── User/Account/        # Module without "Module/" prefix
├── Blog/Post/
└── Shared/
```

---

## Configuration Files Summary

### Essential Files

```
config/
├── packages/
│   ├── doctrine.yaml              ← REQUIRED: Add YAML mappings
│   ├── messenger.yaml             ← Optional: CQRS buses
│   └── hexagonal_maker.yaml       ← Optional: Custom templates
└── services.yaml                  ← Service configuration
```

### Recommended Services Configuration

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Make controllers public
    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
            - '../src/*/Domain/Model/'  # Exclude entities
            - '../src/Kernel.php'

    # Repository interfaces autowiring
    App\User\Account\Domain\Port\UserRepositoryInterface:
        class: App\User\Account\Infrastructure\Persistence\Doctrine\DoctrineUserRepository

    # OR use bind for automatic interface resolution
    _defaults:
        bind:
            $commandBus: '@messenger.bus.command'
            $queryBus: '@messenger.bus.query'
            $eventBus: '@messenger.bus.event'
```

---

## Database Setup

### 1. Configure Database URL

In `.env`:

```env
DATABASE_URL="mysql://user:password@127.0.0.1:3306/hexagonal_app?serverVersion=8.0"
```

### 2. Create Database

```bash
bin/console doctrine:database:create
```

### 3. Generate Migrations

After creating entities with makers:

```bash
# Validate mapping
bin/console doctrine:schema:validate

# Generate migration
bin/console doctrine:migrations:diff

# Execute migration
bin/console doctrine:migrations:migrate
```

---

## Testing Setup (Optional)

### 1. Install PHPUnit

```bash
composer require --dev phpunit/phpunit symfony/phpunit-bridge
```

### 2. Configure Test Environment

```bash
# Generate test configuration
bin/console make:hexagonal:test-config
```

This creates:
- `config/packages/test/doctrine.yaml` (test database)
- `.env.test` (test environment variables)

### 3. Create Test Database

```bash
bin/console doctrine:database:create --env=test
bin/console doctrine:schema:create --env=test
```

---

## Next Steps

Installation complete! Now:

1. [**Quick Start**](quick-start.md) - Generate your first module
2. [**First Module Tutorial**](first-module.md) - Complete step-by-step guide
3. [**Maker Commands**](../makers/commands.md) - Learn all 19 commands

---

## Troubleshooting

### Bundle not found in `bin/console list`

**Solution:** Clear cache and check `config/bundles.php`

```bash
bin/console cache:clear
```

### Doctrine mapping errors

**Error:** `The class 'X' was not found in the chain configured namespaces`

**Solution:** Ensure YAML mapping is configured in `config/packages/doctrine.yaml` for your module

### Autowiring fails for repositories

**Error:** `Cannot autowire service "X": argument "$repository" references interface but no such service exists`

**Solution:** Add interface alias in `config/services.yaml`:

```yaml
services:
    App\User\Account\Domain\Port\UserRepositoryInterface:
        class: App\User\Account\Infrastructure\Persistence\Doctrine\DoctrineUserRepository
```

### Commands not generating files

**Solution:** Check permissions on `src/` directory:

```bash
chmod -R 755 src/
```

---

## Uninstallation

To remove the bundle:

```bash
composer remove ahmed-bhs/hexagonal-maker-bundle
```

Then remove from `config/bundles.php` if added manually.
