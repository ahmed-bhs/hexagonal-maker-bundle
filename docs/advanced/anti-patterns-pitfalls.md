---
layout: default_with_lang
title: Anti-Patterns and Pitfalls
parent: Advanced Topics
nav_order: 18
lang: en
lang_ref: fr/advanced/anti-patterns-pieges.md
---

# Anti-Patterns and Pitfalls in Hexagonal Architecture

## Table of Contents

1. [Anemic Domain Model](#anemic-domain-model)
2. [God Objects](#god-objects)
3. [Leaky Abstractions](#leaky-abstractions)
4. [Repository as Service Locator](#repository-as-service-locator)
5. [Transaction Management Issues](#transaction-management-issues)
6. [Cascade Delete Problems](#cascade-delete-problems)
7. [Over-Engineering](#over-engineering)
8. [Testing Anti-Patterns](#testing-anti-patterns)

---

## Anemic Domain Model

### The Problem

**Anemic Domain Model:** Entities are just data containers with getters/setters, all logic is in handlers.

```php
// ❌ BAD: Anemic entity
class Order
{
    private OrderStatus $status;
    private Money $total;
    private \DateTimeImmutable $shippedAt;

    // Only getters and setters, no behavior
    public function getStatus(): OrderStatus { return $this->status; }
    public function setStatus(OrderStatus $status): void { $this->status = $status; }

    public function getTotal(): Money { return $this->total; }
    public function setTotal(Money $total): void { $this->total = $total; }

    public function getShippedAt(): ?\DateTimeImmutable { return $this->shippedAt; }
    public function setShippedAt(\DateTimeImmutable $shippedAt): void { $this->shippedAt = $shippedAt; }
}

// ❌ BAD: All business logic in handler
class ShipOrderHandler
{
    public function __invoke(ShipOrderCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);

        // Business logic in handler (should be in entity!)
        if ($order->getStatus() === OrderStatus::CANCELLED) {
            throw new CannotShipCancelledOrderException();
        }

        if ($order->getStatus() === OrderStatus::SHIPPED) {
            throw new OrderAlreadyShippedException();
        }

        $order->setStatus(OrderStatus::SHIPPED);
        $order->setShippedAt(new \DateTimeImmutable());

        $this->orders->save($order);
    }
}
```

**Problems:**
- Business rules scattered in handlers
- Hard to test (need handler to test business logic)
- Cannot reuse logic elsewhere
- Entity is just a data bag

---

### The Solution: Rich Domain Model

```php
// ✅ GOOD: Rich entity with behavior
class Order
{
    private OrderStatus $status;
    private Money $total;
    private ?\DateTimeImmutable $shippedAt = null;

    // Business logic encapsulated in entity
    public function ship(): void
    {
        if ($this->status === OrderStatus::CANCELLED) {
            throw new CannotShipCancelledOrderException();
        }

        if ($this->status === OrderStatus::SHIPPED) {
            throw new OrderAlreadyShippedException();
        }

        $this->status = OrderStatus::SHIPPED;
        $this->shippedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        if ($this->status === OrderStatus::SHIPPED) {
            throw new CannotCancelShippedOrderException();
        }

        $this->status = OrderStatus::CANCELLED;
    }

    // Getters only (no setters!)
    public function getStatus(): OrderStatus { return $this->status; }
    public function getTotal(): Money { return $this->total; }
    public function getShippedAt(): ?\DateTimeImmutable { return $this->shippedAt; }
}

// ✅ GOOD: Thin handler, just orchestration
class ShipOrderHandler
{
    public function __invoke(ShipOrderCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);

        $order->ship(); // Business logic in entity

        $this->orders->save($order);
        $this->eventDispatcher->dispatch(new OrderShippedEvent($order->getId()));
    }
}
```

**Benefits:**
- Business logic in domain where it belongs
- Easy to test (`$order->ship()` can be tested without handler)
- Reusable across use cases
- Entity protects its invariants

---

## God Objects

### The Problem: Fat Handlers

**God Object:** Handler does everything (validation, business logic, orchestration, error handling).

```php
// ❌ BAD: God handler (200+ lines)
class ProcessOrderHandler
{
    public function __invoke(ProcessOrderCommand $command): void
    {
        // Input validation
        if (empty($command->items)) {
            throw new InvalidOrderException("Order must have items");
        }

        // Check customer
        $customer = $this->customers->findById($command->customerId);
        if (!$customer) {
            throw new CustomerNotFoundException($command->customerId);
        }

        if (!$customer->isActive()) {
            throw new InactiveCustomerException();
        }

        // Check inventory for each item
        foreach ($command->items as $item) {
            $product = $this->products->findById($item->productId);
            if (!$product) {
                throw new ProductNotFoundException($item->productId);
            }

            if ($product->getStock() < $item->quantity) {
                throw new InsufficientStockException($item->productId);
            }
        }

        // Calculate pricing
        $subtotal = 0;
        foreach ($command->items as $item) {
            $product = $this->products->findById($item->productId);
            $subtotal += $product->getPrice() * $item->quantity;
        }

        $tax = $subtotal * $this->taxCalculator->getTaxRate($command->shippingAddress);
        $shipping = $this->shippingCalculator->calculate($command->shippingAddress, $command->items);
        $total = $subtotal + $tax + $shipping;

        // Create order
        $order = new Order(
            OrderId::generate(),
            $command->customerId,
            $command->items,
            $subtotal,
            $tax,
            $shipping,
            $total,
            OrderStatus::PENDING,
            new \DateTimeImmutable()
        );

        // Reserve stock
        foreach ($command->items as $item) {
            $product = $this->products->findById($item->productId);
            $product->reserveStock($item->quantity);
            $this->products->save($product);
        }

        // Save order
        $this->orders->save($order);

        // Send notifications
        $this->emailSender->send(new OrderConfirmationEmail($order));
        $this->eventDispatcher->dispatch(new OrderCreatedEvent($order->getId()));

        // ... 100 more lines
    }
}
```

**Problems:**
- Too many responsibilities
- Hard to test
- Hard to maintain
- Hard to understand

---

### The Solution: Decompose Responsibilities

```php
// ✅ GOOD: Split into multiple handlers/services

// 1. Handler: orchestration only
class ProcessOrderHandler
{
    public function __construct(
        private OrderFactory $orderFactory,
        private OrderRepositoryInterface $orders,
        private InventoryService $inventory,
        private EventDispatcherInterface $events,
    ) {}

    public function __invoke(ProcessOrderCommand $command): void
    {
        // Factory handles creation + validation
        $order = $this->orderFactory->create(
            customerId: $command->customerId,
            items: $command->items,
            shippingAddress: $command->shippingAddress
        );

        // Domain service handles inventory
        $this->inventory->reserveStock($order->getItems());

        // Repository handles persistence
        $this->orders->save($order);

        // Event dispatcher handles notifications
        $this->events->dispatch(new OrderCreatedEvent($order->getId()));
    }
}

// 2. Factory: handles complex creation logic
class OrderFactory
{
    public function create(
        CustomerId $customerId,
        array $items,
        ShippingAddress $address
    ): Order {
        $this->validateCustomer($customerId);
        $this->validateItems($items);

        $subtotal = $this->calculateSubtotal($items);
        $tax = $this->taxCalculator->calculate($subtotal, $address);
        $shipping = $this->shippingCalculator->calculate($address, $items);

        return new Order(
            id: OrderId::generate(),
            customerId: $customerId,
            items: $items,
            subtotal: $subtotal,
            tax: $tax,
            shipping: $shipping,
            total: $subtotal->add($tax)->add($shipping),
            status: OrderStatus::PENDING,
            createdAt: new \DateTimeImmutable()
        );
    }

    private function validateCustomer(CustomerId $customerId): void { /* ... */ }
    private function validateItems(array $items): void { /* ... */ }
    private function calculateSubtotal(array $items): Money { /* ... */ }
}

// 3. Domain service: handles cross-entity operations
class InventoryService
{
    public function reserveStock(array $items): void
    {
        foreach ($items as $item) {
            $product = $this->products->findById($item->getProductId());

            if ($product->getStock() < $item->getQuantity()) {
                throw new InsufficientStockException($item->getProductId());
            }

            $product->reserveStock($item->getQuantity());
            $this->products->save($product);
        }
    }
}
```

**Benefits:**
- Single Responsibility Principle
- Easy to test each component
- Easy to understand
- Reusable components

---

## Leaky Abstractions

### The Problem: Port Exposes Implementation Details

```php
// ❌ BAD: Port leaks Doctrine details
namespace App\User\Domain\Port;

use Doctrine\ORM\QueryBuilder;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    // ❌ Exposes Doctrine QueryBuilder!
    public function createQueryBuilder(): QueryBuilder;

    // ❌ Exposes Doctrine-specific method!
    public function findBy(array $criteria, ?array $orderBy = null): array;
}
```

**Problem:** Domain now depends on Doctrine. Cannot change to MongoDB without changing domain.

---

### The Solution: Domain-Centric Port

```php
// ✅ GOOD: Port uses domain language only
namespace App\User\Domain\Port;

interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function findActiveUsers(): array; // array<User>
}

// Adapter implements port with Doctrine
namespace App\User\Infrastructure\Persistence;

class DoctrineUserRepository implements UserRepositoryInterface
{
    public function findActiveUsers(): array
    {
        // Doctrine details hidden in adapter
        return $this->entityManager
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }
}
```

**Benefits:**
- Domain independent of infrastructure
- Can change database without touching domain
- Clear, domain-specific API

---

## Repository as Service Locator

### The Problem: Repository Fetches Unrelated Entities

```php
// ❌ BAD: Order repository fetches customers and products
class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        // Fetch customer (unrelated to order persistence!)
        $customer = $this->entityManager->find(Customer::class, $order->getCustomerId());

        // Fetch products (unrelated to order persistence!)
        foreach ($order->getItems() as $item) {
            $product = $this->entityManager->find(Product::class, $item->getProductId());
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();
    }
}
```

**Problem:** Repository becomes service locator, violates Single Responsibility.

---

### The Solution: Repository Only Manages Its Aggregate

```php
// ✅ GOOD: Order repository only manages orders
class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        // That's it! No fetching other entities
    }

    public function findById(OrderId $id): ?Order
    {
        return $this->entityManager->find(Order::class, $id->toString());
    }
}

// Handler coordinates multiple repositories
class CreateOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private CustomerRepositoryInterface $customers, // Separate repository
        private ProductRepositoryInterface $products,   // Separate repository
    ) {}

    public function __invoke(CreateOrderCommand $command): void
    {
        // Handler fetches entities from their own repositories
        $customer = $this->customers->findById($command->customerId);
        // Validate customer...

        foreach ($command->items as $item) {
            $product = $this->products->findById($item->productId);
            // Validate product...
        }

        $order = OrderFactory::create($customer, $command->items);
        $this->orders->save($order); // Order repository only saves orders
    }
}
```

---

## Transaction Management Issues

### The Problem: Nested Transactions or Implicit Commits

```php
// ❌ BAD: Handler starts transaction, but repository also flushes
class CheckoutOrderHandler
{
    public function __invoke(CheckoutOrderCommand $command): void
    {
        $this->entityManager->beginTransaction();

        try {
            $order = $this->orders->findById($command->orderId);
            $order->confirm();

            $this->orders->save($order); // ❌ Calls flush() inside transaction!

            $this->inventory->reserveStock($order->getItems()); // ❌ Also flushes!

            $this->entityManager->commit(); // May commit already-flushed data
        } catch (\Exception $e) {
            $this->entityManager->rollback(); // May not rollback everything!
            throw $e;
        }
    }
}

class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        $this->entityManager->persist($order);
        $this->entityManager->flush(); // ❌ Flushes immediately!
    }
}
```

**Problem:** Intermediate flushes prevent proper rollback.

---

### The Solution: Explicit Transaction Control

```php
// ✅ GOOD: Handler controls transaction, repositories don't flush
class CheckoutOrderHandler
{
    public function __invoke(CheckoutOrderCommand $command): void
    {
        $this->entityManager->beginTransaction();

        try {
            $order = $this->orders->findById($command->orderId);
            $order->confirm();

            $this->orders->persist($order); // Just persist, don't flush

            $this->inventory->reserveStock($order->getItems()); // Just persist

            $this->entityManager->flush(); // Flush all changes at once
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}

// Repository: persist() method (no flush)
class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function persist(Order $order): void
    {
        $this->entityManager->persist($order);
        // No flush! Let handler control transaction
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
```

**Alternative: Use Doctrine's transactional wrapper**

```php
// ✅ GOOD: Use transactional helper
class CheckoutOrderHandler
{
    public function __invoke(CheckoutOrderCommand $command): void
    {
        $this->entityManager->wrapInTransaction(function() use ($command) {
            $order = $this->orders->findById($command->orderId);
            $order->confirm();

            $this->orders->persist($order);
            $this->inventory->reserveStock($order->getItems());

            // Automatically flushes and commits, or rollbacks on exception
        });
    }
}
```

---

## Cascade Delete Problems

### The Problem: Accidental Cascade Deletes

```php
// ❌ BAD: Deleting order deletes customer!
#[ORM\Entity]
class Order
{
    #[ORM\ManyToOne(targetEntity: Customer::class, cascade: ['remove'])] // ❌ Wrong!
    private Customer $customer;
}

// Deleting order accidentally deletes customer
$this->orders->delete($order); // ❌ Customer also deleted!
```

**Problem:** Cascade operations can have unintended side effects.

---

### The Solution: Explicit Aggregate Boundaries

```php
// ✅ GOOD: No cascade, explicit deletion
#[ORM\Entity]
class Order
{
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    private Customer $customer; // No cascade
}

// Handler explicitly controls what gets deleted
class DeleteOrderHandler
{
    public function __invoke(DeleteOrderCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);

        // Only delete order, not customer
        $this->orders->delete($order);

        // If needed, handle customer separately
        // $this->customers->delete($order->getCustomer());
    }
}
```

**Rule:** Only cascade within aggregate boundaries.

```php
// ✅ GOOD: Cascade within aggregate
#[ORM\Entity]
class Order
{
    #[ORM\OneToMany(
        targetEntity: OrderItem::class,
        mappedBy: 'order',
        cascade: ['persist', 'remove'] // ✅ OK: OrderItem is part of Order aggregate
    )]
    private array $items;
}
```

---

## Over-Engineering

### The Problem: Premature Abstraction

```php
// ❌ BAD: Over-engineered for simple CRUD
interface UserCreatorInterface { /* ... */ }
interface UserUpdaterInterface { /* ... */ }
interface UserDeleterInterface { /* ... */ }
interface UserFinderInterface { /* ... */ }
interface UserValidatorInterface { /* ... */ }
interface UserFactoryInterface { /* ... */ }
interface UserMapperInterface { /* ... */ }

class UserCreator implements UserCreatorInterface { /* ... */ }
class UserUpdater implements UserUpdaterInterface { /* ... */ }
// ... 10 more classes for simple user management
```

**Problem:** Too many abstractions for simple operations.

---

### The Solution: Start Simple, Refactor When Needed

```php
// ✅ GOOD: Simple interface for simple needs
interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function delete(User $user): void;
}

class DoctrineUserRepository implements UserRepositoryInterface
{
    // Simple implementation
}

// Refactor into separate interfaces ONLY when:
// - Multiple implementations needed
// - Interface becomes too large
// - Different clients need different methods
```

**Rule:** YAGNI (You Ain't Gonna Need It) - Don't add complexity until it's needed.

---

## Testing Anti-Patterns

### Anti-Pattern 1: Testing Implementation Details

```php
// ❌ BAD: Testing internal state instead of behavior
class OrderTest extends TestCase
{
    public function test_ship_order(): void
    {
        $order = new Order(OrderId::generate(), OrderStatus::CONFIRMED);

        $order->ship();

        // ❌ Testing private property directly (using reflection)
        $reflection = new \ReflectionClass($order);
        $property = $reflection->getProperty('status');
        $property->setAccessible(true);

        $this->assertEquals(OrderStatus::SHIPPED, $property->getValue($order));
    }
}
```

**Problem:** Test is coupled to implementation, breaks when refactoring.

---

#### Solution: Test Behavior, Not State

```php
// ✅ GOOD: Test public behavior
class OrderTest extends TestCase
{
    public function test_ship_order(): void
    {
        $order = new Order(OrderId::generate(), OrderStatus::CONFIRMED);

        $order->ship();

        // Test public method
        $this->assertEquals(OrderStatus::SHIPPED, $order->getStatus());
        $this->assertNotNull($order->getShippedAt());
    }

    public function test_cannot_ship_cancelled_order(): void
    {
        $order = new Order(OrderId::generate(), OrderStatus::CANCELLED);

        $this->expectException(CannotShipCancelledOrderException::class);

        $order->ship();
    }
}
```

---

### Anti-Pattern 2: Mocking Everything

```php
// ❌ BAD: Mocking value objects and entities
class RegisterUserHandlerTest extends TestCase
{
    public function test_registers_user(): void
    {
        $user = $this->createMock(User::class); // ❌ Mocking entity
        $email = $this->createMock(Email::class); // ❌ Mocking value object

        $factory = $this->createMock(UserFactory::class);
        $factory->method('create')->willReturn($user);

        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($user);

        $handler = new RegisterUserHandler($factory, $repository);
        $handler(new RegisterUserCommand('test@example.com', 'password'));
    }
}
```

**Problem:** Mocking domain objects defeats the purpose of testing.

---

#### Solution: Use Real Domain Objects, Mock Only Infrastructure

```php
// ✅ GOOD: Real domain objects, mock infrastructure
class RegisterUserHandlerTest extends TestCase
{
    public function test_registers_user(): void
    {
        // Real factory and entities
        $repository = new InMemoryUserRepository(); // Fake infrastructure

        $handler = new RegisterUserHandler($repository);

        $handler(new RegisterUserCommand('test@example.com', 'ValidPass123'));

        // Verify using real repository
        $this->assertTrue($repository->existsByEmail('test@example.com'));

        $user = $repository->findByEmail('test@example.com');
        $this->assertFalse($user->isActive());
    }
}
```

---

### Anti-Pattern 3: Not Testing Error Cases

```php
// ❌ BAD: Only testing happy path
class OrderTest extends TestCase
{
    public function test_create_order(): void
    {
        $order = OrderFactory::create($customerId, $items);

        $this->assertInstanceOf(Order::class, $order);
    }

    // ❌ Missing: test empty items, invalid customer, etc.
}
```

---

#### Solution: Test Error Cases Thoroughly

```php
// ✅ GOOD: Test both success and failure
class OrderTest extends TestCase
{
    public function test_create_order_with_valid_data(): void
    {
        $order = OrderFactory::create($customerId, $items);
        $this->assertInstanceOf(Order::class, $order);
    }

    public function test_throws_when_no_items(): void
    {
        $this->expectException(OrderMustHaveItemsException::class);
        OrderFactory::create($customerId, []);
    }

    public function test_throws_when_total_below_minimum(): void
    {
        $this->expectException(OrderBelowMinimumException::class);
        OrderFactory::create($customerId, $cheapItems);
    }

    public function test_cannot_ship_cancelled_order(): void
    {
        $this->expectException(CannotShipCancelledOrderException::class);
        $order = new Order(OrderId::generate(), OrderStatus::CANCELLED);
        $order->ship();
    }
}
```

---

## Summary Checklist

### ✅ Avoid These Anti-Patterns

- [ ] **Anemic Domain Model** - Entities should have behavior, not just getters/setters
- [ ] **God Objects** - Handlers should orchestrate, not implement everything
- [ ] **Leaky Abstractions** - Ports should use domain language, not expose infrastructure
- [ ] **Repository as Service Locator** - Repositories manage one aggregate only
- [ ] **Transaction Management Issues** - Control transactions explicitly in handlers
- [ ] **Cascade Delete Problems** - Only cascade within aggregate boundaries
- [ ] **Over-Engineering** - Start simple, refactor when needed (YAGNI)
- [ ] **Testing Implementation Details** - Test behavior, not internal state
- [ ] **Mocking Everything** - Use real domain objects, mock only infrastructure
- [ ] **Not Testing Error Cases** - Test failure scenarios thoroughly

---

## Quick Reference: Good vs Bad

| Anti-Pattern | Good Practice |
|--------------|---------------|
| Getters/setters everywhere | Entity methods expressing behavior |
| 500-line handler | Decomposed into handler + factory + services |
| Port returns QueryBuilder | Port returns domain objects |
| Repository fetches other entities | Handler coordinates multiple repositories |
| Repository calls flush() | Handler controls transactions |
| Cascade delete everywhere | Explicit deletes, cascade only within aggregates |
| Interface for everything | Start simple, refactor when needed |
| Test private properties | Test public behavior |
| Mock entities | Use real entities, mock infrastructure |
| Only test happy path | Test errors and edge cases |

---

**That's it! You now have a complete guide to avoiding common pitfalls in hexagonal architecture. Good luck!** 🎉
