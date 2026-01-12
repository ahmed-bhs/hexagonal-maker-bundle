---
layout: default
title: Error Handling Strategy
parent: Advanced Topics
nav_order: 17
lang: en
lang_ref: fr/advanced/strategie-gestion-erreurs.md
---

# Error Handling Strategy in Hexagonal Architecture

## Table of Contents

1. [Exception Hierarchy](#exception-hierarchy)
2. [Domain vs Infrastructure Exceptions](#domain-vs-infrastructure-exceptions)
3. [Exception Translation at Boundaries](#exception-translation-at-boundaries)
4. [Handling Exceptions in Handlers](#handling-exceptions-in-handlers)
5. [Controller Exception Handling](#controller-exception-handling)
6. [Testing Error Scenarios](#testing-error-scenarios)
7. [Complete Real-World Examples](#complete-real-world-examples)

---

## Exception Hierarchy

### Recommended Structure

```
Exception (PHP)
├── DomainException (Custom base)
│   ├── ValidationException
│   │   ├── InvalidEmailException
│   │   ├── PasswordTooShortException
│   │   └── InvalidQuantityException
│   ├── BusinessRuleException
│   │   ├── OrderAlreadyShippedException
│   │   ├── InsufficientStockException
│   │   └── CannotCancelShippedOrderException
│   └── NotFoundException
│       ├── UserNotFoundException
│       ├── OrderNotFoundException
│       └── ProductNotFoundException
└── InfrastructureException (Custom base)
    ├── PersistenceException
    │   ├── DatabaseConnectionException
    │   └── UniqueConstraintViolationException
    ├── ExternalServiceException
    │   ├── PaymentGatewayException
    │   └── EmailSendingException
    └── CacheException
```

---

### Base Exception Classes

```php
namespace App\Shared\Domain\Exception;

// Base for all domain exceptions
abstract class DomainException extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

// Domain validation failures
abstract class ValidationException extends DomainException {}

// Business rule violations
abstract class BusinessRuleException extends DomainException {}

// Entity not found
abstract class NotFoundException extends DomainException
{
    public function __construct(string $entityName, string $identifier)
    {
        parent::__construct("$entityName with identifier '$identifier' not found");
    }
}
```

```php
namespace App\Shared\Infrastructure\Exception;

// Base for all infrastructure exceptions
abstract class InfrastructureException extends \Exception {}

// Database/persistence failures
abstract class PersistenceException extends InfrastructureException {}

// External service failures
abstract class ExternalServiceException extends InfrastructureException {}
```

---

## Domain vs Infrastructure Exceptions

### Domain Exceptions

**Purpose:** Represent business rule violations or domain-specific errors.

**Characteristics:**
- Thrown by domain layer (entities, value objects, domain services)
- Express business concepts
- Should be caught and handled by application layer
- May propagate to controller for user feedback

---

#### Example: Domain Validation Exceptions

```php
namespace App\User\Domain\Exception;

final class InvalidEmailException extends ValidationException
{
    public function __construct(string $email)
    {
        parent::__construct("Email '$email' is not valid");
    }
}

final class PasswordTooShortException extends ValidationException
{
    public function __construct()
    {
        parent::__construct("Password must be at least 8 characters");
    }
}
```

---

#### Example: Business Rule Exceptions

```php
namespace App\Order\Domain\Exception;

final class CannotShipCancelledOrderException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct("Cannot ship an order that has been cancelled");
    }
}

final class OrderAlreadyShippedException extends BusinessRuleException
{
    public function __construct(OrderId $orderId)
    {
        parent::__construct("Order {$orderId} has already been shipped");
    }
}

final class InsufficientStockException extends BusinessRuleException
{
    public function __construct(ProductId $productId, int $requested, int $available)
    {
        parent::__construct(
            "Insufficient stock for product {$productId}: requested $requested, available $available"
        );
    }
}
```

---

#### Example: Not Found Exceptions

```php
namespace App\User\Domain\Exception;

final class UserNotFoundException extends NotFoundException
{
    public function __construct(UserId $userId)
    {
        parent::__construct('User', $userId->toString());
    }

    public static function byEmail(string $email): self
    {
        $exception = new self(UserId::generate()); // Dummy ID
        $exception->message = "User with email '$email' not found";
        return $exception;
    }
}
```

---

### Infrastructure Exceptions

**Purpose:** Represent technical failures (database, network, external services).

**Characteristics:**
- Thrown by infrastructure layer (repositories, adapters)
- Express technical problems
- Should be caught by application layer and translated to domain exceptions or logged
- Generally should NOT propagate to domain layer

---

#### Example: Persistence Exceptions

```php
namespace App\Shared\Infrastructure\Exception;

final class DatabaseConnectionException extends PersistenceException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct("Database connection failed: $message", 0, $previous);
    }
}

final class UniqueConstraintViolationException extends PersistenceException
{
    public function __construct(string $field, string $value, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Unique constraint violation on field '$field' with value '$value'",
            0,
            $previous
        );
    }
}
```

---

#### Example: External Service Exceptions

```php
namespace App\Payment\Infrastructure\Exception;

final class PaymentGatewayException extends ExternalServiceException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct("Payment gateway error: $message", 0, $previous);
    }

    public static function timeout(): self
    {
        return new self("Payment gateway request timed out");
    }

    public static function invalidResponse(string $details): self
    {
        return new self("Invalid response from payment gateway: $details");
    }
}
```

---

## Exception Translation at Boundaries

### The Problem: Infrastructure Exceptions in Domain

```php
// ❌ BAD: Infrastructure exception leaks to handler
class RegisterUserHandler
{
    public function __invoke(RegisterUserCommand $command): void
    {
        $user = UserFactory::create($command->email, $command->password);

        try {
            $this->users->save($user);
        } catch (UniqueConstraintViolationException $e) {
            // ❌ Catching infrastructure exception in application layer
            throw new EmailAlreadyExistsException($command->email);
        }
    }
}
```

**Problem:** Application layer shouldn't know about `UniqueConstraintViolationException` (infrastructure detail).

---

### Solution: Translate in Repository (Adapter)

```php
// ✅ GOOD: Repository translates infrastructure → domain
class DoctrineUserRepository implements UserRepositoryInterface
{
    public function save(User $user): void
    {
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            // Check which field violated constraint
            if (str_contains($e->getMessage(), 'email')) {
                throw new EmailAlreadyExistsException($user->getEmail()->value);
            }

            // Re-throw as generic persistence exception
            throw new PersistenceException("Failed to save user", previous: $e);
        } catch (DriverException $e) {
            throw new DatabaseConnectionException($e->getMessage(), previous: $e);
        }
    }
}

// ✅ GOOD: Handler only deals with domain exceptions
class RegisterUserHandler
{
    public function __invoke(RegisterUserCommand $command): void
    {
        $user = UserFactory::create($command->email, $command->password);

        $this->users->save($user); // May throw EmailAlreadyExistsException (domain)
    }
}
```

**Benefits:**
- Application layer only knows about domain exceptions
- Infrastructure details hidden
- Easy to change database without affecting application

---

### Translation Pattern

```php
// Repository translates infrastructure → domain
class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        try {
            $this->entityManager->persist($order);
            $this->entityManager->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // Translate to domain exception
            throw new OrderAlreadyExistsException($order->getId());
        } catch (\Doctrine\DBAL\Exception\ConnectionException $e) {
            // Translate to infrastructure exception
            throw new DatabaseConnectionException($e->getMessage(), $e);
        } catch (\Exception $e) {
            // Catch-all: wrap in generic exception
            throw new PersistenceException("Failed to save order", previous: $e);
        }
    }

    public function findById(OrderId $id): ?Order
    {
        try {
            $order = $this->entityManager->find(Order::class, $id->toString());

            if ($order === null) {
                throw new OrderNotFoundException($id);
            }

            return $order;
        } catch (\Doctrine\DBAL\Exception\ConnectionException $e) {
            throw new DatabaseConnectionException($e->getMessage(), $e);
        }
    }
}
```

---

## Handling Exceptions in Handlers

### Strategy 1: Let Domain Exceptions Propagate

```php
// ✅ GOOD: Let domain exceptions bubble up
class CancelOrderHandler
{
    public function __invoke(CancelOrderCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);
        // May throw OrderNotFoundException

        $order->cancel();
        // May throw CannotCancelShippedOrderException

        $this->orders->save($order);
        // May throw PersistenceException
    }
}
```

**When to use:** Domain exceptions should reach the controller for proper HTTP response.

---

### Strategy 2: Catch and Transform

```php
// ✅ GOOD: Catch specific exception and provide context
class ProcessPaymentHandler
{
    public function __invoke(ProcessPaymentCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);

        try {
            $result = $this->paymentProcessor->charge($order->getTotal());
        } catch (PaymentGatewayException $e) {
            // Transform to domain exception with context
            throw new PaymentFailedException(
                orderId: $order->getId(),
                amount: $order->getTotal(),
                reason: $e->getMessage(),
                previous: $e
            );
        }

        $order->markAsPaid($result->transactionId);
        $this->orders->save($order);
    }
}
```

**When to use:** Add business context to infrastructure exceptions.

---

### Strategy 3: Catch and Recover

```php
// ✅ GOOD: Catch exception and attempt recovery
class SendOrderConfirmationHandler
{
    public function __invoke(SendOrderConfirmationCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);

        try {
            $this->emailSender->send(new OrderConfirmationEmail($order));
        } catch (EmailSendingException $e) {
            // Log error but don't fail the whole operation
            $this->logger->error("Failed to send order confirmation", [
                'orderId' => $order->getId(),
                'error' => $e->getMessage()
            ]);

            // Queue for retry later
            $this->retryQueue->add(new RetryEmailJob($order->getId()));

            // Don't throw - order is still confirmed even if email failed
        }
    }
}
```

**When to use:** Non-critical operations that shouldn't fail the entire use case.

---

### Strategy 4: Wrap Multiple Operations in Transaction

```php
// ✅ GOOD: Rollback on any exception
class CheckoutOrderHandler
{
    public function __invoke(CheckoutOrderCommand $command): void
    {
        $this->entityManager->beginTransaction();

        try {
            $order = $this->orders->findById($command->orderId);

            // May throw InsufficientStockException
            $this->inventory->reserveStock($order->getItems());

            // May throw PaymentFailedException
            $payment = $this->paymentProcessor->charge($order->getTotal());

            $order->confirm($payment->transactionId);
            $this->orders->save($order);

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e; // Re-throw to controller
        }
    }
}
```

**When to use:** Multiple operations that must succeed together (ACID transaction).

---

## Controller Exception Handling

### Strategy 1: Global Exception Handler (Recommended)

```php
namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
        private bool $debug = false,
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Log all exceptions
        $this->logger->error($exception->getMessage(), [
            'exception' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Map exception to HTTP response
        $response = $this->createResponse($exception);

        $event->setResponse($response);
    }

    private function createResponse(\Throwable $exception): JsonResponse
    {
        // Domain exceptions
        if ($exception instanceof ValidationException) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($exception instanceof BusinessRuleException) {
            return new JsonResponse([
                'error' => 'Business rule violation',
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($exception instanceof NotFoundException) {
            return new JsonResponse([
                'error' => 'Resource not found',
                'message' => $exception->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }

        // Infrastructure exceptions
        if ($exception instanceof InfrastructureException) {
            return new JsonResponse([
                'error' => 'Internal server error',
                'message' => $this->debug ? $exception->getMessage() : 'An error occurred',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // HTTP exceptions (Symfony)
        if ($exception instanceof HttpExceptionInterface) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
            ], $exception->getStatusCode());
        }

        // Unknown exceptions
        return new JsonResponse([
            'error' => 'Internal server error',
            'message' => $this->debug ? $exception->getMessage() : 'An unexpected error occurred',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
```

**Configuration (services.yaml):**

```yaml
services:
    App\Shared\Infrastructure\Http\ExceptionListener:
        arguments:
            $debug: '%kernel.debug%'
        tags:
            - { name: kernel.event_listener, event: kernel.exception }
```

---

### Strategy 2: Try-Catch in Controller (Less Recommended)

```php
#[Route('/api/users', methods: ['POST'])]
final class RegisterUserController
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $command = new RegisterUserCommand(
                email: $request->request->get('email'),
                password: $request->request->get('password')
            );

            $this->messageBus->dispatch($command);

            return new JsonResponse(['status' => 'created'], Response::HTTP_CREATED);
        } catch (InvalidEmailException $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        } catch (PasswordTooShortException $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        } catch (EmailAlreadyExistsException $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_CONFLICT);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return new JsonResponse([
                'error' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
```

**Drawback:** Repetitive, error-prone, hard to maintain.

---

## Testing Error Scenarios

### Test 1: Domain Exception Thrown

```php
class CancelOrderHandlerTest extends TestCase
{
    public function test_throws_when_order_already_shipped(): void
    {
        $order = new Order(OrderId::generate(), OrderStatus::SHIPPED);
        $orders = new InMemoryOrderRepository();
        $orders->save($order);

        $handler = new CancelOrderHandler($orders);
        $command = new CancelOrderCommand($order->getId());

        $this->expectException(CannotCancelShippedOrderException::class);

        $handler($command);
    }
}
```

---

### Test 2: Infrastructure Exception Translated

```php
class DoctrineUserRepositoryTest extends KernelTestCase
{
    public function test_throws_domain_exception_on_duplicate_email(): void
    {
        $repository = $this->getContainer()->get(UserRepositoryInterface::class);

        $user1 = UserFactory::create('test@example.com', 'password123');
        $repository->save($user1);

        $user2 = UserFactory::create('test@example.com', 'password456');

        $this->expectException(EmailAlreadyExistsException::class);

        $repository->save($user2); // Duplicate email
    }
}
```

---

### Test 3: Controller Exception Handling

```php
class RegisterUserControllerTest extends WebTestCase
{
    public function test_returns_400_on_invalid_email(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'not-an-email',
            'password' => 'ValidPass123',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonContains(['error' => 'Validation failed']);
    }

    public function test_returns_409_on_duplicate_email(): void
    {
        $client = static::createClient();

        // Create first user
        $client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'test@example.com',
            'password' => 'ValidPass123',
        ]));

        // Try to create duplicate
        $client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'test@example.com',
            'password' => 'AnotherPass456',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }
}
```

---

## Complete Real-World Examples

### Example 1: Order Processing with Multiple Exceptions

```php
// Domain exceptions
namespace App\Order\Domain\Exception;

final class InsufficientStockException extends BusinessRuleException
{
    public function __construct(ProductId $productId, int $requested, int $available)
    {
        parent::__construct(
            "Product {$productId} has insufficient stock: requested $requested, available $available"
        );
    }
}

final class PaymentFailedException extends BusinessRuleException
{
    public function __construct(
        public readonly OrderId $orderId,
        public readonly Money $amount,
        public readonly string $reason,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            "Payment failed for order {$orderId}: $reason",
            previous: $previous
        );
    }
}

// Handler with exception handling
final readonly class CheckoutOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private InventoryServiceInterface $inventory,
        private PaymentProcessorInterface $payment,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(CheckoutOrderCommand $command): void
    {
        $this->entityManager->beginTransaction();

        try {
            $order = $this->orders->findById($command->orderId);
            // May throw OrderNotFoundException

            // Reserve inventory
            try {
                $this->inventory->reserveStock($order->getItems());
            } catch (InsufficientStockException $e) {
                $this->logger->warning("Insufficient stock", [
                    'orderId' => $order->getId(),
                    'error' => $e->getMessage()
                ]);
                throw $e; // Re-throw to rollback transaction
            }

            // Process payment
            try {
                $payment = $this->payment->charge(new PaymentRequest(
                    amount: $order->getTotal(),
                    currency: Currency::USD,
                    orderId: $order->getId()
                ));
            } catch (PaymentGatewayException $e) {
                // Release reserved stock
                $this->inventory->releaseStock($order->getItems());

                throw new PaymentFailedException(
                    orderId: $order->getId(),
                    amount: $order->getTotal(),
                    reason: $e->getMessage(),
                    previous: $e
                );
            }

            // Confirm order
            $order->confirm($payment->transactionId);
            $this->orders->save($order);

            $this->entityManager->commit();

            $this->logger->info("Order checked out successfully", [
                'orderId' => $order->getId()
            ]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error("Checkout failed", [
                'orderId' => $command->orderId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
```

---

## Summary

| Exception Type | Where Thrown | Where Caught | Example |
|----------------|--------------|--------------|---------|
| **Validation** | Value objects | Controller (via global handler) | `InvalidEmailException` |
| **Business Rule** | Entities, domain services | Controller (via global handler) | `CannotCancelShippedOrderException` |
| **Not Found** | Repositories | Controller (via global handler) | `OrderNotFoundException` |
| **Infrastructure** | Adapters | **Translated in adapters** to domain exceptions | `DatabaseConnectionException` |
| **External Service** | Adapters | Handler (catch & transform or recover) | `PaymentGatewayException` |

### Key Principles

1. **Domain exceptions express business concepts** - Use clear, meaningful names
2. **Infrastructure exceptions should be translated** - Don't leak to application/domain
3. **Use global exception handler** - Centralize HTTP response mapping
4. **Log all exceptions** - Essential for debugging production issues
5. **Test exception scenarios** - Ensure proper handling and responses

---

**Next:** [Anti-Patterns and Pitfalls →](./anti-patterns-pitfalls.md)
