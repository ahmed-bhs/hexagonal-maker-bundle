# Quick Start

Get started with Hexagonal Maker Bundle in 2 minutes.

---

## Installation

```bash
composer require ahmed-bhs/hexagonal-maker-bundle --dev
```

The bundle auto-registers via Symfony Flex. If not using Flex, add to `config/bundles.php`:

```php
return [
    // ...
    AhmedBhs\HexagonalMakerBundle\HexagonalMakerBundle::class => ['dev' => true],
];
```

---

## First Module: User Registration

Let's build a complete User Registration module with all hexagonal layers.

### Step 1: Domain Layer (Pure Business Logic)

```bash
# Create User entity (pure PHP, no Doctrine annotations)
bin/console make:hexagonal:entity user/account User

# Create Email value object
bin/console make:hexagonal:value-object user/account Email

# Create business exception
bin/console make:hexagonal:exception user/account InvalidEmailException
```

**Generated:**
```
src/User/Account/
├── Domain/
│   ├── Model/
│   │   └── User.php                    # Pure PHP entity
│   ├── ValueObject/
│   │   └── Email.php                   # Immutable value object
│   └── Exception/
│       └── InvalidEmailException.php   # Business exception
```

### Step 2: Repository (Port + Adapter)

```bash
# Generate repository interface + Doctrine implementation
bin/console make:hexagonal:repository user/account User
```

**Generated:**
```
src/User/Account/
├── Domain/Port/
│   └── UserRepositoryInterface.php           # Interface (Port)
└── Infrastructure/Persistence/Doctrine/
    ├── DoctrineUserRepository.php            # Implementation (Adapter)
    └── Orm/Mapping/
        └── User.orm.yml                      # Doctrine YAML mapping
```

### Step 3: Application Layer (Use Cases)

```bash
# Generate registration command with factory
bin/console make:hexagonal:command user/account register --factory

# Generate query to find user
bin/console make:hexagonal:query user/account find-by-id
```

**Generated:**
```
src/User/Account/Application/
├── Register/
│   ├── RegisterCommand.php           # Command DTO
│   ├── RegisterCommandHandler.php    # Handler with business logic
│   └── UserFactory.php               # Factory for creating users
└── FindById/
    ├── FindByIdQuery.php             # Query DTO
    ├── FindByIdQueryHandler.php      # Query handler
    └── FindByIdResponse.php          # Response DTO
```

### Step 4: UI Layer (Controllers)

```bash
# Generate web controller
bin/console make:hexagonal:controller user/account RegisterUser /users/register

# Generate Symfony form
bin/console make:hexagonal:form user/account User
```

**Generated:**
```
src/User/Account/UI/
└── Http/Web/
    ├── Controller/
    │   └── RegisterUserController.php   # Web controller
    └── Form/
        └── UserType.php                 # Symfony form
```

---

## Configure Doctrine Mapping

Add YAML mapping configuration to `config/packages/doctrine.yaml`:

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'

    orm:
        auto_generate_proxy_classes: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            UserAccount:
                is_bundle: false
                type: yml
                dir: '%kernel.project_dir%/src/User/Account/Infrastructure/Persistence/Doctrine/Orm/Mapping'
                prefix: 'App\User\Account\Domain\Model'
                alias: UserAccount
```

---

## Complete the Implementation

### 1. Edit User Entity

```php
// src/User/Account/Domain/Model/User.php
<?php

declare(strict_types=1);

namespace App\User\Account\Domain\Model;

use App\User\Account\Domain\ValueObject\Email;

final class User
{
    private string $id;
    private Email $email;
    private string $passwordHash;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        Email $email,
        string $passwordHash
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    // Business methods...
    public function changeEmail(Email $newEmail): void
    {
        $this->email = $newEmail;
    }
}
```

### 2. Complete Email Value Object

```php
// src/User/Account/Domain/ValueObject/Email.php
<?php

declare(strict_types=1);

namespace App\User\Account\Domain\ValueObject;

use App\User\Account\Domain\Exception\InvalidEmailException;

