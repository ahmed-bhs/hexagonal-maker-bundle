---
layout: default
title: Implémentation Pattern Factory
parent: Sujets Avancés
nav_order: 16
lang: fr
lang_ref: advanced/factory-pattern-guide.md
---

# Pattern Factory : Guide d'Implémentation Complet

## Table des Matières

1. [Pourquoi Utiliser des Factories ?](#pourquoi-utiliser-des-factories-)
2. [Factory vs Constructeur](#factory-vs-constructeur)
3. [Types de Factories](#types-de-factories)
4. [Validation Entrée dans Factories](#validation-entrée-dans-factories)
5. [Gestion des Erreurs](#gestion-des-erreurs)
6. [Factories avec Value Objects](#factories-avec-value-objects)
7. [Factory avec Dépendances](#factory-avec-dépendances)
8. [Tester les Factories](#tester-les-factories)
9. [Exemples Réels Complets](#exemples-réels-complets)

---

## Pourquoi Utiliser des Factories ?

### Le Problème : Création Entité Complexe

```php
// ❌ Créer entité directement : complexe et source d'erreurs
$user = new User(
    id: UserId::generate(),
    email: new Email($request->email),
    password: HashedPassword::fromPlaintext($request->password),
    roles: [Role::USER],
    isActive: false,
    createdAt: new \DateTimeImmutable(),
    updatedAt: new \DateTimeImmutable()
);

// Et si validation email lève exception ?
// Et si mot de passe trop court ?
// Et si on oublie de définir createdAt ?
```

**Problèmes :**
- Logique d'instanciation complexe éparpillée partout
- Facile d'oublier champs requis
- Difficile à maintenir (changement un endroit = changement partout)
- Règles métier dupliquées

---

### La Solution : Pattern Factory

```php
// ✅ Utiliser factory : simple et cohérent
$user = UserFactory::create(
    email: $request->email,
    password: $request->password
);

// Factory gère :
// - Génération ID
// - Validation email
// - Hashage mot de passe
// - Rôles par défaut
// - Timestamps
// - Règles métier
```

**Bénéfices :**
- **Encapsule logique de création complexe**
- **Force règles métier**
- **Fournit valeurs par défaut sensées**
- **Un seul endroit pour changer logique création**
- **API claire pour créer entités**

---

## Factory vs Constructeur

### Quand Utiliser Constructeur

✅ **Utiliser constructeur quand :**
- Création simple (pas de logique)
- Tous champs requis, pas de défauts
- Reconstruction depuis base données (hydratation)

```php
// Value object simple : constructeur convient
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
$email = new Email('user@example.com'); // Simple, clair
```

---

### Quand Utiliser Factory

✅ **Utiliser factory quand :**
- Logique d'initialisation complexe
- Plusieurs méthodes de création nécessaires
- Besoin de générer IDs ou timestamps
- Règles métier s'appliquent
- Plusieurs étapes impliquées

```php
// Entité complexe : factory est mieux
class UserFactory
{
    public static function create(string $email, string $password): User
    {
        return new User(
            id: UserId::generate(),              // Généré
            email: new Email($email),            // Validé
            password: HashedPassword::fromPlaintext($password), // Haché
            roles: [Role::USER],                 // Défaut
            isActive: false,                     // Défaut
            createdAt: new \DateTimeImmutable(), // Auto
            updatedAt: new \DateTimeImmutable()  // Auto
        );
    }
}
```

---

## Types de Factories

### Type 1 : Méthodes Factory Statiques

**Meilleur pour :** Factories simples sans dépendances.

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
            roles: [Role::USER, Role::ADMIN], // Défaut différent
            isActive: true,                   // Défaut différent
            createdAt: new \DateTimeImmutable()
        );
    }
}

// Usage
$user = UserFactory::create('user@example.com', 'password123');
$admin = UserFactory::createAdmin('admin@example.com', 'admin123');
```

---

### Type 2 : Factory Instance avec Dépendances

**Meilleur pour :** Factories nécessitant services (ex. générateur ID, horloge).

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
            orderNumber: $this->numberGenerator->next(), // Utilise service
            customerId: $customerId,
            items: $items,
            status: OrderStatus::PENDING,
            createdAt: $this->clock->now() // Utilise service
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

### Type 3 : Constructeurs Nommés (Alternative à Factory)

**Meilleur pour :** Value objects avec plusieurs méthodes création.

```php
final readonly class Money
{
    private function __construct(
        public int $amountInCents,
        public Currency $currency,
    ) {}

    // Constructeur nommé : depuis centimes
    public static function fromCents(int $cents, Currency $currency): self
    {
        return new self($cents, $currency);
    }

    // Constructeur nommé : depuis float
    public static function fromFloat(float $amount, Currency $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    // Constructeur nommé : montant zéro
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

## Validation Entrée dans Factories

### Où Valider ?

**Règle :** Valider dans value objects d'abord, puis vérifier règles métier dans factory.

---

### Exemple : Inscription Utilisateur

```php
// 1. Value object valide format
final readonly class Email
{
    public function __construct(public string $value)
    {
        // Validation technique : format email
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException($value);
        }

        // Validation métier : restriction domaine
        if (!str_ends_with($value, '@company.com')) {
            throw new InvalidEmailDomainException($value);
        }
    }
}

final readonly class Password
{
    public function __construct(public string $value)
    {
        // Validation métier : longueur minimale
        if (strlen($value) < 8) {
            throw new PasswordTooShortException();
        }

        // Validation métier : complexité
        if (!preg_match('/[A-Z]/', $value)) {
            throw new PasswordNeedsUppercaseException();
        }

        if (!preg_match('/[0-9]/', $value)) {
            throw new PasswordNeedsNumberException();
        }
    }
}

// 2. Factory gère logique création
class UserFactory
{
    public static function create(string $email, string $password): User
    {
        // Value objects se valident à la construction
        $emailVO = new Email($email); // Lève si invalide
        $passwordVO = new Password($password); // Lève si invalide

        // Factory gère logique supplémentaire
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

**Séparation des préoccupations :**
- **Value objects :** Validation technique + métier basique
- **Factory :** Orchestration + défauts + règles métier complexes

---

### Exemple : Commande avec Règles Métier

```php
class OrderFactory
{
    public function create(
        CustomerId $customerId,
        array $items, // OrderItem[]
        ShippingAddress $address
    ): Order {
        // Règle métier : doit avoir au moins un article
        if (empty($items)) {
            throw new OrderMustHaveItemsException();
        }

        // Règle métier : total doit être au-dessus minimum
        $total = $this->calculateTotal($items);
        if ($total->isLessThan(Money::fromCents(500, Currency::USD))) {
            throw new OrderBelowMinimumException($total);
        }

        // Règle métier : valider livraison vers pays
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

## Gestion des Erreurs

### Stratégie 1 : Lever Exceptions Domaine

```php
class UserFactory
{
    public static function create(string $email, string $password): User
    {
        try {
            $emailVO = new Email($email);
        } catch (InvalidEmailException $e) {
            throw new UserCreationFailedException(
                "Email invalide : {$e->getMessage()}",
                previous: $e
            );
        }

        try {
            $passwordVO = HashedPassword::fromPlaintext($password);
        } catch (PasswordTooShortException $e) {
            throw new UserCreationFailedException(
                "Mot de passe invalide : {$e->getMessage()}",
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

**Bénéfices :**
- Exceptions domaine se propagent naturellement
- Appelant gère erreurs
- Messages erreur clairs

---

### Stratégie 2 : Retourner Objet Result (Railway-Oriented)

```php
// Wrapper Result
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

// Factory retourne Result
class UserFactory
{
    public static function create(string $email, string $password): Result
    {
        try {
            $emailVO = new Email($email);
        } catch (InvalidEmailException $e) {
            return Result::fail("Email invalide : {$e->getMessage()}");
        }

        try {
            $passwordVO = HashedPassword::fromPlaintext($password);
        } catch (\Exception $e) {
            return Result::fail("Mot de passe invalide : {$e->getMessage()}");
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

**Bénéfices :**
- Erreurs sont valeurs, pas exceptions
- Gestion erreur explicite
- Style programmation fonctionnelle

---

## Factories avec Value Objects

### Pattern : Factory Utilise Factories Value Object

```php
// Value object avec sa propre factory
final readonly class HashedPassword
{
    private function __construct(public string $hash) {}

    // Factory value object
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

// Factory entité utilise factory value object
class UserFactory
{
    public static function create(string $email, string $password): User
    {
        return new User(
            id: UserId::generate(),
            email: new Email($email),
            password: HashedPassword::fromPlaintext($password), // Utilise factory VO
            isActive: false,
            createdAt: new \DateTimeImmutable()
        );
    }

    // Reconstituer depuis base de données (méthode factory VO différente)
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
            password: HashedPassword::fromHash($passwordHash), // Factory VO différente
            isActive: $isActive,
            createdAt: new \DateTimeImmutable($createdAt)
        );
    }
}
```

---

## Factory avec Dépendances

### Exemple : Factory Order avec Services

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
        // Utiliser services injectés
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

**Configuration (services.yaml) :**

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

## Tester les Factories

### Test 1 : Création Réussie

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

### Test 2 : Échecs Validation

```php
class UserFactoryTest extends TestCase
{
    public function test_throws_on_invalid_email(): void
    {
        $this->expectException(InvalidEmailException::class);

        UserFactory::create(
            email: 'pas-un-email',
            password: 'ValidPass123'
        );
    }

    public function test_throws_on_short_password(): void
    {
        $this->expectException(PasswordTooShortException::class);

        UserFactory::create(
            email: 'user@example.com',
            password: 'court'
        );
    }
}
```

---

### Test 3 : Factory avec Dépendances

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

## Exemples Réels Complets

### Exemple 1 : Factory Product

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

        // Créer value objects
        $price = Money::fromCents($priceInCents, Currency::USD);
        $stock = new Stock($initialStock);

        // Créer entité
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
        $product->deactivate(); // Produits en rupture sont inactifs
        return $product;
    }
}
```

---

### Exemple 2 : Factory Invoice avec Lignes

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

        // Calculer total
        $total = array_reduce(
            $lineItems,
            fn(Money $sum, InvoiceLineItem $item) => $sum->add($item->getTotal()),
            Money::zero(Currency::USD)
        );

        // Date échéance par défaut : 30 jours depuis maintenant
        $dueDate ??= $this->clock->now()->modify('+30 days');

        // Générer numéro facture
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

### Exemple 3 : Factory User Complexe avec Plusieurs Méthodes

```php
namespace App\User\Domain\Factory;

final class UserFactory
{
    // Inscription utilisateur régulier
    public static function create(string $email, string $password): User
    {
        return new User(
            id: UserId::generate(),
            email: new Email($email),
            password: HashedPassword::fromPlaintext($password),
            roles: [Role::USER],
            isActive: false, // Requiert vérification email
            isEmailVerified: false,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    // Utilisateur admin (pas de vérification nécessaire)
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

    // Utilisateur OAuth (pas de mot de passe)
    public static function createFromOAuth(string $email, OAuthProvider $provider, string $providerId): User
    {
        return new User(
            id: UserId::generate(),
            email: new Email($email),
            password: null, // Pas de mot de passe pour utilisateurs OAuth
            roles: [Role::USER],
            isActive: true,
            isEmailVerified: true, // Faire confiance fournisseur OAuth
            oauthProvider: $provider,
            oauthProviderId: $providerId,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    // Reconstituer depuis base de données
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

## Points Clés à Retenir

1. **Utiliser factories pour création entité complexe** - Encapsuler logique en un endroit
2. **Valider dans value objects d'abord** - Puis vérifier règles métier dans factory
3. **Fournir plusieurs méthodes création** - Cas d'usage différents nécessitent factories différentes
4. **Gérer erreurs avec exceptions domaine** - Messages erreur clairs et significatifs
5. **Injecter dépendances si nécessaire** - Utiliser factories instance pour services
6. **Tester factories minutieusement** - Assurer validation et défauts fonctionnent correctement

---

**Suivant :** [Stratégie Gestion Erreurs →](./strategie-gestion-erreurs.md)
