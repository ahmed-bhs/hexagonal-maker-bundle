# Hexagonal Architecture - Complete Guide

## Table of Contents

1. [Overview](#1-overview)
2. [The 3 Layers](#2-the-3-layers)
3. [Ports vs Adapters](#3-ports-vs-adapters)
4. [CQRS Pattern](#4-cqrs-pattern)
5. [Testability](#5-testability)
6. [Directory Structure](#6-directory-structure)
7. [Best Practices](#7-best-practices)
8. [Favored Design Patterns](#8-favored-design-patterns)
   - 8.1 [Creational Patterns](#81-creational-patterns) - Factory, Builder, Singleton
   - 8.2 [Structural Patterns](#82-structural-patterns) - Adapter, Repository, DTO
   - 8.3 [Behavioral Patterns](#83-behavioral-patterns) - Strategy, Command, Observer
   - 8.4 [Other Patterns](#84-other-important-patterns) - Specification, Null Object
9. [Progressive Migration](#9-progressive-migration)
10. [Resources](#10-resources)

---

## 1. Overview

Hexagonal architecture (also called **Ports and Adapters**) is an architectural pattern that aims to **isolate business logic** from technical concerns (framework, database, external APIs, etc.).

### 1.1 Fundamental Principle

> **Dependencies always point inward** (towards the domain)

```mermaid
%%{init: {'theme':'base', 'themeVariables': { 'fontSize':'16px'}}}%%
graph LR
    Infra["🔌 Infrastructure<br/><small>Adapters</small>"]
    App["⚙️ Application<br/><small>Use Cases</small>"]
    Domain["💎 Domain<br/><small>Business Logic</small>"]

    Infra ==>|"depends on"| App
    App ==>|"depends on"| Domain

    style Domain fill:#C8E6C9,stroke:#2E7D32,stroke-width:4px,color:#000
    style App fill:#B3E5FC,stroke:#0277BD,stroke-width:3px,color:#000
    style Infra fill:#F8BBD0,stroke:#C2185B,stroke-width:3px,color:#000
```

---

## 2. The 3 Layers

### 2.1 Domain (Core - Hexagon)

**Responsibility:** Pure business logic, business rules, invariants

**Contains:**
- `Model/` - Entities with identity and lifecycle
- `ValueObject/` - Immutable objects defined by their values
- `Port/` - Interfaces (contracts) for external dependencies

**Strict Rules:**
- NO dependencies towards external layers
- NO Symfony/Doctrine annotations/attributes
- Pure PHP only
- Framework independent

**Entity Example:**
```php
namespace App\User\Account\Domain\Model;

final class User
{
    public function __construct(
        private UserId $id,
        private Email $email,
        private bool $isActive = false,
    ) {
    }

    // Business logic
    public function activate(): void
    {
        if ($this->isActive) {
            throw new UserAlreadyActiveException();
        }
        $this->isActive = true;
    }
}
```

**Value Object Example:**
```php
namespace App\User\Account\Domain\ValueObject;

final readonly class Email
{
    public function __construct(public string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException($value);
        }
    }
}
```

**Port (Interface) Example:**
```php
namespace App\User\Account\Domain\Port;

interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
}
```

### 2.2 Application (Orchestration)

**Responsibility:** Use cases, orchestration of business operations

**Contains:**
- `Command/` - CQRS commands (writes)
- `Query/` - CQRS queries (reads)
- Handlers - Orchestration logic

**Rules:**
- Depends on Domain only
- Uses Ports (interfaces)
- Coordinates operations
- Does NOT contain business logic

**Command:**
```php
namespace App\User\Account\Application\Register;

final readonly class RegisterCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
```

**Command Handler:**
```php
namespace App\User\Account\Application\Register;

use App\User\Account\Domain\Port\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegisterCommandHandler
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private PasswordHasherInterface $hasher,
    ) {
    }

    public function __invoke(RegisterCommand $command): void
    {
        // Orchestration only, no business logic
        $user = new User(
            id: UserId::generate(),
            email: new Email($command->email),
            password: $this->hasher->hash($command->password),
        );

        $this->repository->save($user);
    }
}
```

### 2.3 Infrastructure (Technical Details)

**Responsibility:** Concrete implementations, technical details

**Contains:**
- `Persistence/` - Adapters for persistence (Doctrine, etc.)
- `Messaging/` - Adapters for messaging
- `ExternalAPI/` - Adapters for external APIs

**Rules:**
- Implements Ports (Domain interfaces)
- Contains technical details
- Can depend on Domain and Application
- Uses Doctrine, HTTP clients, etc.

**Doctrine Adapter:**
```php
namespace App\User\Account\Infrastructure\Persistence\Doctrine;

use App\User\Account\Domain\Model\User;
use App\User\Account\Domain\Port\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }

    public function findById(UserId $id): ?User
    {
        return $this->em->find(User::class, $id->value);
    }
}
```

---

## 3. Ports vs Adapters

```mermaid
%%{init: {'theme':'base', 'themeVariables': { 'fontSize':'15px'}}}%%
graph LR
    subgraph Primary["🎮 Primary Adapters (Driving)"]
        HTTP["🌐 HTTP Controller"]
        CLI["⌨️ CLI Command"]
        GraphQL["📊 GraphQL Resolver"]
    end

    subgraph Core["💎 Core - Domain + Application"]
        UseCases["⚙️ Use Cases<br/><small>Handlers</small>"]
        Ports["🔗 Ports<br/><small>Interfaces</small>"]
    end

    subgraph Secondary["🔌 Secondary Adapters (Driven)"]
        Doctrine["🗄️ Doctrine<br/><small>Repository</small>"]
        Redis["⚡ Redis<br/><small>Cache</small>"]
        SMTP["📧 SMTP<br/><small>Mailer</small>"]
    end

    Primary ==>|"calls"| UseCases
    UseCases ==>|"uses"| Ports
    Secondary -.->|"🎯 implements"| Ports

    style Core fill:#C8E6C9,stroke:#2E7D32,stroke-width:4px,color:#000
    style Primary fill:#E1BEE7,stroke:#6A1B9A,stroke-width:3px,color:#000
    style Secondary fill:#F8BBD0,stroke:#C2185B,stroke-width:3px,color:#000
```

### 3.1 Port (Interface)

A **Port** is an interface defined in the **Domain** that represents a need.

**Types of Ports:**

1. **Primary Ports (Driving/Primary)** - What the application offers
   - Example: Use case handlers
   - Called by primary adapters (UI)

2. **Secondary Ports (Driven/Secondary)** - What the application requires
   - Example: `RepositoryInterface`, `EmailSenderInterface`
   - Implemented by secondary adapters (Infrastructure)

### 3.2 Adapter (Implementation)

An **Adapter** is a concrete implementation of a Port in the **Infrastructure**.

**Adapter Examples:**

```php
// Port (Domain)
interface UserRepositoryInterface
{
    public function save(User $user): void;
}

// Adapter 1 - Doctrine (Infrastructure)
class DoctrineUserRepository implements UserRepositoryInterface
{
    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }
}

// Adapter 2 - In Memory (Infrastructure/Tests)
class InMemoryUserRepository implements UserRepositoryInterface
{
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->getId()->value] = $user;
    }
}
```

---

## 4. CQRS Pattern

```mermaid
%%{init: {'theme':'base', 'themeVariables': { 'fontSize':'14px'}}}%%
graph TB
    UI["🎮 UI Layer<br/><small>Controller/CLI</small>"]

    subgraph Write["✍️ Write Side - Commands"]
        CMD["📝 Command<br/><small>RegisterUserCommand</small>"]
        CMDH["⚙️ Command Handler<br/><small>RegisterUserCommandHandler</small>"]
        WriteRepo["💾 Write Repository<br/><small>Save/Update/Delete</small>"]
    end

    subgraph Read["📖 Read Side - Queries"]
        QRY["🔍 Query<br/><small>FindUserQuery</small>"]
        QRYH["⚙️ Query Handler<br/><small>FindUserQueryHandler</small>"]
        ReadRepo["📚 Read Repository<br/><small>Find/List/Search</small>"]
        RESP["📋 Response<br/><small>FindUserResponse</small>"]
    end

    UI ==>|"dispatch"| CMD
    UI ==>|"dispatch"| QRY

    CMD ==> CMDH
    CMDH ==>|"uses"| WriteRepo

    QRY ==> QRYH
    QRYH ==>|"uses"| ReadRepo
    QRYH ==>|"returns"| RESP

    style Write fill:#FFCDD2,stroke:#C62828,stroke-width:3px,color:#000
    style Read fill:#B3E5FC,stroke:#0277BD,stroke-width:3px,color:#000
    style UI fill:#E1BEE7,stroke:#6A1B9A,stroke-width:3px,color:#000
```

### 4.1 Command (Write)

**Characteristics:**
- Intent to **modify state**
- Returns `void`
- Present tense naming: `RegisterUser`, `PublishArticle`

```php
final readonly class PublishArticleCommand
{
    public function __construct(
        public string $articleId,
        public \DateTimeImmutable $publishedAt,
    ) {
    }
}
```

### 4.2 Query (Read)

**Characteristics:**
- Intent to **read data**
- Returns a `Response`
- Descriptive naming: `FindUserById`, `ListArticles`

```php
final readonly class FindUserByIdQuery
{
    public function __construct(
        public string $userId,
    ) {
    }
}

final readonly class FindUserByIdResponse
{
    public function __construct(
        public string $id,
        public string $email,
        public bool $isActive,
    ) {
    }
}
```

### 4.3 Strict Separation

```php
// BAD - Returns a value
class RegisterUserCommand
{
    public function __invoke(RegisterCommand $cmd): User { ... }
}

// GOOD - Void only
class RegisterUserCommandHandler
{
    public function __invoke(RegisterCommand $cmd): void { ... }
}

// GOOD - Query returns data
class FindUserQueryHandler
{
    public function __invoke(FindUserQuery $q): FindUserResponse { ... }
}
```

---

## 5. Testability

Hexagonal architecture greatly facilitates testing:

```mermaid
graph TB
    subgraph Pyramid["Test Pyramid"]
        E2E[E2E Tests<br/>Slow - Few<br/>Full stack with DB]
        Integration[Integration Tests<br/>Medium - Moderate<br/>With Symfony Kernel]
        Unit[Unit Tests<br/>Fast - Many<br/>InMemory - Mocks]
    end

    subgraph Layers["Tested Layers"]
        UnitTests[Domain + Application<br/>Unit tests<br/>InMemoryRepository]
        IntTests[Handlers + Adapters<br/>Integration tests<br/>DoctrineRepository]
        E2ETests[UI → DB<br/>E2E tests<br/>Full journey]
    end

    Unit -.->|tests| UnitTests
    Integration -.->|tests| IntTests
    E2E -.->|tests| E2ETests

    style Unit fill:#90EE90,stroke:#333,stroke-width:2px
    style Integration fill:#87CEEB,stroke:#333,stroke-width:2px
    style E2E fill:#FFB6C1,stroke:#333,stroke-width:2px
```

### 5.1 Domain Testing (ultra fast)

```php
class UserTest extends TestCase
{
    public function testUserCanBeActivated(): void
    {
        $user = new User(
            id: UserId::generate(),
            email: new Email('test@example.com'),
        );

        $user->activate();

        $this->assertTrue($user->isActive());
    }
}
```

### 5.2 Application Testing with InMemory

```php
class RegisterCommandHandlerTest extends TestCase
{
    public function testUserIsRegistered(): void
    {
        $repository = new InMemoryUserRepository();
        $handler = new RegisterCommandHandler($repository);

        $command = new RegisterCommand(
            email: 'test@example.com',
            password: 'secret',
        );

        $handler($command);

        $this->assertCount(1, $repository->all());
    }
}
```

---

## 6. Directory Structure

```
src/
└── User/                          # Bounded Context
    └── Account/                   # Module
        ├── Application/           # Application Layer
        │   ├── Register/
        │   │   ├── RegisterCommand.php
        │   │   ├── RegisterCommandHandler.php
        │   │   └── AccountFactory.php
        │   └── Find/
        │       ├── FindQuery.php
        │       ├── FindQueryHandler.php
        │       └── FindResponse.php
        │
        ├── Domain/                # Domain Layer (Core)
        │   ├── Model/
        │   │   └── User.php
        │   ├── ValueObject/
        │   │   ├── Email.php
        │   │   └── UserId.php
        │   └── Port/
        │       └── UserRepositoryInterface.php
        │
        └── Infrastructure/        # Infrastructure Layer
            ├── Persistence/
            │   ├── Doctrine/
            │   │   ├── DoctrineUserRepository.php
            │   │   └── Mapping/
            │   │       └── User.orm.xml
            │   └── InMemory/
            │       └── InMemoryUserRepository.php
            └── Messaging/
                └── SymfonyMessengerAdapter.php
```

---

## 7. Best Practices

### 7.1 Pure Domain

```php
// BAD - Doctrine dependency
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User { }

// GOOD - Pure PHP
class User
{
    public function __construct(
        private UserId $id,
        private Email $email,
    ) {
    }
}
```

### 7.2 Immutable Value Objects

```php
// BAD - Mutable
class Email
{
    public string $value;

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}

// GOOD - Immutable with readonly
final readonly class Email
{
    public function __construct(
        public string $value,
    ) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException();
        }
    }
}
```

### 7.3 Ports in Domain

```php
// BAD - Adapter in Domain
namespace App\Domain;

use Doctrine\ORM\EntityManagerInterface;

class UserService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}
}

// GOOD - Port (interface) in Domain
namespace App\Domain\Port;

interface UserRepositoryInterface
{
    public function save(User $user): void;
}

namespace App\Application;

class RegisterHandler
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {}
}
```

### 7.4 Factories for Complex Creation

```php
final readonly class OrderFactory
{
    public function __construct(
        private IdGeneratorInterface $idGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(CreateOrderCommand $command): Order
    {
        return new Order(
            id: new OrderId($this->idGenerator->generate()),
            customerId: new CustomerId($command->customerId),
            items: $this->createOrderItems($command->items),
            createdAt: $this->clock->now(),
        );
    }
}
```

---

## 8. Favored Design Patterns

Hexagonal architecture naturally encourages the use of many proven design patterns. This section explores how hexagonal facilitates and favors these patterns.

```mermaid
graph TB
    subgraph Creational["Creational Patterns"]
        Factory[Factory Pattern]
        Builder[Builder Pattern]
        Singleton[Singleton Pattern]
    end

    subgraph Structural["Structural Patterns"]
        Adapter[Adapter Pattern]
        Repository[Repository Pattern]
        DTO[DTO Pattern]
    end

    subgraph Behavioral["Behavioral Patterns"]
        Strategy[Strategy Pattern]
        Observer[Observer Pattern]
        Command[Command Pattern]
    end

    subgraph Hexagonal["Hexagonal Architecture"]
        Domain[Domain Layer]
        App[Application Layer]
        Infra[Infrastructure Layer]
    end

    Factory -.->|creates| Domain
    Builder -.->|builds| Domain
    Adapter -.->|implements ports| Infra
    Repository -.->|abstracts persistence| Domain
    DTO -.->|transfers data| App
    Strategy -.->|implementation selection| Infra
    Command -.->|encapsulates intention| App

    style Creational fill:#FFD700,stroke:#333,stroke-width:2px
    style Structural fill:#87CEEB,stroke:#333,stroke-width:2px
    style Behavioral fill:#FFB6C1,stroke:#333,stroke-width:2px
    style Hexagonal fill:#90EE90,stroke:#333,stroke-width:2px
```

### 8.1 Creational Patterns

#### 8.1.1 Factory Pattern

**Why hexagonal favors it:**
- Creating complex entities often requires multiple Value Objects
- Validation and business logic must be centralized
- Domain must not depend on Infrastructure

**Example:**
```php
<?php

declare(strict_types=1);

namespace App\User\Account\Application\Register;

use App\User\Account\Domain\Model\User;
use App\User\Account\Domain\ValueObject\Email;
use App\User\Account\Domain\ValueObject\UserId;
use App\User\Account\Domain\ValueObject\HashedPassword;
use App\Shared\Domain\Service\IdGeneratorInterface;
use App\Shared\Domain\Service\PasswordHasherInterface;

/**
 * Factory Pattern - Creates complex Domain entities
 */
final readonly class UserFactory
{
    public function __construct(
        private IdGeneratorInterface $idGenerator,
        private PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function createFromCommand(RegisterCommand $command): User
    {
        return new User(
            id: new UserId($this->idGenerator->generate()),
            email: new Email($command->email),
            password: new HashedPassword(
                $this->passwordHasher->hash($command->password)
            ),
            createdAt: new \DateTimeImmutable(),
        );
    }
}
```

**Benefits in hexagonal:**
- Encapsulates complex creation logic
- Isolates dependencies (ID generator, hasher) from the entity
- Facilitates testing (mock the factory)

#### 8.1.2 Builder Pattern

**Usage:**
- Progressive construction of complex objects
- Configurations with many options

**Example:**
```php
<?php

declare(strict_types=1);

namespace App\Order\Domain\Builder;

use App\Order\Domain\Model\Order;
use App\Order\Domain\ValueObject\OrderId;
use App\Order\Domain\ValueObject\OrderItem;

/**
 * Builder Pattern - Progressive construction of an order
 */
final class OrderBuilder
{
    private ?OrderId $id = null;
    private ?string $customerId = null;
    private array $items = [];
    private ?string $shippingAddress = null;
    private ?string $billingAddress = null;

    public function withId(OrderId $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function forCustomer(string $customerId): self
    {
        $this->customerId = $customerId;
        return $this;
    }

    public function addItem(OrderItem $item): self
    {
        $this->items[] = $item;
        return $this;
    }

    public function withShippingAddress(string $address): self
    {
        $this->shippingAddress = $address;
        return $this;
    }

    public function build(): Order
    {
        if ($this->id === null || $this->customerId === null) {
            throw new \LogicException('Order must have id and customer');
        }

        return new Order(
            id: $this->id,
            customerId: $this->customerId,
            items: $this->items,
            shippingAddress: $this->shippingAddress,
            billingAddress: $this->billingAddress ?? $this->shippingAddress,
        );
    }
}

// Usage in a test
$order = (new OrderBuilder())
    ->withId(OrderId::generate())
    ->forCustomer('customer-123')
    ->addItem(new OrderItem('product-1', 2))
    ->addItem(new OrderItem('product-2', 1))
    ->withShippingAddress('123 Main St')
    ->build();
```

#### 8.1.3 Singleton Pattern (Port Registry)

**Limited but relevant use case:**
- Registry of available ports
- Global configuration

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Registry;

/**
 * Singleton - Registry of available adapters
 */
final class AdapterRegistry
{
    private static ?self $instance = null;
    private array $adapters = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register(string $portName, object $adapter): void
    {
        $this->adapters[$portName] = $adapter;
    }

    public function get(string $portName): object
    {
        return $this->adapters[$portName] ?? throw new \RuntimeException(
            "No adapter registered for port: {$portName}"
        );
    }
}
```

---

### 8.2 Structural Patterns

#### 8.2.1 Adapter Pattern (Core of Hexagonal)

**This is THE central pattern of hexagonal architecture!**

**Why:**
- Translates external interfaces to Domain Ports
- Allows changing infrastructure without touching the Domain

**Complete example:**
```php
<?php

declare(strict_types=1);

// PORT (Domain Layer)
namespace App\User\Account\Domain\Port;

use App\User\Account\Domain\Model\User;
use App\User\Account\Domain\ValueObject\UserId;

interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
    public function findByEmail(string $email): ?User;
}

// ADAPTER 1 - Doctrine (Infrastructure)
namespace App\User\Account\Infrastructure\Persistence\Doctrine;

use App\User\Account\Domain\Model\User;
use App\User\Account\Domain\Port\UserRepositoryInterface;
use App\User\Account\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Adapter Pattern - Adapts Doctrine to our Port
 */
final readonly class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function findById(UserId $id): ?User
    {
        return $this->entityManager->find(User::class, $id->value);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email.value' => $email]);
    }
}

// ADAPTER 2 - MongoDB (Infrastructure)
namespace App\User\Account\Infrastructure\Persistence\MongoDB;

use App\User\Account\Domain\Model\User;
use App\User\Account\Domain\Port\UserRepositoryInterface;
use MongoDB\Client;

/**
 * Adapter Pattern - Adapts MongoDB to the same Port
 */
final readonly class MongoUserRepository implements UserRepositoryInterface
{
    private const COLLECTION = 'users';

    public function __construct(
        private Client $mongoClient,
        private string $databaseName,
    ) {
    }

    public function save(User $user): void
    {
        $collection = $this->mongoClient
            ->selectDatabase($this->databaseName)
            ->selectCollection(self::COLLECTION);

        $collection->updateOne(
            ['_id' => $user->getId()->value],
            ['$set' => $this->serialize($user)],
            ['upsert' => true]
        );
    }

    // ... other methods
}

// ADAPTER 3 - InMemory (Tests)
namespace Tests\Unit\User\Account\Infrastructure\Persistence\InMemory;

use App\User\Account\Domain\Model\User;
use App\User\Account\Domain\Port\UserRepositoryInterface;

/**
 * Adapter Pattern - In-memory implementation for fast tests
 */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->getId()->value] = $user;
    }

    public function findById(UserId $id): ?User
    {
        return $this->users[$id->value] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->getEmail()->value === $email) {
                return $user;
            }
        }

        return null;
    }

    // Helper for tests
    public function clear(): void
    {
        $this->users = [];
    }
}
```

**Benefits:**
- **3 different adapters** for the **same port**
- The **Domain** never changes
- The **Application** never changes
- DB change = change the adapter in `services.yaml`

#### 8.2.2 Repository Pattern

**Natural integration:**
- Ports define Repository interfaces
- Adapters implement persistence

**Example with business methods:**
```php
<?php

declare(strict_types=1);

// Port with business methods
namespace App\Product\Domain\Port;

use App\Product\Domain\Model\Product;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;
    public function findById(string $id): ?Product;

    // Business methods (not technical)
    public function findActiveProducts(): array;
    public function findProductsLowInStock(int $threshold): array;
    public function findBestSellers(int $limit): array;
}

// Doctrine Adapter
namespace App\Product\Infrastructure\Persistence\Doctrine;

use App\Product\Domain\Model\Product;
use App\Product\Domain\Port\ProductRepositoryInterface;

final readonly class DoctrineProductRepository implements ProductRepositoryInterface
{
    public function findActiveProducts(): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function findProductsLowInStock(int $threshold): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.stock < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stock', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
```

#### 8.2.3 DTO Pattern (Data Transfer Object)

**Systematic usage in hexagonal:**

**1. Commands (Write) - Immutable DTOs**
```php
<?php

declare(strict_types=1);

namespace App\User\Account\Application\Register;

/**
 * DTO Pattern - Command = immutable DTO for writing
 */
final readonly class RegisterCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $firstName,
        public string $lastName,
    ) {
    }
}
```

**2. Query Response - Read DTOs**
```php
<?php

declare(strict_types=1);

namespace App\User\Account\Application\FindById;

/**
 * DTO Pattern - Response = DTO for reading
 */
final readonly class FindByIdResponse
{
    public function __construct(
        public string $id,
        public string $email,
        public string $firstName,
        public string $lastName,
        public bool $isActive,
        public string $createdAt,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->getId()->value,
            email: $user->getEmail()->value,
            firstName: $user->getFirstName(),
            lastName: $user->getLastName(),
            isActive: $user->isActive(),
            createdAt: $user->getCreatedAt()->format('Y-m-d H:i:s'),
        );
    }
}
```

**3. API DTOs - UI/Application Separation**
```php
<?php

declare(strict_types=1);

namespace App\UI\Http\DTO;

/**
 * DTO Pattern - DTO specific to HTTP API
 */
final readonly class RegisterUserRequest
{
    public function __construct(
        public string $email,
        public string $password,
        public string $first_name,  // snake_case for API
        public string $last_name,
    ) {
    }

    public function toCommand(): RegisterCommand
    {
        return new RegisterCommand(
            email: $this->email,
            password: $this->password,
            firstName: $this->first_name,  // Conversion to Domain
            lastName: $this->last_name,
        );
    }
}
```

**Complete flow:**
```
HTTP Request (JSON)
    → RegisterUserRequest (API DTO)
    → RegisterCommand (Application DTO)
    → User (Domain Entity)
    → Persistence
```

---

### 8.3 Behavioral Patterns

#### 8.3.1 Strategy Pattern

**Natural usage in hexagonal:**
- Dynamic selection of adapters
- Interchangeable calculation algorithms

**Example - Multiple payment strategies:**
```php
<?php

declare(strict_types=1);

// Port (Interface = Strategy)
namespace App\Payment\Domain\Port;

use App\Payment\Domain\Model\Payment;

interface PaymentGatewayInterface
{
    public function process(Payment $payment): bool;
    public function refund(Payment $payment): bool;
}

// Strategy 1 - Stripe
namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Port\PaymentGatewayInterface;
use Stripe\StripeClient;

final readonly class StripePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private StripeClient $stripe,
    ) {
    }

    public function process(Payment $payment): bool
    {
        $result = $this->stripe->charges->create([
            'amount' => $payment->getAmount()->inCents(),
            'currency' => $payment->getAmount()->currency,
            'source' => $payment->getToken(),
        ]);

        return $result->status === 'succeeded';
    }

    public function refund(Payment $payment): bool
    {
        // Stripe refund logic
    }
}

// Strategy 2 - PayPal
namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Port\PaymentGatewayInterface;

final readonly class PayPalPaymentGateway implements PaymentGatewayInterface
{
    public function process(Payment $payment): bool
    {
        // PayPal logic
    }
}

// Context - Using the strategy
namespace App\Payment\Application\ProcessPayment;

use App\Payment\Domain\Port\PaymentGatewayInterface;

#[AsMessageHandler]
final readonly class ProcessPaymentCommandHandler
{
    public function __construct(
        private PaymentGatewayInterface $paymentGateway,  // Injected strategy
    ) {
    }

    public function __invoke(ProcessPaymentCommand $command): void
    {
        $payment = $this->createPayment($command);

        // The strategy is used without knowing which one it is
        $success = $this->paymentGateway->process($payment);

        if (!$success) {
            throw new PaymentFailedException();
        }
    }
}
```

**Symfony Configuration (strategy choice):**
```yaml
# config/services.yaml
services:
    # Default strategy
    App\Payment\Domain\Port\PaymentGatewayInterface:
        class: App\Payment\Infrastructure\Gateway\StripePaymentGateway

    # Or by environment
    App\Payment\Domain\Port\PaymentGatewayInterface:
        class: '%env(PAYMENT_GATEWAY)%'
        # PAYMENT_GATEWAY=App\Payment\Infrastructure\Gateway\PayPalPaymentGateway
```

#### 8.3.2 Command Pattern (CQRS)

**Native implementation in hexagonal:**

```php
<?php

declare(strict_types=1);

namespace App\Order\Application\PlaceOrder;

/**
 * Command Pattern - Encapsulates a request as an object
 */
final readonly class PlaceOrderCommand
{
    public function __construct(
        public string $customerId,
        public array $items,
        public string $shippingAddress,
    ) {
    }
}

// Handler = Receiver
#[AsMessageHandler]
final readonly class PlaceOrderCommandHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private OrderFactory $orderFactory,
    ) {
    }

    public function __invoke(PlaceOrderCommand $command): void
    {
        $order = $this->orderFactory->createFromCommand($command);
        $this->orderRepository->save($order);
    }
}

