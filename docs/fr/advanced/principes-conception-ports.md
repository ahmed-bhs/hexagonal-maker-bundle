---
layout: default_with_lang
title: Principes de Conception des Ports
parent: Sujets Avancés
nav_order: 12
lang: fr
lang_ref: advanced/port-design-principles.md
---

# Principes de Conception des Ports (Interfaces)

## Table des Matières

1. [Qu'est-ce qu'un Port ?](#quest-ce-quun-port)
2. [Conventions de Nommage](#conventions-de-nommage)
3. [Principe de Ségrégation des Interfaces (ISP)](#principe-de-ségrégation-des-interfaces-isp)
4. [Directives de Conception des Méthodes](#directives-de-conception-des-méthodes)
5. [Patterns de Ports Courants](#patterns-de-ports-courants)
6. [Anti-Patterns à Éviter](#anti-patterns-à-éviter)
7. [Exemples Concrets](#exemples-concrets)

---

## Qu'est-ce qu'un Port ?

**Un Port est une interface définie dans la couche Domaine qui déclare ce que le domaine a besoin du monde extérieur.**

```
Le Domaine définit :    "J'ai besoin de sauvegarder des utilisateurs"  → UserRepositoryInterface (Port)
L'Infrastructure fournit :  "Voici comment"                            → DoctrineUserRepository (Adaptateur)
```

### Caractéristiques Clés

- **Défini dans le Domaine** - Vit dans `Domain/Port/`
- **Implémenté par l'Infrastructure** - Adaptateurs dans `Infrastructure/`
- **Exprime l'Intention Métier** - Utilise le langage domaine, pas technique
- **Aucun Détail d'Implémentation** - Aucune mention de Doctrine, MySQL, HTTP, etc.

---

## Conventions de Nommage

### Ports Repository

✅ **BON :**
```php
interface UserRepositoryInterface       // Clair : gère les entités User
interface OrderRepositoryInterface      // Clair : gère les entités Order
interface ProductRepositoryInterface    // Clair : gère les entités Product
```

❌ **MAUVAIS :**
```php
interface UserDAO                       // Terme technique (Data Access Object)
interface UserPersistence               // Vague
interface IUserRepository               // Notation hongroise (éviter préfixe "I")
interface UserRepositoryPort            // Suffixe redondant
```

### Ports Service

✅ **BON :**
```php
interface EmailSenderInterface          // Capacité claire
interface PaymentProcessorInterface     // Responsabilité claire
interface NotificationServiceInterface  // Objectif clair
```

❌ **MAUVAIS :**
```php
interface EmailService                  // Trop vague
interface IEmailSender                  // Notation hongroise
interface SMTPEmailSender               // Détail d'implémentation fuite !
```

### Ports Query (CQRS)

✅ **BON :**
```php
interface UserQueryInterface            // Clair : opérations lecture pour Users
interface OrderQueryInterface           // Clair : opérations lecture pour Orders
interface ProductCatalogQueryInterface  // Clair : préoccupation lecture spécifique
```

❌ **MAUVAIS :**
```php
interface UserReader                    // Peu clair
interface GetUserQuery                  // Pas une capacité, mais une action
```

---

## Principe de Ségrégation des Interfaces (ISP)

> **"Les clients ne devraient pas être forcés de dépendre de méthodes qu'ils n'utilisent pas."**

### Le Problème : Interfaces Obèses

❌ **MAUVAIS : Interface Dieu**

```php
interface UserRepositoryInterface
{
    // Méthodes lecture
    public function findById(UserId $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findAll(): array;
    public function findActiveUsers(): array;
    public function findUsersByRole(string $role): array;
    public function searchUsers(string $query): array;

    // Méthodes écriture
    public function save(User $user): void;
    public function delete(User $user): void;

    // Méthodes statistiques
    public function countUsers(): int;
    public function countActiveUsers(): int;

    // Méthodes admin
    public function purgeInactiveUsers(): void;
    public function exportUsersToCSV(): string;

    // Méthodes notification
    public function findUsersToNotify(): array;
}
```

**Problèmes :**
- Handler qui sauvegarde uniquement des utilisateurs dépend de 15 méthodes dont il n'a pas besoin
- Difficile à tester (doit mocker 15 méthodes)
- Difficile à implémenter (adaptateur doit tout implémenter)
- Viole le Principe de Responsabilité Unique

### La Solution : Interfaces Ségrégées

✅ **BON : Ségrégé par Responsabilité**

```php
// Opérations écriture
interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function delete(User $user): void;
    public function existsByEmail(string $email): bool;
}

// Opérations lecture (pattern CQRS)
interface UserQueryInterface
{
    public function findById(UserId $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findActiveUsers(): array;
}

// Opérations admin
interface UserAdminInterface
{
    public function purgeInactiveUsers(): void;
    public function countUsers(): int;
}

// Opérations notification
interface UserNotificationQueryInterface
{
    public function findUsersToNotify(): array;
}
```

**Bénéfices :**
- Les handlers dépendent uniquement de ce dont ils ont besoin
- Facile à tester (mocker uniquement les méthodes pertinentes)
- Facile à implémenter (adaptateur implémente une responsabilité à la fois)
- Séparation claire des préoccupations

### Quand Diviser vs Garder Ensemble

✅ **Garder ensemble** quand les méthodes sont toujours utilisées ensemble :

```php
// BON : Ces méthodes appartiennent logiquement ensemble
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function delete(Order $order): void;
}
```

❌ **Diviser** quand les méthodes servent différents cas d'usage :

```php
// MAUVAIS : findPendingOrders est spécifique à un job en arrière-plan
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function findPendingOrders(): array; // ❌ Préoccupation différente !
}

// BON : Interface query séparée
interface OrderQueryInterface
{
    public function findPendingOrders(): array;
}
```

---

## Directives de Conception des Méthodes

### 1. Utiliser le Langage Domaine, Pas Technique

✅ **BON : Langage Domaine**

```php
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function findPendingOrders(): array; // Concept métier
}
```

❌ **MAUVAIS : Langage Technique**

```php
interface OrderRepositoryInterface
{
    public function persist(Order $order): void; // Technique (terme SQL)
    public function selectById(OrderId $id): ?Order; // Technique (terme SQL)
    public function queryByStatusPending(): array; // Détail d'implémentation technique
}
```

---

### 2. Retourner des Objets Domaine, Pas des Primitives

✅ **BON : Objets Domaine**

```php
interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;
    public function findActiveUsers(): array; // array<User>
}
```

❌ **MAUVAIS : Primitives**

```php
interface UserRepositoryInterface
{
    public function findById(string $id): ?array; // array n'est pas type-safe
    public function findActiveUsers(): array; // array<quoi?>
}
```

**Utiliser PHPDoc pour la clarté :**

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

### 3. Accepter des Types Domaine, Pas des Primitives

✅ **BON : Value Objects**

```php
interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;
    public function existsByEmail(Email $email): bool;
}
```

❌ **MAUVAIS : Primitives**

```php
interface UserRepositoryInterface
{
    public function findById(string $id): ?User;
    public function existsByEmail(string $email): bool; // Perd la validation domaine
}
```

**Pourquoi ?** Les value objects assurent que la validation se produit à la frontière, pas dans l'adaptateur.

---

### 4. Concevoir pour la Lisibilité

Les noms de méthode doivent se lire comme du langage naturel.

✅ **BON : Lisible**

```php
if ($this->users->existsByEmail($email)) {
    throw new EmailAlreadyExistsException();
}

$orders = $this->orders->findPendingOrders();
```

❌ **MAUVAIS : Peu Clair**

```php
if ($this->users->checkEmail($email)) { // Vérifier quoi sur l'email ?
    throw new EmailAlreadyExistsException();
}

$orders = $this->orders->getPending(); // Obtenir pending quoi ?
```

---

### 5. Éviter de Faire Fuir les Détails d'Implémentation

✅ **BON : Agnostique à l'Implémentation**

```php
interface NotificationServiceInterface
{
    public function send(Notification $notification): void;
}
```

❌ **MAUVAIS : Fuite d'Implémentation**

```php
interface NotificationServiceInterface
{
    public function sendViaSmtp(Notification $notification): void; // ❌ SMTP est détail d'implémentation
    public function sendViaSendGrid(Notification $notification): void; // ❌ SendGrid est détail d'implémentation
}
```

**Pourquoi ?** Le port doit décrire "quoi", pas "comment". L'implémentation peut changer sans changer le port.

---

### 6. Concevoir pour la Testabilité

Les ports doivent être faciles à mocker/stub.

✅ **BON : Simple, Testable**

```php
interface EmailSenderInterface
{
    public function send(Email $email): void;
}

// Test avec fake en mémoire
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

❌ **MAUVAIS : Difficile à Tester**

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

// Test nécessite configuration complexe avec nombreuses dépendances
```

---

## Patterns de Ports Courants

### Pattern 1 : Port Repository (Persistance)

**Objectif :** Gérer le cycle de vie de la racine d'agrégat (CRUD).

```php
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function delete(Order $order): void;
}
```

**Points Clés :**
- Un repository par racine d'agrégat
- Méthodes utilisent le langage domaine (`save`, pas `persist`)
- Retournent des entités domaine, pas des tableaux

---

### Pattern 2 : Port Query (Côté Lecture CQRS)

**Objectif :** Opérations de lecture optimisées, peut retourner des DTOs au lieu d'entités.

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

**Points Clés :**
- Séparé des opérations écriture (repository)
- Peut retourner des DTOs optimisés pour l'affichage
- Peut contourner les entités domaine pour la performance

---

### Pattern 3 : Port Service Externe

**Objectif :** Communiquer avec des systèmes externes (email, paiement, etc.).

```php
interface PaymentProcessorInterface
{
    public function charge(PaymentRequest $request): PaymentResult;
    public function refund(RefundRequest $request): RefundResult;
}
```

**Points Clés :**
- Exprime la capacité métier, pas le protocole technique
- Accepte/retourne des objets domaine
- Cache les détails d'implémentation (Stripe, PayPal, etc.)

---

### Pattern 4 : Port Dispatcher d'Événements

**Objectif :** Publier des événements domaine.

```php
interface EventDispatcherInterface
{
    public function dispatch(DomainEvent $event): void;
}
```

**Points Clés :**
- Interface générique pour tous les événements
- Les événements domaine sont des citoyens de première classe
- L'infrastructure gère le routage

---

## Anti-Patterns à Éviter

### Anti-Pattern 1 : Repository Générique

❌ **À ÉVITER :**

```php
interface GenericRepositoryInterface
{
    public function save(object $entity): void;
    public function findById(string $id): ?object;
    public function findAll(): array;
}
```

**Problèmes :**
- Type-unsafe (`object` et `string` sont trop génériques)
- Perd la spécificité domaine
- Aucun bénéfice type hinting

✅ **MIEUX :**

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

### Anti-Pattern 2 : Repositories avec Logique Métier

❌ **À ÉVITER :**

```php
interface OrderRepositoryInterface
{
    public function save(Order $order): void;

    // ❌ Logique métier fuitée dans le repository !
    public function cancelOrder(OrderId $id): void;
    public function shipOrder(OrderId $id, Address $address): void;
}
```

**Problème :** Repository devrait gérer la persistance, pas exécuter la logique métier.

✅ **MIEUX :**

```php
// Repository : persistance uniquement
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
}

// Logique métier dans les handlers
class CancelOrderHandler
{
    public function __invoke(CancelOrderCommand $command): void
    {
        $order = $this->orders->findById($command->orderId);
        $order->cancel(); // Logique métier dans l'entité
        $this->orders->save($order);
    }
}
```

---

### Anti-Pattern 3 : Ports Dépendant de l'Infrastructure

❌ **À ÉVITER :**

```php
use Doctrine\ORM\EntityManagerInterface;

interface UserRepositoryInterface
{
    public function getEntityManager(): EntityManagerInterface; // ❌ Fuite infrastructure !
}
```

**Problème :** Le domaine dépend maintenant de Doctrine.

✅ **MIEUX :**

```php
interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
    // Aucune mention de Doctrine, EntityManager, ou framework
}
```

---

## Exemples Concrets

### Exemple 1 : Système E-Commerce de Commandes

```php
// Opérations écriture
interface OrderRepositoryInterface
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function nextOrderNumber(): OrderNumber;
}

// Opérations lecture (optimisées pour affichage)
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

// Service paiement externe
interface PaymentProcessorInterface
{
    public function charge(PaymentRequest $request): PaymentResult;
    public function refund(RefundRequest $request): RefundResult;
    public function getTransactionStatus(TransactionId $id): TransactionStatus;
}

// Gestion inventaire
interface InventoryServiceInterface
{
    public function reserveStock(ProductId $productId, int $quantity): void;
    public function releaseStock(ProductId $productId, int $quantity): void;
    public function checkAvailability(ProductId $productId): int;
}
```

---

### Exemple 2 : Système d'Authentification Utilisateur

```php
// Persistance utilisateur
interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function existsByEmail(Email $email): bool;
}

// Hachage mot de passe (service externe)
interface PasswordHasherInterface
{
    public function hash(string $plaintext): string;
    public function verify(string $plaintext, string $hash): bool;
}

// Notifications email
interface EmailSenderInterface
{
    public function send(Email $email): void;
}

// Génération token
interface TokenGeneratorInterface
{
    public function generate(): string;
}
```

---

## Checklist de Décision

Lors de la conception d'un port, demandez-vous :

- [ ] Le nom de l'interface exprime-t-il clairement son objectif ?
- [ ] Les méthodes sont-elles nommées en langage domaine, pas en termes techniques ?
- [ ] Accepte-t-il/retourne-t-il des objets domaine (entités, value objects, DTOs) ?
- [ ] Est-il ségrégé (ISP)—les handlers dépendent uniquement de ce dont ils ont besoin ?
- [ ] Évite-t-il de faire fuir les détails d'implémentation ?
- [ ] Peut-il être facilement mocké/stubbé pour les tests ?
- [ ] Un expert métier comprendrait-il les noms de méthodes ?
- [ ] Est-il défini dans la couche Domaine (`Domain/Port/`) ?
- [ ] A-t-il zéro dépendance vers l'infrastructure ?

---

## Résumé

| Principe | Directive |
|----------|-----------|
| **Nommage** | Utiliser langage domaine, éviter termes techniques |
| **Ségrégation** | Diviser interfaces par responsabilité (ISP) |
| **Types** | Accepter/retourner objets domaine, pas primitives |
| **Clarté** | Méthodes doivent se lire comme langage naturel |
| **Abstraction** | Cacher complètement détails d'implémentation |
| **Testabilité** | Facile à mocker avec fakes en mémoire |
| **Localisation** | Toujours dans `Domain/Port/`, jamais dans Infrastructure |

---

**Suivant :** [Primary vs Secondary Adapters →](./adaptateurs-primary-secondary.md)
