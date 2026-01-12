---
layout: default_with_lang
title: Stratégie de Gestion des Erreurs
parent: Sujets Avancés
nav_order: 17
lang: fr
lang_ref: advanced/error-handling-strategy.md
---

# Stratégie de Gestion des Erreurs en Architecture Hexagonale

## Table des Matières

1. [Hiérarchie d'Exceptions](#hiérarchie-dexceptions)
2. [Exceptions Domaine vs Infrastructure](#exceptions-domaine-vs-infrastructure)
3. [Traduction d'Exceptions aux Frontières](#traduction-dexceptions-aux-frontières)
4. [Gestion des Exceptions dans les Handlers](#gestion-des-exceptions-dans-les-handlers)
5. [Gestion des Exceptions dans les Contrôleurs](#gestion-des-exceptions-dans-les-contrôleurs)
6. [Tests des Scénarios d'Erreur](#tests-des-scénarios-derreur)
7. [Exemples Concrets Complets](#exemples-concrets-complets)

---

## Hiérarchie d'Exceptions

### Structure Recommandée

```
Exception (PHP)
├── DomainException (Base personnalisée)
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
└── InfrastructureException (Base personnalisée)
    ├── PersistenceException
    │   ├── DatabaseConnectionException
    │   └── UniqueConstraintViolationException
    ├── ExternalServiceException
    │   ├── PaymentGatewayException
    │   └── EmailSendingException
    └── CacheException
```

---

### Classes d'Exception de Base

```php
namespace App\Shared\Domain\Exception;

// Base pour toutes les exceptions domaine
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

// Échecs de validation domaine
abstract class ValidationException extends DomainException {}

// Violations de règles métier
abstract class BusinessRuleException extends DomainException {}

// Entité non trouvée
abstract class NotFoundException extends DomainException
{
    public function __construct(string $entityName, string $identifier)
    {
        parent::__construct("$entityName avec identifiant '$identifier' introuvable");
    }
}
```

```php
namespace App\Shared\Infrastructure\Exception;

// Base pour toutes les exceptions infrastructure
abstract class InfrastructureException extends \Exception {}

// Échecs de base de données/persistance
abstract class PersistenceException extends InfrastructureException {}

// Échecs de service externe
abstract class ExternalServiceException extends InfrastructureException {}
```

---

## Exceptions Domaine vs Infrastructure

### Exceptions Domaine

**Objectif :** Représenter les violations de règles métier ou erreurs spécifiques au domaine.

**Caractéristiques :**
- Lancées par la couche domaine (entités, value objects, services domaine)
- Expriment des concepts métier
- Doivent être capturées et gérées par la couche application
- Peuvent se propager au contrôleur pour feedback utilisateur

---

#### Exemple : Exceptions de Validation Domaine

```php
namespace App\User\Domain\Exception;

final class InvalidEmailException extends ValidationException
{
    public function __construct(string $email)
    {
        parent::__construct("L'email '$email' n'est pas valide");
    }
}

final class PasswordTooShortException extends ValidationException
{
    public function __construct()
    {
        parent::__construct("Le mot de passe doit faire au moins 8 caractères");
    }
}
```

---

#### Exemple : Exceptions de Règle Métier

```php
namespace App\Order\Domain\Exception;

final class CannotShipCancelledOrderException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct("Impossible d'expédier une commande annulée");
    }
}

final class OrderAlreadyShippedException extends BusinessRuleException
{
    public function __construct(OrderId $orderId)
    {
        parent::__construct("La commande {$orderId} a déjà été expédiée");
    }
}

final class InsufficientStockException extends BusinessRuleException
{
    public function __construct(ProductId $productId, int $requested, int $available)
    {
        parent::__construct(
            "Stock insuffisant pour le produit {$productId} : demandé $requested, disponible $available"
        );
    }
}
```

---

#### Exemple : Exceptions Not Found

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
        $exception = new self(UserId::generate()); // ID factice
        $exception->message = "Utilisateur avec email '$email' introuvable";
        return $exception;
    }
}
```

---

### Exceptions Infrastructure

**Objectif :** Représenter les échecs techniques (base de données, réseau, services externes).

**Caractéristiques :**
- Lancées par la couche infrastructure (repositories, adaptateurs)
- Expriment des problèmes techniques
- Doivent être capturées par la couche application et traduites en exceptions domaine ou loggées
- Ne doivent généralement PAS se propager à la couche domaine

---

#### Exemple : Exceptions de Persistance

```php
namespace App\Shared\Infrastructure\Exception;

final class DatabaseConnectionException extends PersistenceException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct("Échec de connexion à la base de données : $message", 0, $previous);
    }
}

final class UniqueConstraintViolationException extends PersistenceException
{
    public function __construct(string $field, string $value, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Violation de contrainte unique sur le champ '$field' avec la valeur '$value'",
            0,
            $previous
        );
    }
}
```

---

#### Exemple : Exceptions de Service Externe

```php
namespace App\Payment\Infrastructure\Exception;

final class PaymentGatewayException extends ExternalServiceException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct("Erreur passerelle de paiement : $message", 0, $previous);
    }

    public static function timeout(): self
    {
        return new self("La requête vers la passerelle de paiement a expiré");
    }

    public static function invalidResponse(string $details): self
    {
        return new self("Réponse invalide de la passerelle de paiement : $details");
    }
}
```

---

## Traduction d'Exceptions aux Frontières

### Le Problème : Exceptions Infrastructure dans le Domaine

```php
// ❌ MAUVAIS : Exception infrastructure fuite vers le handler
class RegisterUserHandler
{
    public function __invoke(RegisterUserCommand $command): void
    {
        $user = UserFactory::create($command->email, $command->password);

        try {
            $this->users->save($user);
        } catch (UniqueConstraintViolationException $e) {
            // ❌ Capture d'exception infrastructure dans la couche application
            throw new EmailAlreadyExistsException($command->email);
        }
    }
}
```

**Problème :** La couche application ne devrait pas connaître `UniqueConstraintViolationException` (détail infrastructure).

---

### Solution : Traduire dans le Repository (Adaptateur)

```php
// ✅ BON : Le repository traduit infrastructure → domaine
class DoctrineUserRepository implements UserRepositoryInterface
{
    public function save(User $user): void
    {
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            // Vérifier quel champ a violé la contrainte
            if (str_contains($e->getMessage(), 'email')) {
                throw new EmailAlreadyExistsException($user->getEmail()->value);
            }

            // Re-lancer comme exception de persistance générique
            throw new PersistenceException("Échec de sauvegarde utilisateur", previous: $e);
        } catch (DriverException $e) {
            throw new DatabaseConnectionException($e->getMessage(), previous: $e);
        }
    }
}

