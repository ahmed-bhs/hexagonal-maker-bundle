---
layout: default_with_lang
title: Port Interface Design Principles
parent: Advanced Topics
nav_order: 12
lang: en
lang_ref: fr/advanced/principes-conception-ports.md
---

# Port Interface Design Principles

## Table of Contents

1. [What is a Port?](#what-is-a-port)
2. [Naming Conventions](#naming-conventions)
3. [Interface Segregation Principle (ISP)](#interface-segregation-principle-isp)
4. [Method Design Guidelines](#method-design-guidelines)
5. [Common Port Patterns](#common-port-patterns)
6. [Anti-Patterns to Avoid](#anti-patterns-to-avoid)
7. [Real-World Examples](#real-world-examples)

---

## What is a Port?

**A Port is an interface defined in the Domain layer that declares what the domain needs from the outside world.**

```
Domain defines:   "I need to save users"  → UserRepositoryInterface (Port)
Infrastructure provides:  "Here's how"    → DoctrineUserRepository (Adapter)
```

### Key Characteristics

- **Defined in Domain** - Lives in `Domain/Port/`
- **Implemented by Infrastructure** - Adapters in `Infrastructure/`
- **Expresses Business Intent** - Uses domain language, not technical language
- **No Implementation Details** - No mention of Doctrine, MySQL, HTTP, etc.

---

## Naming Conventions

### Repository Ports

✅ **GOOD:**
```php
interface UserRepositoryInterface       // Clear: manages User entities
interface OrderRepositoryInterface      // Clear: manages Order entities
interface ProductRepositoryInterface    // Clear: manages Product entities
```

❌ **BAD:**
```php
interface UserDAO                       // Technical term (Data Access Object)
interface UserPersistence               // Vague
interface IUserRepository               // Hungarian notation (avoid "I" prefix)
interface UserRepositoryPort            // Redundant suffix
```

### Service Ports

✅ **GOOD:**
```php
interface EmailSenderInterface          // Clear capability
interface PaymentProcessorInterface     // Clear responsibility
interface NotificationServiceInterface  // Clear purpose
```

❌ **BAD:**
```php
interface EmailService                  // Too vague
interface IEmailSender                  // Hungarian notation
interface SMTPEmailSender               // Implementation detail leaked!
```

### Query Ports (CQRS)

✅ **GOOD:**
```php
interface UserQueryInterface            // Clear: read operations for Users
interface OrderQueryInterface           // Clear: read operations for Orders
interface ProductCatalogQueryInterface  // Clear: specific read concern
```

❌ **BAD:**
```php
interface UserReader                    // Unclear
interface GetUserQuery                  // Not a capability, but an action
```

---

## Interface Segregation Principle (ISP)

> **"Clients should not be forced to depend on methods they do not use."**

### The Problem: Fat Interfaces

❌ **BAD: God Interface**

```php
interface UserRepositoryInterface
{
    // Read methods
    public function findById(UserId $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findAll(): array;
    public function findActiveUsers(): array;
    public function findUsersByRole(string $role): array;
    public function searchUsers(string $query): array;

    // Write methods
    public function save(User $user): void;
    public function delete(User $user): void;

    // Statistics methods
    public function countUsers(): int;
    public function countActiveUsers(): int;

    // Admin methods
    public function purgeInactiveUsers(): void;
    public function exportUsersToCSV(): string;

    // Notification methods
    public function findUsersToNotify(): array;
}
```

**Problems:**
- Handler that only saves users depends on 15 methods it doesn't need
- Hard to test (must mock 15 methods)
- Hard to implement (adapter must implement everything)
- Violates Single Responsibility Principle

### The Solution: Segregated Interfaces

✅ **GOOD: Segregated by Responsibility**

```php
// Write operations
interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function delete(User $user): void;
    public function existsByEmail(string $email): bool;
}

// Read operations (CQRS pattern)
interface UserQueryInterface
{
    public function findById(UserId $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findActiveUsers(): array;
}

// Admin operations
interface UserAdminInterface
{
    public function purgeInactiveUsers(): void;
    public function countUsers(): int;
}

// Notification operations
interface UserNotificationQueryInterface
{
    public function findUsersToNotify(): array;
}
```

**Benefits:**
- Handlers depend only on what they need
- Easy to test (mock only relevant methods)
- Easy to implement (adapter implements one responsibility at a time)
- Clear separation of concerns

### When to Split vs Keep Together

✅ **Keep together** when methods are always used together:

```php
// GOOD: These methods logically belong together
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function delete(Order $order): void;
}
```

❌ **Split** when methods serve different use cases:

```php
// BAD: findPendingOrders is specific to a background job
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function findPendingOrders(): array; // ❌ Different concern!
}

// GOOD: Separate query interface
interface OrderQueryInterface
{
    public function findPendingOrders(): array;
}
```

---

## Method Design Guidelines

### 1. Use Domain Language, Not Technical Language

✅ **GOOD: Domain Language**

```php
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function findPendingOrders(): array; // Business concept
}
```

❌ **BAD: Technical Language**

```php
interface OrderRepositoryInterface
{
    public function persist(Order $order): void; // Technical (SQL term)
    public function selectById(OrderId $id): ?Order; // Technical (SQL term)
    public function queryByStatusPending(): array; // Technical implementation detail
}
```

---

### 2. Return Domain Objects, Not Primitives

✅ **GOOD: Domain Objects**

```php
interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;
    public function findActiveUsers(): array; // array<User>
}
```

❌ **BAD: Primitives**

```php
interface UserRepositoryInterface
{
    public function findById(string $id): ?array; // array is not type-safe
    public function findActiveUsers(): array; // array<what?>
}
```

**Use PHPDoc for clarity:**

```php
interface UserRepositoryInterface
{
    /**
     * @return array<User>
     */
    public function findActiveUsers(): array;
}
```

---

### 3. Accept Domain Types, Not Primitives

✅ **GOOD: Value Objects**

```php
interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;
    public function existsByEmail(Email $email): bool;
}
```

❌ **BAD: Primitives**

```php
interface UserRepositoryInterface
{
    public function findById(string $id): ?User;
    public function existsByEmail(string $email): bool; // Loses domain validation
}
```

**Why?** Value objects ensure validation happens at the boundary, not in the adapter.

---

### 4. Design for Readability

Method names should read like natural language.

✅ **GOOD: Readable**

```php
if ($this->users->existsByEmail($email)) {
    throw new EmailAlreadyExistsException();
}

$orders = $this->orders->findPendingOrders();
```

❌ **BAD: Unclear**

```php
if ($this->users->checkEmail($email)) { // Check what about email?
    throw new EmailAlreadyExistsException();
}

$orders = $this->orders->getPending(); // Get pending what?
```

---

### 5. Avoid Leaking Implementation Details

✅ **GOOD: Implementation-Agnostic**

```php
interface NotificationServiceInterface
{
    public function send(Notification $notification): void;
}
```

❌ **BAD: Leaks Implementation**

```php
interface NotificationServiceInterface
{
    public function sendViaSmtp(Notification $notification): void; // ❌ SMTP is implementation detail
    public function sendViaSendGrid(Notification $notification): void; // ❌ SendGrid is implementation detail
}
```

**Why?** Port should describe "what", not "how". Implementation can change without changing the port.

---

### 6. Design for Testability

Ports should be easy to mock/stub.

✅ **GOOD: Simple, Testable**

```php
interface EmailSenderInterface
{
    public function send(Email $email): void;
}

// Test with in-memory fake
class InMemoryEmailSender implements EmailSenderInterface
{
    private array $sentEmails = [];

    public function send(Email $email): void
    {
        $this->sentEmails[] = $email;
    }

    public function getSentEmails(): array
    {
        return $this->sentEmails;
    }
}
```

❌ **BAD: Hard to Test**

```php
interface EmailSenderInterface
{
    public function send(
        Email $email,
        EmailConfiguration $config,
        TransportOptions $transport,
        RetryPolicy $retry
    ): SendResult;
}

// Test requires complex setup with many dependencies
```

---

## Common Port Patterns

### Pattern 1: Repository Port (Persistence)

**Purpose:** Manage aggregate root lifecycle (CRUD).

```php
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function delete(Order $order): void;
}
```

**Key Points:**
- One repository per aggregate root
- Methods use domain language (`save`, not `persist`)
- Return domain entities, not arrays

---

### Pattern 2: Query Port (CQRS Read Side)

**Purpose:** Optimized read operations, may return DTOs instead of entities.

```php
interface ProductCatalogQueryInterface
{
    /**
     * @return array<ProductListDTO>
     */
    public function findAvailableProducts(int $limit, int $offset): array;

    public function findProductById(ProductId $id): ?ProductDetailDTO;

    public function searchProducts(string $query): array;
}
```

**Key Points:**
- Separate from write operations (repository)
- Can return DTOs optimized for display
- May bypass domain entities for performance

---

### Pattern 3: External Service Port

**Purpose:** Communicate with external systems (email, payment, etc.).

```php
interface PaymentProcessorInterface
{
    public function charge(PaymentRequest $request): PaymentResult;
    public function refund(RefundRequest $request): RefundResult;
}
```

**Key Points:**
- Express business capability, not technical protocol
- Accept/return domain objects
- Hide implementation details (Stripe, PayPal, etc.)

---

### Pattern 4: Event Dispatcher Port

**Purpose:** Publish domain events.

```php
interface EventDispatcherInterface
{
    public function dispatch(DomainEvent $event): void;
}
```

**Key Points:**
- Generic interface for all events
- Domain events are first-class citizens
- Infrastructure handles routing

---

### Pattern 5: Specification Port (Query Builder)

**Purpose:** Build complex queries dynamically.

```php
interface UserSpecificationInterface
{
    public function matching(Specification $spec): array;
}

// Usage
$activeAdmins = $this->users->matching(
    new AndSpecification(
        new IsActiveSpecification(),
        new HasRoleSpecification(Role::ADMIN)
    )
);
```

**Key Points:**
- Allows complex filtering without polluting repository
- Composable specifications
- Advanced pattern, use sparingly

---

## Anti-Patterns to Avoid

### Anti-Pattern 1: Generic Repository

❌ **AVOID:**

```php
interface GenericRepositoryInterface
{
    public function save(object $entity): void;
    public function findById(string $id): ?object;
    public function findAll(): array;
}
```

**Problems:**
- Type-unsafe (`object` and `string` are too generic)
- Loses domain specificity
- No type hinting benefits

✅ **BETTER:**

```php
interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
}

interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
}
```

---

### Anti-Pattern 2: Repositories with Business Logic

❌ **AVOID:**

```php
interface OrderRepositoryInterface
{
    public function save(Order $order): void;

    // ❌ Business logic leaked into repository!
    public function cancelOrder(OrderId $id): void;
    public function shipOrder(OrderId $id, Address $address): void;
}
```

**Problem:** Repository should manage persistence, not execute business logic.

✅ **BETTER:**

```php
// Repository: persistence only
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
}

// Business logic in handlers
class CancelOrderHandler
{
    public function __invoke(CancelOrderCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);
        $order->cancel(); // Business logic in entity
        $this->orders->save($order);
    }
}
```

---

### Anti-Pattern 3: Query Methods Returning Scalar Arrays

❌ **AVOID:**

```php
interface UserRepositoryInterface
{
    /**
     * @return array<array{id: string, email: string, name: string}>
     */
    public function findAllUsers(): array;
}
```

**Problem:** Array shapes are error-prone and not type-safe.

✅ **BETTER:**

```php
interface UserQueryInterface
{
    /**
     * @return array<UserListDTO>
     */
    public function findAllUsers(): array;
}

final readonly class UserListDTO
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
    ) {}
}
```

---

### Anti-Pattern 4: Ports Depending on Infrastructure

❌ **AVOID:**

```php
use Doctrine\ORM\EntityManagerInterface;

interface UserRepositoryInterface
{
    public function getEntityManager(): EntityManagerInterface; // ❌ Leaks infrastructure!
}
```

**Problem:** Domain now depends on Doctrine.

✅ **BETTER:**

```php
interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
    // No mention of Doctrine, EntityManager, or any framework
}
```

---

## Real-World Examples

### Example 1: E-Commerce Order System

```php
// Write operations
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function nextOrderNumber(): OrderNumber;
}

// Read operations (optimized for display)
interface OrderQueryInterface
{
    /**
     * @return array<OrderListDTO>
     */
    public function findOrdersByCustomer(CustomerId $customerId, int $limit, int $offset): array;

    public function findOrderDetails(OrderId $id): ?OrderDetailDTO;

    /**
     * @return array<OrderListDTO>
     */
    public function findRecentOrders(int $limit): array;
}

// External payment service
interface PaymentProcessorInterface
{
    public function charge(PaymentRequest $request): PaymentResult;
    public function refund(RefundRequest $request): RefundResult;
    public function getTransactionStatus(TransactionId $id): TransactionStatus;
}

// Inventory management
interface InventoryServiceInterface
{
    public function reserveStock(ProductId $productId, int $quantity): void;
    public function releaseStock(ProductId $productId, int $quantity): void;
    public function checkAvailability(ProductId $productId): int;
}
```

---

### Example 2: User Authentication System

```php
// User persistence
interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function existsByEmail(Email $email): bool;
}

// Password hashing (external service)
interface PasswordHasherInterface
{
    public function hash(string $plaintext): string;
    public function verify(string $plaintext, string $hash): bool;
}

// Email notifications
interface EmailSenderInterface
{
    public function send(Email $email): void;
}

// Token generation
interface TokenGeneratorInterface
{
    public function generate(): string;
}
```

---

### Example 3: Blog System

```php
// Article persistence
interface ArticleRepositoryInterface
{
    public function save(Article $article): void;
    public function findById(ArticleId $id): ?Article;
    public function delete(Article $article): void;
}

// Article queries (optimized for performance)
interface ArticleQueryInterface
{
    /**
     * @return array<ArticleSummaryDTO>
     */
    public function findPublishedArticles(int $limit, int $offset): array;

    public function findArticleBySlug(string $slug): ?ArticleDetailDTO;

    /**
     * @return array<ArticleSummaryDTO>
     */
    public function findArticlesByAuthor(AuthorId $authorId): array;

    public function countPublishedArticles(): int;
}

// Search functionality
interface ArticleSearchInterface
{
    /**
     * @return array<ArticleSearchResultDTO>
     */
    public function search(string $query): array;
}

// Image storage
interface ImageStorageInterface
{
    public function store(Image $image): string; // Returns URL
    public function delete(string $url): void;
}
```

---

## Decision Checklist

When designing a port, ask yourself:

- [ ] Does the interface name clearly express its purpose?
- [ ] Are methods named using domain language, not technical terms?
- [ ] Does it accept/return domain objects (entities, value objects, DTOs)?
- [ ] Is it segregated (ISP)—handlers depend only on what they need?
- [ ] Does it avoid leaking implementation details?
- [ ] Can it be easily mocked/stubbed for testing?
- [ ] Would a business expert understand the method names?
- [ ] Is it defined in the Domain layer (`Domain/Port/`)?
- [ ] Does it have zero dependencies on infrastructure?

---

## Summary

| Principle | Guideline |
|-----------|-----------|
| **Naming** | Use domain language, avoid technical terms |
| **Segregation** | Split interfaces by responsibility (ISP) |
| **Types** | Accept/return domain objects, not primitives |
| **Clarity** | Methods should read like natural language |
| **Abstraction** | Hide implementation details completely |
| **Testability** | Easy to mock with in-memory fakes |
| **Location** | Always in `Domain/Port/`, never in Infrastructure |

---

**Next:** [Primary vs Secondary Adapters →](./primary-secondary-adapters.md)