// Invoker
namespace App\UI\Http\Controller;

use Symfony\Component\Messenger\MessageBusInterface;

final class OrderController
{
    public function __construct(
        private MessageBusInterface $commandBus,  // Invoker
    ) {
    }

    #[Route('/orders', methods: ['POST'])]
    public function placeOrder(Request $request): Response
    {
        $command = new PlaceOrderCommand(
            customerId: $request->get('customer_id'),
            items: $request->get('items'),
            shippingAddress: $request->get('shipping_address'),
        );

        // Command invocation
        $this->commandBus->dispatch($command);

        return new JsonResponse(['status' => 'success']);
    }
}
```

**Benefits:**
- Command history (event sourcing possible)
- Cancellation/Redo possible
- Centralized validation
- Easily asynchronous (via message queue)

#### 8.3.3 Observer Pattern (Domain Events)

**Domain Events:**

```php
<?php

declare(strict_types=1);

// Event
namespace App\User\Account\Domain\Event;

final readonly class UserRegisteredEvent
{
    public function __construct(
        public string $userId,
        public string $email,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}

// Entity that dispatches the event
namespace App\User\Account\Domain\Model;

final class User
{
    private array $domainEvents = [];

    public function register(): void
    {
        // Business logic
        $this->isActive = true;

        // Records the event
        $this->domainEvents[] = new UserRegisteredEvent(
            userId: $this->id->value,
            email: $this->email->value,
            occurredAt: new \DateTimeImmutable(),
        );
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}

// Observer 1 - Send email
namespace App\User\Account\Application\EventSubscriber;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendWelcomeEmailOnUserRegistered
{
    public function __construct(
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(UserRegisteredEvent $event): void
    {
        $this->mailer->send(
            new WelcomeEmail($event->email)
        );
    }
}

// Observer 2 - Logger
#[AsMessageHandler]
final readonly class LogUserRegistration
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UserRegisteredEvent $event): void
    {
        $this->logger->info('New user registered', [
            'user_id' => $event->userId,
            'email' => $event->email,
        ]);
    }
}

// Observer 3 - Create profile
#[AsMessageHandler]
final readonly class CreateUserProfileOnUserRegistered
{
    public function __invoke(UserRegisteredEvent $event): void
    {
        // Create user profile
    }
}
```

**Benefits:**
- **Total decoupling** between modules
- **Extensibility** - adding observer = new class
- **Single Responsibility** - each observer does one thing

---

### 8.4 Other Important Patterns

#### 8.4.1 Specification Pattern

**For complex queries:**

```php
<?php

declare(strict_types=1);

namespace App\Product\Domain\Specification;

use App\Product\Domain\Model\Product;

interface ProductSpecificationInterface
{
    public function isSatisfiedBy(Product $product): bool;
}

final readonly class ActiveProductSpecification implements ProductSpecificationInterface
{
    public function isSatisfiedBy(Product $product): bool
    {
        return $product->isActive();
    }
}

final readonly class InStockSpecification implements ProductSpecificationInterface
{
    public function isSatisfiedBy(Product $product): bool
    {
        return $product->getStock() > 0;
    }
}

// Composite Specification
final readonly class AndSpecification implements ProductSpecificationInterface
{
    public function __construct(
        private ProductSpecificationInterface $spec1,
        private ProductSpecificationInterface $spec2,
    ) {
    }

    public function isSatisfiedBy(Product $product): bool
    {
        return $this->spec1->isSatisfiedBy($product)
            && $this->spec2->isSatisfiedBy($product);
    }
}

// Usage
$activeAndInStock = new AndSpecification(
    new ActiveProductSpecification(),
    new InStockSpecification()
);

$filteredProducts = array_filter(
    $products,
    fn(Product $p) => $activeAndInStock->isSatisfiedBy($p)
);
```

#### 8.4.2 Null Object Pattern

**To avoid null checks:**

```php
<?php

declare(strict_types=1);

namespace App\User\Account\Domain\Model;

// Interface
interface UserInterface
{
    public function getId(): UserId;
    public function getEmail(): Email;
    public function isActive(): bool;
}

// Real User
final class User implements UserInterface
{
    // ... normal implementation
}

// Null User
final class NullUser implements UserInterface
{
    public function getId(): UserId
    {
        return new UserId('null');
    }

    public function getEmail(): Email
    {
        return new Email('null@example.com');
    }

    public function isActive(): bool
    {
        return false;
    }
}

// Repository
final class DoctrineUserRepository
{
    public function findById(UserId $id): UserInterface
    {
        $user = $this->entityManager->find(User::class, $id->value);

        // Returns NullUser instead of null
        return $user ?? new NullUser();
    }
}

// Usage - no null check!
$user = $repository->findById($userId);
if ($user->isActive()) {  // No null check needed
    // ...
}
```

---

### 8.5 Summary Table

| Pattern | Category | Hexagonal Layer | Main Usage |
|---------|----------|----------------|------------|
| **Factory** | Creational | Application/Domain | Creating complex entities |
| **Builder** | Creational | Domain/Tests | Progressive construction |
| **Singleton** | Creational | Infrastructure | Registries, configurations |
| **Adapter** | Structural | Infrastructure | **CORE - Implements ports** |
| **Repository** | Structural | Domain (Port) + Infra (Adapter) | Persistence abstraction |
| **DTO** | Structural | Application | Commands, Queries, Responses |
| **Strategy** | Behavioral | Infrastructure | Interchangeable algorithms |
| **Command** | Behavioral | Application | **CQRS - Use cases** |
| **Observer** | Behavioral | Application | Domain Events |
| **Specification** | Domain | Domain | Complex business rules |
| **Null Object** | Special | Domain | Avoid null checks |

---

### 8.6 Anti-Patterns to Avoid

#### Service Locator in Domain
```php
// BAD
class User
{
    public function save(): void
    {
        $repository = ServiceLocator::get(UserRepositoryInterface::class);
        $repository->save($this);
    }
}

// GOOD - Dependency injection
class RegisterCommandHandler
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {}
}
```

#### God Object / Anemic Domain
```php
// BAD - Anemic
class User
{
    public string $email;
    public string $password;
    // No logic
}