// ✅ BON : Le handler ne traite que des exceptions domaine
class RegisterUserHandler
{
    public function __invoke(RegisterUserCommand $command): void
    {
        $user = UserFactory::create($command->email, $command->password);

        $this->users->save($user); // Peut lancer EmailAlreadyExistsException (domaine)
    }
}
```

**Bénéfices :**
- La couche application ne connaît que les exceptions domaine
- Détails infrastructure cachés
- Facile de changer de base de données sans affecter l'application

---

### Pattern de Traduction

```php
// Le repository traduit infrastructure → domaine
class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        try {
            $this->entityManager->persist($order);
            $this->entityManager->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // Traduire en exception domaine
            throw new OrderAlreadyExistsException($order->getId());
        } catch (\Doctrine\DBAL\Exception\ConnectionException $e) {
            // Traduire en exception infrastructure
            throw new DatabaseConnectionException($e->getMessage(), $e);
        } catch (\Exception $e) {
            // Capture générale : envelopper dans exception générique
            throw new PersistenceException("Échec de sauvegarde commande", previous: $e);
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

## Gestion des Exceptions dans les Handlers

### Stratégie 1 : Laisser les Exceptions Domaine se Propager

```php
// ✅ BON : Laisser les exceptions domaine remonter
class CancelOrderHandler
{
    public function __invoke(CancelOrderCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);
        // Peut lancer OrderNotFoundException

        $order->cancel();
        // Peut lancer CannotCancelShippedOrderException

        $this->orders->save($order);
        // Peut lancer PersistenceException
    }
}
```

**Quand utiliser :** Les exceptions domaine doivent atteindre le contrôleur pour une réponse HTTP appropriée.

---

### Stratégie 2 : Capturer et Transformer

```php
// ✅ BON : Capturer une exception spécifique et fournir du contexte
class ProcessPaymentHandler
{
    public function __invoke(ProcessPaymentCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);

        try {
            $result = $this->paymentProcessor->charge($order->getTotal());
        } catch (PaymentGatewayException $e) {
            // Transformer en exception domaine avec contexte
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

**Quand utiliser :** Ajouter du contexte métier aux exceptions infrastructure.

---

### Stratégie 3 : Capturer et Récupérer

```php
// ✅ BON : Capturer l'exception et tenter une récupération
class SendOrderConfirmationHandler
{
    public function __invoke(SendOrderConfirmationCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);

        try {
            $this->emailSender->send(new OrderConfirmationEmail($order));
        } catch (EmailSendingException $e) {
            // Logger l'erreur mais ne pas faire échouer toute l'opération
            $this->logger->error("Échec envoi confirmation commande", [
                'orderId' => $order->getId(),
                'error' => $e->getMessage()
            ]);

            // Mettre en file d'attente pour réessai ultérieur
            $this->retryQueue->add(new RetryEmailJob($order->getId()));

            // Ne pas lancer - commande toujours confirmée même si email échoué
        }
    }
}
```

**Quand utiliser :** Opérations non critiques qui ne doivent pas faire échouer le cas d'usage entier.

---

### Stratégie 4 : Envelopper Plusieurs Opérations dans une Transaction

```php
// ✅ BON : Rollback sur toute exception
class CheckoutOrderHandler
{
    public function __invoke(CheckoutOrderCommand $command): void
    {
        $this->entityManager->beginTransaction();

        try {
            $order = $this->orders->findById($command->orderId);

            // Peut lancer InsufficientStockException
            $this->inventory->reserveStock($order->getItems());

            // Peut lancer PaymentFailedException
            $payment = $this->paymentProcessor->charge($order->getTotal());

            $order->confirm($payment->transactionId);
            $this->orders->save($order);

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e; // Re-lancer vers le contrôleur
        }
    }
}
```

**Quand utiliser :** Plusieurs opérations qui doivent réussir ensemble (transaction ACID).

---

## Gestion des Exceptions dans les Contrôleurs

### Stratégie 1 : Global Exception Handler (Recommandé)

```php
namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

