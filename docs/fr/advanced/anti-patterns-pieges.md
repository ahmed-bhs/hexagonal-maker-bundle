---
layout: default
title: Anti-Patterns et Pièges
parent: Sujets Avancés
nav_order: 18
lang: fr
lang_ref: advanced/anti-patterns-pitfalls.md
---

# Anti-Patterns et Pièges en Architecture Hexagonale

## Table des Matières

1. [Modèle de Domaine Anémique](#modèle-de-domaine-anémique)
2. [God Objects](#god-objects)
3. [Abstractions Fuyantes](#abstractions-fuyantes)
4. [Repository comme Service Locator](#repository-comme-service-locator)
5. [Problèmes de Gestion des Transactions](#problèmes-de-gestion-des-transactions)
6. [Problèmes de Cascade Delete](#problèmes-de-cascade-delete)
7. [Sur-ingénierie](#sur-ingénierie)
8. [Anti-Patterns de Test](#anti-patterns-de-test)

---

## Modèle de Domaine Anémique

### Le Problème

**Modèle de Domaine Anémique :** Les entités ne sont que des conteneurs de données avec getters/setters, toute la logique est dans les handlers.

```php
// ❌ MAUVAIS : Entité anémique
class Order
{
    private OrderStatus $status;
    private Money $total;
    private \DateTimeImmutable $shippedAt;

    // Seulement des getters et setters, pas de comportement
    public function getStatus(): OrderStatus { return $this->status; }
    public function setStatus(OrderStatus $status): void { $this->status = $status; }

    public function getTotal(): Money { return $this->total; }
    public function setTotal(Money $total): void { $this->total = $total; }

    public function getShippedAt(): ?\DateTimeImmutable { return $this->shippedAt; }
    public function setShippedAt(\DateTimeImmutable $shippedAt): void { $this->shippedAt = $shippedAt; }
}

// ❌ MAUVAIS : Toute la logique métier dans le handler
class ShipOrderHandler
{
    public function __invoke(ShipOrderCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);

        // Logique métier dans le handler (devrait être dans l'entité !)
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

**Problèmes :**
- Règles métier éparpillées dans les handlers
- Difficile à tester (besoin du handler pour tester la logique métier)
- Impossible de réutiliser la logique ailleurs
- L'entité n'est qu'un sac de données

---

### La Solution : Modèle de Domaine Riche

```php
// ✅ BON : Entité riche avec comportement
class Order
{
    private OrderStatus $status;
    private Money $total;
    private ?\DateTimeImmutable $shippedAt = null;

    // Logique métier encapsulée dans l'entité
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

    // Seulement des getters (pas de setters !)
    public function getStatus(): OrderStatus { return $this->status; }
    public function getTotal(): Money { return $this->total; }
    public function getShippedAt(): ?\DateTimeImmutable { return $this->shippedAt; }
}

// ✅ BON : Handler mince, juste de l'orchestration
class ShipOrderHandler
{
    public function __invoke(ShipOrderCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);

        $order->ship(); // Logique métier dans l'entité

        $this->orders->save($order);
        $this->eventDispatcher->dispatch(new OrderShippedEvent($order->getId()));
    }
}
```

**Bénéfices :**
- Logique métier dans le domaine où elle doit être
- Facile à tester (`$order->ship()` peut être testé sans handler)
- Réutilisable dans tous les cas d'usage
- L'entité protège ses invariants

---

## God Objects

### Le Problème : Handlers Obèses

**God Object :** Handler fait tout (validation, logique métier, orchestration, gestion d'erreurs).

```php
// ❌ MAUVAIS : God handler (200+ lignes)
class ProcessOrderHandler
{
    public function __invoke(ProcessOrderCommand $command): void
    {
        // Validation d'entrée
        if (empty($command->items)) {
            throw new InvalidOrderException("La commande doit avoir des articles");
        }

        // Vérification client
        $customer = $this->customers->findById($command->customerId);
        if (!$customer) {
            throw new CustomerNotFoundException($command->customerId);
        }

        if (!$customer->isActive()) {
            throw new InactiveCustomerException();
        }

        // Vérification inventaire pour chaque article
        foreach ($command->items as $item) {
            $product = $this->products->findById($item->productId);
            if (!$product) {
                throw new ProductNotFoundException($item->productId);
            }

            if ($product->getStock() < $item->quantity) {
                throw new InsufficientStockException($item->productId);
            }
        }

        // Calcul des prix
        $subtotal = 0;
        foreach ($command->items as $item) {
            $product = $this->products->findById($item->productId);
            $subtotal += $product->getPrice() * $item->quantity;
        }

        $tax = $subtotal * $this->taxCalculator->getTaxRate($command->shippingAddress);
        $shipping = $this->shippingCalculator->calculate($command->shippingAddress, $command->items);
        $total = $subtotal + $tax + $shipping;

        // Créer la commande
        $order = new Order(/* ... beaucoup de paramètres ... */);

        // Réserver le stock
        foreach ($command->items as $item) {
            $product = $this->products->findById($item->productId);
            $product->reserveStock($item->quantity);
            $this->products->save($product);
        }

        // Sauvegarder la commande
        $this->orders->save($order);

        // Envoyer les notifications
        $this->emailSender->send(new OrderConfirmationEmail($order));
        $this->eventDispatcher->dispatch(new OrderCreatedEvent($order->getId()));

        // ... 100 lignes de plus
    }
}
```

**Problèmes :**
- Trop de responsabilités
- Difficile à tester
- Difficile à maintenir
- Difficile à comprendre

---

### La Solution : Décomposer les Responsabilités

```php
// ✅ BON : Diviser en plusieurs handlers/services

// 1. Handler : orchestration uniquement
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
        // La factory gère la création + validation
        $order = $this->orderFactory->create(
            customerId: $command->customerId,
            items: $command->items,
            shippingAddress: $command->shippingAddress
        );

        // Le service domaine gère l'inventaire
        $this->inventory->reserveStock($order->getItems());

        // Le repository gère la persistance
        $this->orders->save($order);

        // Le dispatcher d'événements gère les notifications
        $this->events->dispatch(new OrderCreatedEvent($order->getId()));
    }
}

// 2. Factory : gère la logique de création complexe
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

// 3. Service domaine : gère les opérations cross-entités
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

**Bénéfices :**
- Principe de Responsabilité Unique
- Facile à tester chaque composant
- Facile à comprendre
- Composants réutilisables

---

## Abstractions Fuyantes

### Le Problème : Port Expose les Détails d'Implémentation

```php
// ❌ MAUVAIS : Port fuit les détails Doctrine
namespace App\User\Domain\Port;

use Doctrine\ORM\QueryBuilder;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    // ❌ Expose Doctrine QueryBuilder !
    public function createQueryBuilder(): QueryBuilder;

    // ❌ Expose une méthode spécifique à Doctrine !
    public function findBy(array $criteria, ?array $orderBy = null): array;
}
```

**Problème :** Le domaine dépend maintenant de Doctrine. Impossible de changer pour MongoDB sans modifier le domaine.

---

### La Solution : Port Centré sur le Domaine

```php
// ✅ BON : Port utilise uniquement le langage domaine
namespace App\User\Domain\Port;

interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function findActiveUsers(): array; // array<User>
}

// L'adaptateur implémente le port avec Doctrine
namespace App\User\Infrastructure\Persistence;

class DoctrineUserRepository implements UserRepositoryInterface
{
    public function findActiveUsers(): array
    {
        // Les détails Doctrine cachés dans l'adaptateur
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

**Bénéfices :**
- Domaine indépendant de l'infrastructure
- Peut changer de base de données sans toucher au domaine
- API claire, spécifique au domaine

---

## Repository comme Service Locator

### Le Problème : Repository Récupère des Entités Non Liées

```php
// ❌ MAUVAIS : Le repository de commandes récupère clients et produits
class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        // Récupère le client (non lié à la persistance de commande !)
        $customer = $this->entityManager->find(Customer::class, $order->getCustomerId());

        // Récupère les produits (non lié à la persistance de commande !)
        foreach ($order->getItems() as $item) {
            $product = $this->entityManager->find(Product::class, $item->getProductId());
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();
    }
}
```

**Problème :** Le repository devient un service locator, viole le Principe de Responsabilité Unique.

---

### La Solution : Repository Gère Seulement Son Agrégat

```php
// ✅ BON : Le repository de commandes gère seulement les commandes
class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        // C'est tout ! Pas de récupération d'autres entités
    }

    public function findById(OrderId $id): ?Order
    {
        return $this->entityManager->find(Order::class, $id->toString());
    }
}

// Le handler coordonne plusieurs repositories
class CreateOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private CustomerRepositoryInterface $customers, // Repository séparé
        private ProductRepositoryInterface $products,   // Repository séparé
    ) {}

    public function __invoke(CreateOrderCommand $command): void
    {
        // Le handler récupère les entités de leurs propres repositories
        $customer = $this->customers->findById($command->customerId);
        // Valider le client...

        foreach ($command->items as $item) {
            $product = $this->products->findById($item->productId);
            // Valider le produit...
        }

        $order = OrderFactory::create($customer, $command->items);
        $this->orders->save($order); // Le repository de commandes sauvegarde seulement les commandes
    }
}
```

---

## Problèmes de Gestion des Transactions

### Le Problème : Transactions Imbriquées ou Commits Implicites

```php
// ❌ MAUVAIS : Le handler démarre une transaction, mais le repository flush aussi
class CheckoutOrderHandler
{
    public function __invoke(CheckoutOrderCommand $command): void
    {
        $this->entityManager->beginTransaction();

        try {
            $order = $this->orders->findById($command->orderId);
            $order->confirm();

            $this->orders->save($order); // ❌ Appelle flush() dans la transaction !

            $this->inventory->reserveStock($order->getItems()); // ❌ Flush aussi !

            $this->entityManager->commit(); // Peut commit des données déjà flushées
        } catch (\Exception $e) {
            $this->entityManager->rollback(); // Peut ne pas tout rollback !
            throw $e;
        }
    }
}

