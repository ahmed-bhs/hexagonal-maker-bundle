---
layout: default_with_lang
title: Factory Pattern Implementation
parent: Advanced Topics
nav_order: 16
lang: en
lang_ref: fr/advanced/pattern-factory-guide.md
---

# Factory Pattern: Complete Implementation Guide

## Table of Contents

1. [Why Use Factories?](#why-use-factories)
2. [Factory vs Constructor](#factory-vs-constructor)
3. [Types of Factories](#types-of-factories)
4. [Input Validation in Factories](#input-validation-in-factories)
5. [Error Handling](#error-handling)
6. [Factories with Value Objects](#factories-with-value-objects)
7. [Factory with Dependencies](#factory-with-dependencies)
8. [Testing Factories](#testing-factories)
9. [Complete Real-World Examples](#complete-real-world-examples)

---

## Why Use Factories?

### The Problem: Complex Entity Creation

```php
// ❌ Creating entity directly: complex and error-prone
$user = new User(
    id: UserId::generate(),
    email: new Email($request->email),
    password: HashedPassword::fromPlaintext($request->password),
    roles: [Role::USER],
    isActive: false,
    createdAt: new \DateTimeImmutable(),
    updatedAt: new \DateTimeImmutable()
);

// What if email validation throws?
// What if password is too short?
// What if we forget to set createdAt?
```

**Problems:**
- Complex instantiation logic scattered everywhere
- Easy to forget required fields
- Hard to maintain (change in one place = change everywhere)
- Business rules duplicated

---

### The Solution: Factory Pattern

```php
// ✅ Using factory: simple and consistent
$user = UserFactory::create(
    email: $request->email,
    password: $request->password
);

// Factory handles:
// - ID generation
// - Email validation
// - Password hashing
// - Default roles
// - Timestamps
// - Business rules
```

**Benefits:**
- **Encapsulates complex creation logic**
- **Enforces business rules**
- **Provides sensible defaults**
- **Single place to change creation logic**
- **Clear API for creating entities**

---

## Factory vs Constructor

### When to Use Constructor

✅ **Use constructor when:**
- Creation is simple (no logic)
- All fields required, no defaults
- Reconstructing from database (hydration)

```php
// Simple value object: constructor is fine
final readonly class Email
{
    public function __construct(public string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException($value);
        }
    }
}

// Usage
$email = new Email('user@example.com'); // Simple, clear
```

---

### When to Use Factory

✅ **Use factory when:**
- Complex initialization logic
- Multiple creation methods needed
- Need to generate IDs or timestamps
- Business rules apply
- Multiple steps involved

```php
// Complex entity: factory is better
class UserFactory
{
    public static function create(string $email, string $password): User
    {
        return new User(
            id: UserId::generate(),              // Generated
            email: new Email($email),            // Validated
            password: HashedPassword::fromPlaintext($password), // Hashed
            roles: [Role::USER],                 // Default
            isActive: false,                     // Default
            createdAt: new \DateTimeImmutable(), // Auto
            updatedAt: new \DateTimeImmutable()  // Auto
        );
    }
}
```

---

## Types of Factories

### Type 1: Static Factory Methods

**Best for:** Simple factories without dependencies.

```php
class UserFactory
{
    public static function create(string $email, string $password): User
    {
        return new User(
            id: UserId::generate(),
            email: new Email($email),
            password: HashedPassword::fromPlaintext($password),
            roles: [Role::USER],
            isActive: false,
            createdAt: new \DateTimeImmutable()
        );
    }

    public static function createAdmin(string $email, string $password): User
    {
        return new User(
            id: UserId::generate(),
            email: new Email($email),
            password: HashedPassword::fromPlaintext($password),
            roles: [Role::USER, Role::ADMIN], // Different default
            isActive: true,                   // Different default
            createdAt: new \DateTimeImmutable()
        );
    }
}

// Usage
$user = UserFactory::create('user@example.com', 'password123');
$admin = UserFactory::createAdmin('admin@example.com', 'admin123');
```

---

### Type 2: Instance Factory with Dependencies

**Best for:** Factories needing services (e.g., ID generator, clock).

```php
final readonly class OrderFactory
{
    public function __construct(
        private OrderNumberGenerator $numberGenerator,
        private ClockInterface $clock,
    ) {}

    public function create(CustomerId $customerId, array $items): Order
    {
        return new Order(
            id: OrderId::generate(),
            orderNumber: $this->numberGenerator->next(), // Uses service
            customerId: $customerId,
            items: $items,
            status: OrderStatus::PENDING,
            createdAt: $this->clock->now() // Uses service
        );
    }
}

// Usage (via DI)
class CreateOrderHandler
{
    public function __construct(
        private OrderFactory $orderFactory,
        private OrderRepositoryInterface $orders,
    ) {}

    public function __invoke(CreateOrderCommand $command): void
    {
        $order = $this->orderFactory->create(
            customerId: $command->customerId,
            items: $command->items
        );

        $this->orders->save($order);
    }
}
```

---

### Type 3: Named Constructors (Alternative to Factory)

**Best for:** Value objects with multiple creation methods.

```php
final readonly class Money
{
    private function __construct(
        public int $amountInCents,
        public Currency $currency,
    ) {}

    // Named constructor: from cents
    public static function fromCents(int $cents, Currency $currency): self
    {
        return new self($cents, $currency);
    }

    // Named constructor: from float
    public static function fromFloat(float $amount, Currency $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    // Named constructor: zero amount
    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }
}

// Usage
$price1 = Money::fromCents(1999, Currency::USD);     // $19.99
$price2 = Money::fromFloat(19.99, Currency::USD);    // $19.99
$balance = Money::zero(Currency::USD);                // $0.00
```

---

## Input Validation in Factories

### Where to Validate?

**Rule:** Validate in value objects first, then check business rules in factory.

---

### Example: User Registration

```php
// 1. Value object validates format
final readonly class Email
{
    public function __construct(public string $value)
    {
        // Technical validation: email format
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException($value);
        }

        // Business validation: domain restriction
        if (!str_ends_with($value, '@company.com')) {
            throw new InvalidEmailDomainException($value);
        }
    }
}

final readonly class Password
{
    public function __construct(public string $value)
    {
        // Business validation: minimum length
        if (strlen($value) < 8) {
            throw new PasswordTooShortException();
        }

        // Business validation: complexity
        if (!preg_match('/[A-Z]/', $value)) {
            throw new PasswordNeedsUppercaseException();
        }

        if (!preg_match('/[0-9]/', $value)) {
            throw new PasswordNeedsNumberException();
        }
    }
}

// 2. Factory handles creation logic
class UserFactory
{
    public static function create(string $email, string $password): User
    {
        // Value objects validate themselves on construction
        $emailVO = new Email($email); // Throws if invalid
        $passwordVO = new Password($password); // Throws if invalid

        // Factory handles additional logic
        return new User(
            id: UserId::generate(),
            email: $emailVO,
            password: HashedPassword::fromPassword($passwordVO),
            roles: [Role::USER],
            isActive: false,
            createdAt: new \DateTimeImmutable()
        );
    }
}
```

**Separation of concerns:**
- **Value objects:** Technical + basic business validation
- **Factory:** Orchestration + defaults + complex business rules

---

### Example: Order with Business Rules

```php
class OrderFactory
{
    public function create(
        CustomerId $customerId,
        array $items, // OrderItem[]
        ShippingAddress $address
    ): Order {
        // Business rule: must have at least one item
        if (empty($items)) {
            throw new OrderMustHaveItemsException();
        }

        // Business rule: total must be above minimum
        $total = $this->calculateTotal($items);
        if ($total->isLessThan(Money::fromCents(500, Currency::USD))) {
            throw new OrderBelowMinimumException($total);
        }

        // Business rule: validate shipping to country
        if (!$this->canShipToCountry($address->country)) {
            throw new CannotShipToCountryException($address->country);
        }

        return new Order(
            id: OrderId::generate(),
            customerId: $customerId,
            items: $items,
            shippingAddress: $address,
            status: OrderStatus::PENDING,
            createdAt: new \DateTimeImmutable()
        );
    }

    private function calculateTotal(array $items): Money
    {
        return array_reduce(
            $items,
            fn(Money $sum, OrderItem $item) => $sum->add($item->getTotal()),
            Money::zero(Currency::USD)
        );
    }

    private function canShipToCountry(Country $country): bool
    {
        return in_array($country, [Country::US, Country::CA, Country::UK]);
    }
}
```

---

## Error Handling

### Strategy 1: Throw Domain Exceptions

```php
class UserFactory
{
    public static function create(string $email, string $password): User
    {
        try {
            $emailVO = new Email($email);
        } catch (InvalidEmailException $e) {
            throw new UserCreationFailedException(
                "Invalid email: {$e->getMessage()}",
                previous: $e
            );
        }

        try {
            $passwordVO = HashedPassword::fromPlaintext($password);
        } catch (PasswordTooShortException $e) {
            throw new UserCreationFailedException(
                "Invalid password: {$e->getMessage()}",
                previous: $e
            );
        }

        return new User(
            id: UserId::generate(),
            email: $emailVO,
            password: $passwordVO,
            isActive: false,
            createdAt: new \DateTimeImmutable()
        );
    }
}
```

**Benefits:**
- Domain exceptions propagate naturally
- Caller handles errors
- Clear error messages

---

### Strategy 2: Return Result Object (Railway-Oriented)

```php
// Result wrapper
final readonly class Result
{
    private function __construct(
        public bool $success,
        public mixed $value = null,
        public ?string $error = null,
    ) {}

    public static function ok(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function fail(string $error): self
    {
        return new self(false, error: $error);
    }
}

// Factory returns Result
class UserFactory
{
    public static function create(string $email, string $password): Result
    {
        try {
            $emailVO = new Email($email);
        } catch (InvalidEmailException $e) {
            return Result::fail("Invalid email: {$e->getMessage()}");
        }

        try {
            $passwordVO = HashedPassword::fromPlaintext($password);
        } catch (\Exception $e) {
            return Result::fail("Invalid password: {$e->getMessage()}");
        }

        $user = new User(
            id: UserId::generate(),
            email: $emailVO,
            password: $passwordVO,
            isActive: false,
            createdAt: new \DateTimeImmutable()
        );

        return Result::ok($user);
    }
}

// Usage
$result = UserFactory::create($email, $password);

if (!$result->success) {
    return new JsonResponse(['error' => $result->error], 400);
}

$user = $result->value;
```

**Benefits:**
- Errors are values, not exceptions
- Explicit error handling
- Functional programming style

---

## Factories with Value Objects

### Pattern: Factory Uses Value Object Factories

```php
// Value object with its own factory
final readonly class HashedPassword
{
    private function __construct(public string $hash) {}

    // Value object factory
    public static function fromPlaintext(string $plaintext): self
    {
        if (strlen($plaintext) < 8) {
            throw new PasswordTooShortException();
        }

        $hash = password_hash($plaintext, PASSWORD_ARGON2ID);

        return new self($hash);
    }

    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }
}

// Entity factory uses value object factory
class UserFactory
{
    public static function create(string $email, string $password): User
    {
        return new User(
            id: UserId::generate(),
            email: new Email($email),
            password: HashedPassword::fromPlaintext($password), // Uses VO factory
            isActive: false,
            createdAt: new \DateTimeImmutable()
        );
    }

    // Reconstruct from database (different VO factory method)
    public static function reconstitute(
        string $id,
        string $email,
        string $passwordHash,
        bool $isActive,
        string $createdAt
    ): User {
        return new User(
            id: UserId::fromString($id),
            email: new Email($email),
            password: HashedPassword::fromHash($passwordHash), // Different VO factory
            isActive: $isActive,
            createdAt: new \DateTimeImmutable($createdAt)
        );
    }
}
```

---

## Factory with Dependencies

### Example: Order Factory with Services

```php
final readonly class OrderFactory
{
    public function __construct(
        private OrderNumberGenerator $numberGenerator,
        private TaxCalculator $taxCalculator,
        private ShippingCalculator $shippingCalculator,
        private ClockInterface $clock,
    ) {}

    public function create(
        CustomerId $customerId,
        array $items,
        ShippingAddress $address
    ): Order {
        // Use injected services
        $orderNumber = $this->numberGenerator->next();
        $subtotal = $this->calculateSubtotal($items);
        $tax = $this->taxCalculator->calculate($subtotal, $address->country);
        $shipping = $this->shippingCalculator->calculate($address, $items);
        $total = $subtotal->add($tax)->add($shipping);

        return new Order(
            id: OrderId::generate(),
            orderNumber: $orderNumber,
            customerId: $customerId,
            items: $items,
            subtotal: $subtotal,
            tax: $tax,
            shipping: $shipping,
            total: $total,
            status: OrderStatus::PENDING,
            createdAt: $this->clock->now()
        );
    }

    private function calculateSubtotal(array $items): Money
    {
        return array_reduce(
            $items,
            fn(Money $sum, OrderItem $item) => $sum->add($item->getTotal()),
            Money::zero(Currency::USD)
        );
    }
}
```

**Configuration (services.yaml):**

```yaml
services:
    App\Order\Domain\Factory\OrderFactory:
        arguments:
            $numberGenerator: '@App\Order\Infrastructure\OrderNumberGenerator'
            $taxCalculator: '@App\Order\Domain\Service\TaxCalculator'
            $shippingCalculator: '@App\Order\Domain\Service\ShippingCalculator'
            $clock: '@App\Shared\Infrastructure\Clock\SystemClock'
```

---

## Testing Factories

### Test 1: Successful Creation

```php
class UserFactoryTest extends TestCase
{
    public function test_creates_user_with_valid_data(): void
    {
        $user = UserFactory::create(
            email: 'user@example.com',
            password: 'ValidPass123'
        );

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('user@example.com', $user->getEmail()->value);
        $this->assertFalse($user->isActive());
        $this->assertContains(Role::USER, $user->getRoles());
    }
}
```

---

### Test 2: Validation Failures

```php
class UserFactoryTest extends TestCase
{
    public function test_throws_on_invalid_email(): void
    {
        $this->expectException(InvalidEmailException::class);

        UserFactory::create(
            email: 'not-an-email',
            password: 'ValidPass123'
        );
    }

    public function test_throws_on_short_password(): void
    {
        $this->expectException(PasswordTooShortException::class);

        UserFactory::create(
            email: 'user@example.com',
            password: 'short'
        );
    }
}
```

---

### Test 3: Factory with Dependencies

```php
class OrderFactoryTest extends TestCase
{
    public function test_creates_order_with_calculated_totals(): void
    {
        $numberGenerator = $this->createMock(OrderNumberGenerator::class);
        $numberGenerator->method('next')->willReturn(new OrderNumber('ORD-001'));

        $taxCalculator = $this->createMock(TaxCalculator::class);
        $taxCalculator->method('calculate')->willReturn(Money::fromCents(200, Currency::USD));

        $shippingCalculator = $this->createMock(ShippingCalculator::class);
        $shippingCalculator->method('calculate')->willReturn(Money::fromCents(500, Currency::USD));

        $clock = new FixedClock(new \DateTimeImmutable('2024-01-15 10:00:00'));

        $factory = new OrderFactory($numberGenerator, $taxCalculator, $shippingCalculator, $clock);

        $order = $factory->create(
            customerId: CustomerId::generate(),
            items: [new OrderItem(ProductId::generate(), 2, Money::fromCents(1000, Currency::USD))],
            address: new ShippingAddress(/* ... */)
        );

        $this->assertEquals('ORD-001', $order->getOrderNumber()->value);
        $this->assertEquals(2700, $order->getTotal()->amountInCents); // 2000 + 200 + 500
    }
}
```

---

## Complete Real-World Examples

### Example 1: Product Factory

```php
namespace App\Catalog\Domain\Factory;

final class ProductFactory
{
    public static function create(
        string $name,
        string $description,
        int $priceInCents,
        int $initialStock
    ): Product {
        // Validation
        if (empty($name)) {
            throw new ProductNameCannotBeEmptyException();
        }

        if ($priceInCents < 0) {
            throw new ProductPriceCannotBeNegativeException();
        }

        if ($initialStock < 0) {
            throw new ProductStockCannotBeNegativeException();
        }

        // Create value objects
        $price = Money::fromCents($priceInCents, Currency::USD);
        $stock = new Stock($initialStock);

        // Create entity
        return new Product(
            id: ProductId::generate(),
            name: $name,
            description: $description,
            price: $price,
            stock: $stock,
            isActive: true,
            createdAt: new \DateTimeImmutable()
        );
    }

    public static function createOutOfStock(string $name, string $description, int $priceInCents): Product
    {
        $product = self::create($name, $description, $priceInCents, 0);
        $product->deactivate(); // Out of stock products are inactive
        return $product;
    }
}
```

---

### Example 2: Invoice Factory with Line Items

```php
namespace App\Billing\Domain\Factory;

final readonly class InvoiceFactory
{
    public function __construct(
        private InvoiceNumberGenerator $numberGenerator,
        private ClockInterface $clock,
    ) {}

    public function create(
        CustomerId $customerId,
        array $lineItems, // InvoiceLineItem[]
        ?DateTimeImmutable $dueDate = null
    ): Invoice {
        // Validation
        if (empty($lineItems)) {
            throw new InvoiceMustHaveLineItemsException();
        }

        // Calculate total
        $total = array_reduce(
            $lineItems,
            fn(Money $sum, InvoiceLineItem $item) => $sum->add($item->getTotal()),
            Money::zero(Currency::USD)
        );

        // Default due date: 30 days from now
        $dueDate ??= $this->clock->now()->modify('+30 days');

        // Generate invoice number
        $invoiceNumber = $this->numberGenerator->next();

        return new Invoice(
            id: InvoiceId::generate(),
            invoiceNumber: $invoiceNumber,
            customerId: $customerId,
            lineItems: $lineItems,
            total: $total,
            status: InvoiceStatus::DRAFT,
            issuedAt: $this->clock->now(),
            dueDate: $dueDate
        );
    }
}
```

---

### Example 3: Complex User Factory with Multiple Methods

```php
namespace App\User\Domain\Factory;

final class UserFactory
{
    // Regular user registration
    public static function create(string $email, string $password): User
    {
        return new User(
            id: UserId::generate(),
            email: new Email($email),
            password: HashedPassword::fromPlaintext($password),
            roles: [Role::USER],
            isActive: false, // Requires email verification
            isEmailVerified: false,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    // Admin user (no verification needed)
    public static function createAdmin(string $email, string $password): User
    {
        return new User(
            id: UserId::generate(),
            email: new Email($email),
            password: HashedPassword::fromPlaintext($password),
            roles: [Role::USER, Role::ADMIN],
            isActive: true,
            isEmailVerified: true,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    // OAuth user (no password)
    public static function createFromOAuth(string $email, OAuthProvider $provider, string $providerId): User
    {
        return new User(
            id: UserId::generate(),
            email: new Email($email),
            password: null, // No password for OAuth users
            roles: [Role::USER],
            isActive: true,
            isEmailVerified: true, // Trust OAuth provider
            oauthProvider: $provider,
            oauthProviderId: $providerId,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    // Reconstitute from database
    public static function reconstitute(array $data): User
    {
        return new User(
            id: UserId::fromString($data['id']),
            email: new Email($data['email']),
            password: $data['password'] ? HashedPassword::fromHash($data['password']) : null,
            roles: array_map(fn($role) => Role::from($role), $data['roles']),
            isActive: $data['is_active'],
            isEmailVerified: $data['is_email_verified'],
            oauthProvider: $data['oauth_provider'] ? OAuthProvider::from($data['oauth_provider']) : null,
            oauthProviderId: $data['oauth_provider_id'],
            createdAt: new \DateTimeImmutable($data['created_at']),
            updatedAt: new \DateTimeImmutable($data['updated_at'])
        );
    }
}
```

---

## Key Takeaways

1. **Use factories for complex entity creation** - Encapsulate logic in one place
2. **Validate in value objects first** - Then check business rules in factory
3. **Provide multiple creation methods** - Different use cases need different factories
4. **Handle errors with domain exceptions** - Clear, meaningful error messages
5. **Inject dependencies when needed** - Use instance factories for services
6. **Test factories thoroughly** - Ensure validation and defaults work correctly

---

**Next:** [Error Handling Strategy →](./error-handling-strategy.md)