final class ExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
        private bool $debug = false,
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Logger toutes les exceptions
        $this->logger->error($exception->getMessage(), [
            'exception' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Mapper l'exception vers une réponse HTTP
        $response = $this->createResponse($exception);

        $event->setResponse($response);
    }

    private function createResponse(\Throwable $exception): JsonResponse
    {
        // Exceptions domaine
        if ($exception instanceof ValidationException) {
            return new JsonResponse([
                'error' => 'Échec de validation',
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($exception instanceof BusinessRuleException) {
            return new JsonResponse([
                'error' => 'Violation de règle métier',
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($exception instanceof NotFoundException) {
            return new JsonResponse([
                'error' => 'Ressource introuvable',
                'message' => $exception->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }

        // Exceptions infrastructure
        if ($exception instanceof InfrastructureException) {
            return new JsonResponse([
                'error' => 'Erreur serveur interne',
                'message' => $this->debug ? $exception->getMessage() : 'Une erreur est survenue',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Exceptions inconnues
        return new JsonResponse([
            'error' => 'Erreur serveur interne',
            'message' => $this->debug ? $exception->getMessage() : 'Une erreur inattendue est survenue',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
```

**Configuration (services.yaml) :**

```yaml
services:
    App\Shared\Infrastructure\Http\ExceptionListener:
        arguments:
            $debug: '%kernel.debug%'
        tags:
            - { name: kernel.event_listener, event: kernel.exception }
```

---

## Tests des Scénarios d'Erreur

### Test 1 : Exception Domaine Lancée

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

### Test 2 : Exception Infrastructure Traduite

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

        $repository->save($user2); // Email dupliqué
    }
}
```

---

### Test 3 : Gestion des Exceptions dans le Contrôleur

```php
class RegisterUserControllerTest extends WebTestCase
{
    public function test_returns_400_on_invalid_email(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'pas-un-email',
            'password' => 'ValidPass123',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonContains(['error' => 'Échec de validation']);
    }

    public function test_returns_409_on_duplicate_email(): void
    {
        $client = static::createClient();

        // Créer le premier utilisateur
        $client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'test@example.com',
            'password' => 'ValidPass123',
        ]));

        // Essayer de créer un doublon
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

## Exemples Concrets Complets

### Exemple : Traitement de Commande avec Exceptions Multiples

```php
// Exceptions domaine
namespace App\Order\Domain\Exception;

final class InsufficientStockException extends BusinessRuleException
{
    public function __construct(ProductId $productId, int $requested, int $available)
    {
        parent::__construct(
            "Le produit {$productId} a un stock insuffisant : demandé $requested, disponible $available"
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
            "Échec du paiement pour la commande {$orderId} : $reason",
            previous: $previous
        );
    }
}

// Handler avec gestion d'exceptions
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
            // Peut lancer OrderNotFoundException

            // Réserver l'inventaire
            try {
                $this->inventory->reserveStock($order->getItems());
            } catch (InsufficientStockException $e) {
                $this->logger->warning("Stock insuffisant", [
                    'orderId' => $order->getId(),
                    'error' => $e->getMessage()
                ]);
                throw $e; // Re-lancer pour rollback transaction
            }

            // Traiter le paiement
            try {
                $payment = $this->payment->charge(new PaymentRequest(
                    amount: $order->getTotal(),
                    currency: Currency::USD,
                    orderId: $order->getId()
                ));
            } catch (PaymentGatewayException $e) {
                // Libérer le stock réservé
                $this->inventory->releaseStock($order->getItems());

                throw new PaymentFailedException(
                    orderId: $order->getId(),
                    amount: $order->getTotal(),
                    reason: $e->getMessage(),
                    previous: $e
                );
            }

            // Confirmer la commande
            $order->confirm($payment->transactionId);
            $this->orders->save($order);

            $this->entityManager->commit();

            $this->logger->info("Commande validée avec succès", [
                'orderId' => $order->getId()
            ]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error("Échec de validation", [
                'orderId' => $command->orderId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
```

---

## Résumé

| Type d'Exception | Où Lancée | Où Capturée | Exemple |
|------------------|-----------|-------------|---------|
| **Validation** | Value objects | Contrôleur (via handler global) | `InvalidEmailException` |
| **Règle Métier** | Entités, services domaine | Contrôleur (via handler global) | `CannotCancelShippedOrderException` |
| **Not Found** | Repositories | Contrôleur (via handler global) | `OrderNotFoundException` |
| **Infrastructure** | Adaptateurs | **Traduite dans adaptateurs** vers exceptions domaine | `DatabaseConnectionException` |
| **Service Externe** | Adaptateurs | Handler (capturer & transformer ou récupérer) | `PaymentGatewayException` |

### Principes Clés

1. **Les exceptions domaine expriment des concepts métier** - Utilisez des noms clairs et significatifs
2. **Les exceptions infrastructure doivent être traduites** - Ne pas fuiter vers application/domaine
3. **Utiliser un handler d'exceptions global** - Centraliser le mapping des réponses HTTP
4. **Logger toutes les exceptions** - Essentiel pour déboguer les problèmes en production
5. **Tester les scénarios d'exception** - Assurer une gestion et des réponses appropriées

---

**Suivant :** [Anti-Patterns et Pièges →](./anti-patterns-pieges.md)