class UserService
{
    public function register(User $user): void
    {
        // All logic here
    }
}

// GOOD - Rich Domain
class User
{
    public function register(Email $email, HashedPassword $password): void
    {
        // Validation and business logic here
    }
}
```

---

## 9. Progressive Migration

### 9.1 Step 1: Extract the Domain
```php
// Before
class UserController
{
    public function register(Request $request): Response
    {
        $user = new User();
        $user->setEmail($request->get('email'));
        $this->em->persist($user);
        $this->em->flush();
    }
}

// After
class UserController
{
    public function register(Request $request): Response
    {
        $command = new RegisterCommand($request->get('email'));
        $this->commandBus->dispatch($command);
    }
}
```

### 9.2 Step 2: Create Ports
```php
// Domain/Port
interface UserRepositoryInterface
{
    public function save(User $user): void;
}
```

### 9.3 Step 3: Create Adapters
```php
// Infrastructure
class DoctrineUserRepository implements UserRepositoryInterface
{
    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }
}
```

---

## 10. Resources

- [Hexagonal Architecture by Alistair Cockburn](https://alistair.cockburn.us/hexagonal-architecture/)
- [Clean Architecture by Robert C. Martin](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
- [DDD by Eric Evans](https://www.domainlanguage.com/ddd/)
- [CQRS Pattern](https://martinfowler.com/bliki/CQRS.html)

---

**This bundle helps you implement these principles easily with Symfony!**