class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        $this->entityManager->persist($order);
        $this->entityManager->flush(); // ❌ Flush immédiatement !
    }
}
```

**Problème :** Les flush intermédiaires empêchent un rollback propre.

---

### La Solution : Contrôle Explicite des Transactions

```php
// ✅ BON : Le handler contrôle la transaction, les repositories ne flush pas
class CheckoutOrderHandler
{
    public function __invoke(CheckoutOrderCommand $command): void
    {
        $this->entityManager->beginTransaction();

        try {
            $order = $this->orders->findById($command->orderId);
            $order->confirm();

            $this->orders->persist($order); // Juste persist, pas de flush

            $this->inventory->reserveStock($order->getItems()); // Juste persist

            $this->entityManager->flush(); // Flush tous les changements en une fois
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}

// Repository : méthode persist() (pas de flush)
class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function persist(Order $order): void
    {
        $this->entityManager->persist($order);
        // Pas de flush ! Laisser le handler contrôler la transaction
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
```

**Alternative : Utiliser le wrapper transactionnel de Doctrine**

```php
// ✅ BON : Utiliser l'helper transactionnel
class CheckoutOrderHandler
{
    public function __invoke(CheckoutOrderCommand $command): void
    {
        $this->entityManager->wrapInTransaction(function() use ($command) {
            $order = $this->orders->findById($command->orderId);
            $order->confirm();

            $this->orders->persist($order);
            $this->inventory->reserveStock($order->getItems());

            // Flush et commit automatiques, ou rollback sur exception
        });
    }
}
```

---

## Problèmes de Cascade Delete

### Le Problème : Cascade Deletes Accidentels

```php
// ❌ MAUVAIS : Supprimer une commande supprime le client !
#[ORM\Entity]
class Order
{
    #[ORM\ManyToOne(targetEntity: Customer::class, cascade: ['remove'])] // ❌ Faux !
    private Customer $customer;
}

// Supprimer une commande supprime accidentellement le client
$this->orders->delete($order); // ❌ Le client aussi supprimé !
```

**Problème :** Les opérations en cascade peuvent avoir des effets secondaires non intentionnels.

---

### La Solution : Frontières d'Agrégat Explicites

```php
// ✅ BON : Pas de cascade, suppression explicite
#[ORM\Entity]
class Order
{
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    private Customer $customer; // Pas de cascade
}

// Le handler contrôle explicitement ce qui est supprimé
class DeleteOrderHandler
{
    public function __invoke(DeleteOrderCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);

        // Supprimer seulement la commande, pas le client
        $this->orders->delete($order);

        // Si nécessaire, gérer le client séparément
        // $this->customers->delete($order->getCustomer());
    }
}
```

**Règle :** Cascade seulement dans les frontières d'agrégat.

```php
// ✅ BON : Cascade dans l'agrégat
#[ORM\Entity]
class Order
{
    #[ORM\OneToMany(
        targetEntity: OrderItem::class,
        mappedBy: 'order',
        cascade: ['persist', 'remove'] // ✅ OK : OrderItem fait partie de l'agrégat Order
    )]
    private array $items;
}
```

---

## Sur-ingénierie

### Le Problème : Abstraction Prématurée

```php
// ❌ MAUVAIS : Sur-ingénierie pour un simple CRUD
interface UserCreatorInterface { /* ... */ }
interface UserUpdaterInterface { /* ... */ }
interface UserDeleterInterface { /* ... */ }
interface UserFinderInterface { /* ... */ }
interface UserValidatorInterface { /* ... */ }
interface UserFactoryInterface { /* ... */ }
interface UserMapperInterface { /* ... */ }

