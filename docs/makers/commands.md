# Maker Commands Reference

Complete reference for all 19 maker commands in Hexagonal Maker Bundle.

---

## Quick Reference

| Command | Layer | What it generates |
|---------|-------|-------------------|
| [make:hexagonal:entity](#entity) | Domain | Pure PHP entities + YAML mapping |
| [make:hexagonal:value-object](#value-object) | Domain | Immutable value objects |
| [make:hexagonal:exception](#exception) | Domain | Business exceptions |
| [make:hexagonal:repository](#repository) | Domain + Infra | Repository port + Doctrine adapter |
| [make:hexagonal:command](#command) | Application | CQRS commands + handlers |
| [make:hexagonal:query](#query) | Application | CQRS queries + handlers + responses |
| [make:hexagonal:use-case](#use-case) | Application | Use cases (application services) |
| [make:hexagonal:input](#input) | Application | Input DTOs with validation |
| [make:hexagonal:controller](#controller) | UI | Web controllers |
| [make:hexagonal:form](#form) | UI | Symfony forms |
| [make:hexagonal:cli-command](#cli-command) | UI | Console commands |
| [make:hexagonal:domain-event](#domain-event) | Domain | Domain events |
| [make:hexagonal:event-subscriber](#event-subscriber) | App/Infra | Event subscribers |
| [make:hexagonal:message-handler](#message-handler) | Infrastructure | Async message handlers |
| [make:hexagonal:use-case-test](#use-case-test) | Tests | Use case tests |
| [make:hexagonal:controller-test](#controller-test) | Tests | Controller tests |
| [make:hexagonal:cli-command-test](#cli-command-test) | Tests | CLI command tests |
| [make:hexagonal:test-config](#test-config) | Config | Test configuration setup |
| [make:hexagonal:crud](#crud) | All | Complete CRUD module (30+ files) |

---

## Domain Layer

### Entity

Generate a pure domain entity with Doctrine YAML mapping.

```bash
bin/console make:hexagonal:entity <module> <name>
```

**Example:**

```bash
bin/console make:hexagonal:entity user/account User
```

**Generated:**
- `Domain/Model/User.php` - Pure PHP entity
- `Infrastructure/Persistence/Doctrine/Orm/Mapping/User.orm.yml` - Doctrine mapping

**Options:**

```bash
# With repository
--with-repository

# With ID value object
--with-id-vo

# Combined
bin/console make:hexagonal:entity blog/post Post --with-repository --with-id-vo
```

---

### Value Object

Generate an immutable value object.

```bash
bin/console make:hexagonal:value-object <module> <name>
```

**Example:**

```bash
bin/console make:hexagonal:value-object user/account Email
```

**Generated:**
- `Domain/ValueObject/Email.php` - Immutable readonly class

**Template:**

```php
<?php

declare(strict_types=1);

namespace App\User\Account\Domain\ValueObject;

final readonly class Email
{
    private string $value;

    public function __construct(string $value)
    {
        // Validation logic
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
```

---

### Exception

Generate a domain exception for business rule violations.

```bash
bin/console make:hexagonal:exception <module> <name>
```

**Example:**

```bash
bin/console make:hexagonal:exception user/account InvalidEmailException
```

**Generated:**
- `Domain/Exception/InvalidEmailException.php`

---

### Repository

Generate repository interface (Port) and Doctrine implementation (Adapter).

```bash
bin/console make:hexagonal:repository <module> <entity>
```

**Example:**

```bash
bin/console make:hexagonal:repository user/account User
```

**Generated:**
- `Domain/Port/UserRepositoryInterface.php` - Interface (Port)
- `Infrastructure/Persistence/Doctrine/DoctrineUserRepository.php` - Implementation (Adapter)

---

## Application Layer

### Command

Generate CQRS command for write operations.

```bash
bin/console make:hexagonal:command <module> <name> [options]
```

**Example:**

```bash
bin/console make:hexagonal:command blog/post create
```

**Generated:**
- `Application/Create/CreateCommand.php` - Command DTO
- `Application/Create/CreateCommandHandler.php` - Handler with `#[AsMessageHandler]`

**Options:**

```bash
# With factory pattern
--factory

# With tests
--with-tests

# Combined
bin/console make:hexagonal:command user/account register --factory --with-tests
```

**With Factory Generated:**
- `Application/Register/RegisterCommand.php`
- `Application/Register/RegisterCommandHandler.php`
- `Application/Register/UserFactory.php` - Factory for creating entities

---

### Query

Generate CQRS query for read operations.

```bash
bin/console make:hexagonal:query <module> <name>
```

**Example:**

```bash
bin/console make:hexagonal:query blog/post find-by-id
```

**Generated:**
- `Application/FindById/FindByIdQuery.php` - Query DTO
- `Application/FindById/FindByIdQueryHandler.php` - Handler
- `Application/FindById/FindByIdResponse.php` - Response DTO

---

### Use Case

Generate a use case (application service).

```bash
bin/console make:hexagonal:use-case <module> <name>
```

**Example:**

```bash
bin/console make:hexagonal:use-case blog/post PublishPost
```

**Generated:**
- `Application/UseCase/PublishPostUseCase.php`

**Options:**

```bash
# With test
--with-test

bin/console make:hexagonal:use-case blog/post CreatePost --with-test
```

---

### Input

Generate input DTO with validation.

```bash
bin/console make:hexagonal:input <module> <name>
```

**Example:**

```bash
bin/console make:hexagonal:input blog/post CreatePostInput
```

**Generated:**
- `Application/Input/CreatePostInput.php` - DTO with Symfony Validator constraints

---

## UI Layer

### Controller

Generate web controller.

```bash
bin/console make:hexagonal:controller <module> <name> <route>
```

**Example:**

```bash
bin/console make:hexagonal:controller blog/post CreatePost /posts/new
```

**Generated:**
- `UI/Http/Web/Controller/CreatePostController.php`

**Options:**

```bash
# With complete workflow (Form + UseCase + Command + Input)
--with-workflow

bin/console make:hexagonal:controller blog/post CreatePost /posts/new --with-workflow
```

**With Workflow Generated:**
- Controller
- Form
- UseCase
- Command + Handler
- Input DTO

---

### Form

Generate Symfony form type.

```bash
bin/console make:hexagonal:form <module> <name>
```

**Example:**

```bash
bin/console make:hexagonal:form blog/post Post
```

**Generated:**
- `UI/Http/Web/Form/PostType.php`

**Options:**

```bash
# With command workflow
--with-command --action=Create

bin/console make:hexagonal:form blog/post Post --with-command --action=Create
```

**With Command Generated:**
- Form
- Command + Handler
- Input DTO

---

### CLI Command

Generate console command.

```bash
bin/console make:hexagonal:cli-command <module> <name> <command-name>
```

**Example:**

```bash
bin/console make:hexagonal:cli-command blog/post CreatePost app:post:create
```

**Generated:**
- `UI/Cli/CreatePostCommand.php`

**Options:**

```bash
# With use case workflow
--with-use-case

bin/console make:hexagonal:cli-command blog/post CreatePost app:post:create --with-use-case
```

---

## Infrastructure Layer

### Message Handler

Generate async message handler.

```bash
bin/console make:hexagonal:message-handler <module> <name>
```

**Example:**

```bash
bin/console make:hexagonal:message-handler user/account SendWelcomeEmail
```

**Generated:**
- `Infrastructure/Messaging/Handler/SendWelcomeEmailHandler.php`

**Options:**

```bash
# With message class
--with-message

bin/console make:hexagonal:message-handler user/account SendEmail --with-message
```

---

## Events

### Domain Event

Generate domain event.

```bash
bin/console make:hexagonal:domain-event <module> <name>
```

**Example:**

```bash
bin/console make:hexagonal:domain-event order/payment OrderPlaced
```

**Generated:**
- `Domain/Event/OrderPlacedEvent.php` - Immutable event

**Options:**

```bash
# With subscriber
--with-subscriber

bin/console make:hexagonal:domain-event order/payment OrderPlaced --with-subscriber
```

---

### Event Subscriber

Generate event subscriber.

```bash
bin/console make:hexagonal:event-subscriber <module> <name> --layer=<application|infrastructure>
```

**Example:**

```bash
# Application layer (business workflow)
bin/console make:hexagonal:event-subscriber order/payment OrderPlaced --layer=application

# Infrastructure layer (technical concerns)
bin/console make:hexagonal:event-subscriber shared/logging Exception --layer=infrastructure
```

---

## Tests

### Use Case Test

Generate use case test.

```bash
bin/console make:hexagonal:use-case-test <module> <name>
```

**Example:**

```bash
bin/console make:hexagonal:use-case-test blog/post CreatePost
```

**Generated:**
- `tests/Blog/Post/Application/CreatePost/CreatePostTest.php` - KernelTestCase

---

### Controller Test

Generate controller test.

```bash
bin/console make:hexagonal:controller-test <module> <name> <route>
```

**Example:**

```bash
bin/console make:hexagonal:controller-test blog/post CreatePost /posts/new
```

**Generated:**
- `tests/Blog/Post/UI/Http/Web/Controller/CreatePostControllerTest.php` - WebTestCase

---

### CLI Command Test

Generate CLI command test.

```bash
bin/console make:hexagonal:cli-command-test <module> <name> <command-name>
```

**Example:**

```bash
bin/console make:hexagonal:cli-command-test blog/post CreatePost app:post:create
```

**Generated:**
- `tests/Blog/Post/UI/Cli/CreatePostCommandTest.php` - CommandTester

---

## Configuration

### Test Config

Generate test environment configuration.

```bash
bin/console make:hexagonal:test-config
```

**Generated:**
- `config/packages/test/doctrine.yaml` - Test database config
- `.env.test` - Test environment variables

---

## Rapid Development

### CRUD

Generate complete CRUD module.

```bash
bin/console make:hexagonal:crud <module> <entity> [options]
```

**Example:**

```bash
bin/console make:hexagonal:crud blog/post Post --with-tests --with-id-vo
```

**Generated (30+ files):**

**Domain:**
- Entity
- Repository Interface
- ID ValueObject (with `--with-id-vo`)

**Infrastructure:**
- Doctrine Repository
- YAML Mapping

**Application:**
- 5 Use Cases (Create, Update, Delete, Get, List)
- 5 Commands + Handlers
- 5 Input DTOs

**UI:**
- 5 Controllers
- 1 Form

**Tests (with `--with-tests`):**
- 5 Use Case Tests
- 5 Controller Tests

**Routes:**
- `GET /posts` - List
- `GET /posts/{id}` - Show
- `GET /posts/new` - Create form
- `POST /posts/new` - Submit
- `GET /posts/{id}/edit` - Edit form
- `POST /posts/{id}/edit` - Update
- `DELETE /posts/{id}/delete` - Delete

---

## Options Summary

| Option | Available For | Description |
|--------|---------------|-------------|
| `--with-repository` | Entity | Generate repository with entity |
| `--with-id-vo` | Entity, CRUD | Generate ID value object |
| `--factory` | Command | Generate factory pattern |
| `--with-tests` | Command, UseCase, CRUD | Generate tests |
| `--with-use-case` | CLI Command | Generate use case workflow |
| `--with-workflow` | Controller | Generate complete workflow |
| `--with-command` | Form | Generate command workflow |
| `--with-message` | Message Handler | Generate message class |
| `--with-subscriber` | Domain Event | Generate event subscriber |
| `--layer` | Event Subscriber | Choose application or infrastructure |
| `--action` | Form | Specify action (Create, Update, etc.) |

---

## Module Path Format

All commands use the module path format: `<context>/<module>`

**Examples:**
- `user/account` → `src/User/Account/`
- `blog/post` → `src/Blog/Post/`
- `order/payment` → `src/Order/Payment/`

**Flexibility:**
- `module/user/account` → `src/Module/User/Account/`
- `shared/common` → `src/Shared/Common/`

---

## Next Steps

- [**Quick Start**](../getting-started/quick-start.md) - Build your first module
- [**Examples**](../examples/user-registration.md) - Real-world usage
- [**Architecture Guide**](../ARCHITECTURE.md) - Understand the patterns