final readonly class Email
{
    private string $value;

    public function __construct(string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException("Invalid email: $value");
        }

        $this->value = strtolower($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

### 3. Complete Doctrine Mapping

```yaml
# src/User/Account/Infrastructure/Persistence/Doctrine/Orm/Mapping/User.orm.yml
App\User\Account\Domain\Model\User:
    type: entity
    repositoryClass: App\User\Account\Infrastructure\Persistence\Doctrine\DoctrineUserRepository
    table: user

    id:
        id:
            type: string
            length: 36

    fields:
        email:
            type: string
            length: 180
            unique: true
            # Email VO will be stored as string

        passwordHash:
            type: string
            length: 255
            column: password_hash

        createdAt:
            type: datetime_immutable
            column: created_at
```

### 4. Implement Handler

```php
// src/User/Account/Application/Register/RegisterCommandHandler.php
<?php

declare(strict_types=1);

namespace App\User\Account\Application\Register;

use App\User\Account\Domain\Port\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegisterCommandHandler
{
    public function __construct(
        private UserFactory $factory,
        private UserRepositoryInterface $repository,
    ) {
    }

    public function __invoke(RegisterCommand $command): void
    {
        // Use factory to create user
        $user = $this->factory->create(
            email: $command->email,
            password: $command->password
        );

        // Save via repository
        $this->repository->save($user);
    }
}
```

---

## Generate Database Schema

```bash
# Validate mapping
bin/console doctrine:schema:validate

# Generate migration
bin/console doctrine:migrations:diff

# Execute migration
bin/console doctrine:migrations:migrate
```

---

## Test Your Module

### Option 1: Web Interface

Create a controller action:

```php
// src/User/Account/UI/Http/Web/Controller/RegisterUserController.php
use Symfony\Component\Messenger\MessageBusInterface;

#[Route('/users/register', name: 'user_register', methods: ['GET', 'POST'])]
public function register(Request $request, MessageBusInterface $commandBus): Response
{
    $form = $this->createForm(UserType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $data = $form->getData();

        $command = new RegisterCommand(
            email: $data['email'],
            password: $data['password']
        );

        $commandBus->dispatch($command);

        return $this->redirectToRoute('user_success');
    }

    return $this->render('user/register.html.twig', [
        'form' => $form,
    ]);
}
```

### Option 2: CLI Command

```bash
bin/console make:hexagonal:cli-command user/account RegisterUser app:user:register
```

Then test:

```bash
bin/console app:user:register --email=test@example.com --password=secret
```

---

## What's Next?

You've built a complete hexagonal module! Next steps:

1. [**Add more use cases**](first-module.md#adding-more-use-cases) - Update, Delete, List
2. [**Add tests**](../examples/testing.md) - Unit and integration tests
3. [**Add validation**](first-module.md#validation) - Input DTOs with constraints
4. [**Add domain events**](first-module.md#domain-events) - Event-driven architecture
5. [**Generate complete CRUD**](../examples/crud-module.md) - Use the CRUD maker

---

## Quick CRUD Alternative

Want to skip manual generation? Use the CRUD maker:

```bash
bin/console make:hexagonal:crud user/account User --with-tests --with-id-vo
```

This generates everything in one command:
- Entity + Repository + ValueObjects
- 5 Use Cases (Create, Update, Delete, Get, List)
- 5 Controllers + Form
- All tests

[Learn more about CRUD maker →](../examples/crud-module.md)

---

## Troubleshooting

### Doctrine mapping not found

**Error:** `Class "App\User\Account\Domain\Model\User" sub class of "X" is not a valid entity or mapped super class.`

**Solution:** Add mapping configuration to `config/packages/doctrine.yaml` (see Configure Doctrine Mapping above)

### Entity not persisted

**Error:** `A new entity was found through the relationship...`

**Solution:** Add `cascade: ['persist']` in your YAML mapping or call `$entityManager->persist()` explicitly

### Service autowiring failed

**Error:** `Cannot autowire service "X": argument "$repository" references interface "UserRepositoryInterface" but no such service exists.`

**Solution:** Configure service alias in `config/services.yaml`:

```yaml
services:
    App\User\Account\Domain\Port\UserRepositoryInterface:
        class: App\User\Account\Infrastructure\Persistence\Doctrine\DoctrineUserRepository
```

Or use `_defaults: autowire: true` with `bind` for automatic resolution.

---

## Next Steps

- [**Installation Guide**](installation.md) - Detailed installation and configuration
- [**First Module Tutorial**](first-module.md) - Step-by-step guide with full code
- [**Maker Commands Reference**](../makers/commands.md) - All 19 commands documented
- [**Architecture Guide**](../ARCHITECTURE.md) - Deep dive into hexagonal architecture