class UserCreator implements UserCreatorInterface { /* ... */ }
class UserUpdater implements UserUpdaterInterface { /* ... */ }
// ... 10 classes de plus pour une simple gestion d'utilisateur
```

**Problème :** Trop d'abstractions pour des opérations simples.

---

### La Solution : Commencer Simple, Refactorer si Nécessaire

```php
// ✅ BON : Interface simple pour des besoins simples
interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function delete(User $user): void;
}

class DoctrineUserRepository implements UserRepositoryInterface
{
    // Implémentation simple
}

// Refactorer en interfaces séparées SEULEMENT quand :
// - Plusieurs implémentations nécessaires
// - Interface devient trop large
// - Différents clients ont besoin de différentes méthodes
```

**Règle :** YAGNI (You Ain't Gonna Need It) - N'ajoutez pas de complexité avant d'en avoir besoin.

---

## Anti-Patterns de Test

### Anti-Pattern 1 : Tester les Détails d'Implémentation

```php
// ❌ MAUVAIS : Tester l'état interne au lieu du comportement
class OrderTest extends TestCase
{
    public function test_ship_order(): void
    {
        $order = new Order(OrderId::generate(), OrderStatus::CONFIRMED);

        $order->ship();

        // ❌ Tester la propriété privée directement (avec réflexion)
        $reflection = new \ReflectionClass($order);
        $property = $reflection->getProperty('status');
        $property->setAccessible(true);

        $this->assertEquals(OrderStatus::SHIPPED, $property->getValue($order));
    }
}
```

**Problème :** Le test est couplé à l'implémentation, se casse lors du refactoring.

---

#### Solution : Tester le Comportement, Pas l'État

```php
// ✅ BON : Tester le comportement public
class OrderTest extends TestCase
{
    public function test_ship_order(): void
    {
        $order = new Order(OrderId::generate(), OrderStatus::CONFIRMED);

        $order->ship();

        // Tester la méthode publique
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

### Anti-Pattern 2 : Tout Mocker

```php
// ❌ MAUVAIS : Mocker les value objects et entités
class RegisterUserHandlerTest extends TestCase
{
    public function test_registers_user(): void
    {
        $user = $this->createMock(User::class); // ❌ Mocker l'entité
        $email = $this->createMock(Email::class); // ❌ Mocker le value object

        $factory = $this->createMock(UserFactory::class);
        $factory->method('create')->willReturn($user);

        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($user);

        $handler = new RegisterUserHandler($factory, $repository);
        $handler(new RegisterUserCommand('test@example.com', 'password'));
    }
}
```

**Problème :** Mocker les objets domaine annule l'intérêt du test.

---

#### Solution : Utiliser de Vrais Objets Domaine, Mocker Seulement l'Infrastructure

```php
// ✅ BON : Vrais objets domaine, mock infrastructure
class RegisterUserHandlerTest extends TestCase
{
    public function test_registers_user(): void
    {
        // Vraie factory et entités
        $repository = new InMemoryUserRepository(); // Fausse infrastructure

        $handler = new RegisterUserHandler($repository);

        $handler(new RegisterUserCommand('test@example.com', 'ValidPass123'));

        // Vérifier avec le vrai repository
        $this->assertTrue($repository->existsByEmail('test@example.com'));

        $user = $repository->findByEmail('test@example.com');
        $this->assertFalse($user->isActive());
    }
}
```

---

### Anti-Pattern 3 : Ne Pas Tester les Cas d'Erreur

```php
// ❌ MAUVAIS : Tester seulement le chemin heureux
class OrderTest extends TestCase
{
    public function test_create_order(): void
    {
        $order = OrderFactory::create($customerId, $items);

        $this->assertInstanceOf(Order::class, $order);
    }

    // ❌ Manque : tester items vides, client invalide, etc.
}
```

---

#### Solution : Tester Complètement les Cas d'Erreur

```php
// ✅ BON : Tester succès et échec
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

## Checklist Résumée

### ✅ Éviter Ces Anti-Patterns

- [ ] **Modèle de Domaine Anémique** - Les entités doivent avoir du comportement, pas juste des getters/setters
- [ ] **God Objects** - Les handlers doivent orchestrer, pas tout implémenter
- [ ] **Abstractions Fuyantes** - Les ports doivent utiliser le langage domaine, ne pas exposer l'infrastructure
- [ ] **Repository comme Service Locator** - Les repositories gèrent un seul agrégat
- [ ] **Problèmes de Gestion des Transactions** - Contrôler les transactions explicitement dans les handlers
- [ ] **Problèmes de Cascade Delete** - Cascade seulement dans les frontières d'agrégat
- [ ] **Sur-ingénierie** - Commencer simple, refactorer si nécessaire (YAGNI)
- [ ] **Tester les Détails d'Implémentation** - Tester le comportement, pas l'état interne
- [ ] **Tout Mocker** - Utiliser de vrais objets domaine, mocker seulement l'infrastructure
- [ ] **Ne Pas Tester les Cas d'Erreur** - Tester complètement les scénarios d'échec

---

## Référence Rapide : Bon vs Mauvais

| Anti-Pattern | Bonne Pratique |
|--------------|----------------|
| Getters/setters partout | Méthodes d'entité exprimant le comportement |
| Handler de 500 lignes | Décomposé en handler + factory + services |
| Port retourne QueryBuilder | Port retourne des objets domaine |
| Repository récupère d'autres entités | Handler coordonne plusieurs repositories |
| Repository appelle flush() | Handler contrôle les transactions |
| Cascade delete partout | Suppressions explicites, cascade seulement dans les agrégats |
| Interface pour tout | Commencer simple, refactorer si nécessaire |
| Tester les propriétés privées | Tester le comportement public |
| Mocker les entités | Utiliser de vraies entités, mocker l'infrastructure |
| Tester seulement le chemin heureux | Tester les erreurs et cas limites |

---

**C'est tout ! Vous avez maintenant un guide complet pour éviter les pièges courants en architecture hexagonale. Bonne chance !** 🎉
