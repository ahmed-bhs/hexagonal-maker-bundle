---
layout: default
title: Repository Pattern
parent: Advanced Topics
nav_order: 5
---

# Le Pattern Repository en Architecture Hexagonale

Ce guide explique comment implémenter correctement le pattern Repository dans une architecture hexagonale, et pourquoi il ne faut pas utiliser directement l'EntityManager.

---

## Table des matières
{: .no_toc .text-delta }

1. TOC
{:toc}

---

## Qu'est-ce que le Pattern Repository ?

Un Repository se comporte comme une **collection d'objets du domaine**. Il agit comme un intermédiaire entre la couche domaine et la couche de persistance.

### Caractéristiques principales

1. **Collection typée** : Un `GalinetteRepository` ne contient que des objets `Galinette`
2. **Unicité** : Impossible d'ajouter deux fois le même objet (identité unique)
3. **Abstraction de la persistance** : Le développeur manipule une collection, le repository gère la persistance
4. **Contrat dans le domaine** : L'interface est définie dans le domaine, l'implémentation dans l'infrastructure

---

## Pourquoi NE PAS utiliser directement l'EntityManager ?

### MAUVAISE PRATIQUE: Problèmes avec l'EntityManager direct

```php
// ❌ MAUVAISE PRATIQUE
class CreateUserHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function __invoke(CreateUserCommand $command): void
    {
        $user = new User($command->email, $command->name);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
```

**Problèmes :**

1. **Couplage fort à Doctrine** : Votre domaine dépend de Doctrine
2. **Difficile à tester** : Vous devez mocker l'EntityManager
3. **Violation du principe d'inversion de dépendance** : Le domaine dépend de l'infrastructure
4. **Pas de point central de persistance** : La logique de persistance est éparpillée
5. **Migration difficile** : Changer de solution de persistance = refactoring massif

### BONNE PRATIQUE: Avantages du Repository

```php
// ✅ BONNE PRATIQUE
class CreateUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function __invoke(CreateUserCommand $command): void
    {
        $userId = $this->userRepository->nextIdentity();
        $user = User::create($userId, $command->email, $command->name);

        $this->userRepository->add($user);
    }
}
```

**Avantages :**

1. **Indépendance technologique** : Le domaine ne connaît pas Doctrine
2. **Testabilité** : Facile de créer un `InMemoryUserRepository` pour les tests
3. **Principe d'inversion de dépendance** : L'infrastructure dépend du domaine
4. **Point central de persistance** : Toute la logique est dans le repository
5. **Migration facilitée** : Changer l'implémentation sans toucher au domaine

---

## Anatomie d'un Repository

### API générique d'un Repository

```php
interface RepositoryInterface
{
    /**
     * Génère la prochaine identité disponible
     */
    public function nextIdentity(): EntityId;

    /**
     * Ajoute une entité à la collection
     */
    public function add(Entity $entity): void;

    /**
     * Récupère une entité par son identité
     * @throws EntityNotFoundException
     */
    public function get(EntityId $id): Entity;

    /**
     * Supprime une entité (optionnel selon le métier)
     */
    public function remove(EntityId $id): void;
}
```

### Exemple concret : GalinetteRepository

#### Interface (Domaine)

```php
<?php
// src/Hunting/Galinette/Domain/Repository/GalinetteRepositoryInterface.php

namespace App\Hunting\Galinette\Domain\Repository;

use App\Hunting\Galinette\Domain\Model\Galinette;
use App\Hunting\Galinette\Domain\ValueObject\GalinetteId;
use App\Hunting\Galinette\Domain\Exception\GalinetteNotFoundException;

interface GalinetteRepositoryInterface
{
    /**
     * Génère un nouvel identifiant unique pour une Galinette
     */
    public function nextIdentity(): GalinetteId;

    /**
     * Ajoute une Galinette à la collection
     *
     * @throws PersistenceException Si la persistance échoue
     */
    public function add(Galinette $galinette): void;

    /**
     * Récupère une Galinette par son identité
     *
     * @throws GalinetteNotFoundException Si la Galinette n'existe pas
     * @throws PersistenceException Si la récupération échoue
     */
    public function get(GalinetteId $id): Galinette;

    /**
     * Note : remove() n'est pas nécessaire car une Galinette
     * ne se supprime pas, elle va au paradis (goToHeaven())
     */
}
```

#### Implémentation (Infrastructure)

```php
<?php
// src/Hunting/Galinette/Infrastructure/Persistence/Doctrine/DoctrineGalinetteRepository.php

namespace App\Hunting\Galinette\Infrastructure\Persistence\Doctrine;

use App\Hunting\Galinette\Domain\Model\Galinette;
use App\Hunting\Galinette\Domain\Repository\GalinetteRepositoryInterface;
use App\Hunting\Galinette\Domain\ValueObject\GalinetteId;
use App\Hunting\Galinette\Domain\Exception\GalinetteNotFoundException;
use App\Hunting\Galinette\Domain\Exception\PersistenceException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final class DoctrineGalinetteRepository implements GalinetteRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function nextIdentity(): GalinetteId
    {
        try {
            return GalinetteId::fromString(Uuid::uuid4()->toString());
        } catch (\Exception $e) {
            throw new PersistenceException(
                'Failed to generate identity',
                0,
                $e
            );
        }
    }

    public function add(Galinette $galinette): void
    {
        try {
            $this->entityManager->persist($galinette);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw new PersistenceException(
                sprintf('Failed to persist Galinette with id %s', $galinette->getId()),
                0,
                $e
            );
        }
    }

    public function get(GalinetteId $id): Galinette
    {
        try {
            $galinette = $this->entityManager->find(
                Galinette::class,
                $id->toString()
            );

            if (null === $galinette) {
                throw new GalinetteNotFoundException(
                    sprintf('Galinette with id %s not found', $id->toString())
                );
            }

            return $galinette;
        } catch (GalinetteNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PersistenceException(
                sprintf('Failed to retrieve Galinette with id %s', $id->toString()),
                0,
                $e
            );
        }
    }
}
```

---

## Convertir un Repository Symfony classique

### MAUVAISE PRATIQUE: Avant : Repository Symfony classique (Mauvaise pratique)

```php
<?php
// src/Repository/UserRepository.php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    // ❌ Retourne des tableaux
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    // ❌ Retourne des scalaires
    public function countActiveUsers(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ❌ Retourne un QueryBuilder
    public function getActiveUsersQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->where('u.active = :active')
            ->setParameter('active', true);
    }

    // ❌ Méthode qui expose le détail de la persistance
    public function save(User $user, bool $flush = false): void
    {
        $this->getEntityManager()->persist($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
```

**Problèmes :**
- Mélange lecture et écriture
- Retourne différents types (objets, tableaux, scalaires, QueryBuilder)
- Ne respecte pas le contrat d'un vrai Repository
- Devient un "God Object" avec des dizaines de méthodes
- Couple le domaine à Doctrine

### BONNE PRATIQUE: Après : Architecture hexagonale (Bonne pratique)

#### 1. Interface du Repository (Domaine)

```php
<?php
// src/User/Domain/Repository/UserRepositoryInterface.php

namespace App\User\Domain\Repository;

use App\User\Domain\Model\User;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\Exception\UserNotFoundException;

interface UserRepositoryInterface
{
    public function nextIdentity(): UserId;

    public function add(User $user): void;

    /**
     * @throws UserNotFoundException
     */
    public function get(UserId $id): User;

    /**
     * @throws UserNotFoundException
     */
    public function getByEmail(string $email): User;

    /**
     * Finder additionnel : retourne uniquement des objets User
     * @return User[]
     */
    public function findActive(): array;
}
```

#### 2. Implémentation Doctrine (Infrastructure)

```php
<?php
// src/User/Infrastructure/Persistence/Doctrine/DoctrineUserRepository.php

namespace App\User\Infrastructure\Persistence\Doctrine;

use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Exception\PersistenceException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function nextIdentity(): UserId
    {
        try {
            return UserId::fromString(Uuid::uuid4()->toString());
        } catch (\Exception $e) {
            throw new PersistenceException('Failed to generate identity', 0, $e);
        }
    }

    public function add(User $user): void
    {
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw new PersistenceException('Failed to persist User', 0, $e);
        }
    }

    public function get(UserId $id): User
    {
        try {
            $user = $this->entityManager->find(User::class, $id->toString());

            if (null === $user) {
                throw new UserNotFoundException(
                    sprintf('User with id %s not found', $id->toString())
                );
            }

            return $user;
        } catch (UserNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PersistenceException('Failed to retrieve User', 0, $e);
        }
    }

    public function getByEmail(string $email): User
    {
        try {
            $dql = 'SELECT u FROM ' . User::class . ' u WHERE u.email = :email';
            $user = $this->entityManager
                ->createQuery($dql)
                ->setParameter('email', $email)
                ->getOneOrNullResult();

            if (null === $user) {
                throw new UserNotFoundException(
                    sprintf('User with email %s not found', $email)
                );
            }

            return $user;
        } catch (UserNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PersistenceException('Failed to retrieve User by email', 0, $e);
        }
    }

    /**
     * Finder additionnel : retourne UNIQUEMENT des objets User
     */
    public function findActive(): array
    {
        try {
            $dql = 'SELECT u FROM ' . User::class . ' u WHERE u.active = :active';
            return $this->entityManager
                ->createQuery($dql)
                ->setParameter('active', true)
                ->getResult();
        } catch (\Exception $e) {
            throw new PersistenceException('Failed to find active users', 0, $e);
        }
    }
}
```

#### 3. Query Functions pour la lecture (Infrastructure)

```php
<?php
// src/User/Infrastructure/Query/GetActiveUsersQuery.php

namespace App\User\Infrastructure\Query;

use Doctrine\DBAL\Connection;

/**
 * Query function pour la lecture/reporting
 * Ne fait PAS partie du Repository
 */
final class GetActiveUsersQuery
{
    public function __construct(
        private Connection $connection
    ) {}

    /**
     * @return ActiveUserDTO[]
     */
    public function __invoke(): array
    {
        $sql = <<<SQL
            SELECT
                u.id,
                u.email,
                u.name,
                u.created_at,
                COUNT(o.id) as order_count
            FROM user u
            LEFT JOIN `order` o ON o.user_id = u.id
            WHERE u.active = 1
            GROUP BY u.id
            ORDER BY u.created_at DESC
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql);

        return array_map(
            fn(array $row) => new ActiveUserDTO(
                $row['id'],
                $row['email'],
                $row['name'],
                new \DateTimeImmutable($row['created_at']),
                (int) $row['order_count']
            ),
            $rows
        );
    }
}
```

```php
<?php
// src/User/Infrastructure/Query/ActiveUserDTO.php

namespace App\User\Infrastructure\Query;

/**
 * DTO pour la lecture
 * Facilement normalisable pour API REST ou templates Twig
 */
final readonly class ActiveUserDTO
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public \DateTimeImmutable $createdAt,
        public int $orderCount
    ) {}
}
```

#### 4. Utilisation dans un Use Case

```php
<?php
// src/User/Application/Command/CreateUserHandler.php

namespace App\User\Application\Command;

use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;

final class CreateUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function __invoke(CreateUserCommand $command): void
    {
        // Génération de l'identité (pas de dépendance à l'auto-increment MySQL)
        $userId = $this->userRepository->nextIdentity();

        // Création de l'objet du domaine
        $user = User::create($userId, $command->email, $command->name);

        // Persistance (abstraction complète)
        $this->userRepository->add($user);
    }
}
```

```php
<?php
// src/User/Presentation/Controller/ListActiveUsersController.php

namespace App\User\Presentation\Controller;

use App\User\Infrastructure\Query\GetActiveUsersQuery;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ListActiveUsersController
{
    public function __construct(
        private GetActiveUsersQuery $getActiveUsersQuery
    ) {}

    public function __invoke(): JsonResponse
    {
        // Pour la lecture, on utilise une Query Function
        $activeUsers = ($this->getActiveUsersQuery)();

        return new JsonResponse($activeUsers);
    }
}
```

---

## Comparaison des méthodes converties

| Méthode Symfony classique | Solution Hexagonale | Raison |
|---------------------------|---------------------|--------|
| `findActiveUsers()` retourne array | `findActive(): array` dans Repository + `GetActiveUsersQuery` | Séparation lecture/écriture (CQRS) |
| `countActiveUsers()` retourne int | `CountActiveUsersQuery` uniquement | Les repositories ne retournent que des objets du domaine |
| `getActiveUsersQueryBuilder()` | `GetActiveUsersQuery` avec SQL pur | Pas de QueryBuilder exposé, exploitation complète de SQL |
| `save(User $user, bool $flush)` | `add(User $user): void` | API simple et prévisible, toujours flush immédiatement |

---

## Règles d'or du Repository

### BONNE PRATIQUE: DO (À faire)

1. **Définir l'interface dans le domaine**
2. **Implémenter dans l'infrastructure**
3. **Retourner UNIQUEMENT des objets du domaine**
4. **Cacher les exceptions tierces** (traduire en exceptions du domaine)
5. **Générer les identités** (méthode `nextIdentity()`)
6. **Flusher dans le repository** (transaction atomique par agrégat)
7. **Créer des Query Functions séparées** pour la lecture complexe

### MAUVAISE PRATIQUE: DON'T (À éviter)

1. ❌ Retourner des tableaux de scalaires
2. ❌ Retourner des QueryBuilder
3. ❌ Exposer l'EntityManager
4. ❌ Mélanger lecture et écriture complexe
5. ❌ Créer des dizaines de méthodes `findBy*`
6. ❌ Dépendre directement de Doctrine dans le domaine
7. ❌ Utiliser les annotations Doctrine sur les entités du domaine

---

## Le Pattern Collection : Typed Collections

### Pourquoi utiliser des Collections typées ?

Dans une architecture hexagonale, les **Collections typées** renforcent le **type safety** et l'encapsulation du domaine. Au lieu de retourner des `array`, on retourne des objets Collection qui garantissent le type et offrent des méthodes métier.

#### MAUVAISE PRATIQUE: Problème avec les arrays bruts

```php
interface UserRepositoryInterface
{
    /**
     * @return User[]  // Documentation seulement, aucune garantie !
     */
    public function findActive(): array;
}

// Utilisation
$users = $userRepository->findActive();
$users[] = new Product(); // ❌ Rien n'empêche ça !
// L'IDE ne peut pas autocomplete les méthodes de User
foreach ($users as $user) {
    $user->  // ❓ Aucune autocomplétion
}
```

#### BONNE PRATIQUE: Solution avec Collections typées

```php
interface UserRepositoryInterface
{
    public function findActive(): UserCollection; // ✅ Type garanti !
}

// Utilisation
$users = $userRepository->findActive();
$users[] = new Product(); // ❌ TypeError ou InvalidArgumentException !
// L'IDE sait que $user est un User
foreach ($users as $user) {
    $user->  // ✅ Autocomplétion parfaite
}
```

### Note importante: Important : Collections et Architecture Hexagonale

Dans une architecture hexagonale stricte, **le Domain ne doit JAMAIS dépendre de packages externes** (sauf PHP natif).

#### MAUVAISE PRATIQUE: À ÉVITER : Utiliser ArrayCollection directement dans le Domain

```php
<?php
// src/User/Domain/Repository/UserRepositoryInterface.php

namespace App\User\Domain\Repository;

use Doctrine\Common\Collections\ArrayCollection; // ❌ PROBLÈME !

interface UserRepositoryInterface
{
    /**
     * ❌ Le Domain dépend de doctrine/collections (infrastructure)
     * ❌ Viole le principe d'indépendance du domaine
     * ❌ Si vous supprimez Doctrine, votre domaine est cassé
     */
    public function findActive(): ArrayCollection;
}
```

**Problèmes :**
- Le **Domain** dépend d'un package d'infrastructure (`doctrine/collections`)
- Viole le principe d'inversion de dépendances (DIP)
- Rend le domaine difficile à tester indépendamment
- Empêche la migration vers une autre solution de persistance

---

### BONNE PRATIQUE: Bonne pratique 1 : Collection Domain pure (Recommandé pour l'hexagonal strict)

Créez votre propre classe Collection dans le Domain, **sans aucune dépendance externe**.

**Étape 1 : Créer AbstractCollection réutilisable dans Shared/Domain**

```php
<?php
// src/Shared/Domain/Collection/AbstractCollection.php

namespace App\Shared\Domain\Collection;

/**
 * Collection abstraite réutilisable
 * ✅ Pur PHP, aucune dépendance externe
 * ✅ Implémente Iterator, Countable, ArrayAccess
 * ✅ À étendre pour chaque type d'entité
 *
 * @template T
 */
abstract class AbstractCollection implements \Iterator, \Countable, \ArrayAccess
{
    /** @var T[] */
    protected array $items = [];
    protected int $position = 0;

    /**
     * @param T[] $items
     */
    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->validateType($item);
        }
        $this->items = array_values($items);
    }

    /**
     * Validation de type (implémenté par les classes enfants)
     */
    abstract protected function validateType(mixed $item): void;

    // ========================================
    // Iterator - Permet foreach()
    // ========================================

    public function current(): mixed
    {
        return $this->items[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }

    // ========================================
    // Countable - Permet count()
    // ========================================

    public function count(): int
    {
        return count($this->items);
    }

    // ========================================
    // ArrayAccess - Permet $collection[0]
    // ========================================

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->validateType($value);

        if (null === $offset) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
        $this->items = array_values($this->items);
    }

    // ========================================
    // Méthodes utilitaires
    // ========================================

    public function filter(callable $callback): static
    {
        return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function last(): mixed
    {
        return $this->items[array_key_last($this->items)] ?? null;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }
}
```

**Étape 2 : Étendre pour votre entité spécifique**

```php
<?php
// src/User/Domain/Collection/UserCollection.php

namespace App\User\Domain\Collection;

use App\User\Domain\Model\User;
use App\Shared\Domain\Collection\AbstractCollection;

/**
 * Collection typée d'utilisateurs
 * ✅ Pur PHP, aucune dépendance externe
 * ✅ Fait partie du Domain
 *
 * @extends AbstractCollection<User>
 */
final class UserCollection extends AbstractCollection
{
    /**
     * Validation du type : accepte uniquement des User
     */
    protected function validateType(mixed $item): void
    {
        if (!$item instanceof User) {
            throw new \InvalidArgumentException(
                sprintf('UserCollection accepts only %s, %s given',
                    User::class,
                    get_debug_type($item)
                )
            );
        }
    }

    // ========================================
    // Méthodes métier du domaine
    // ========================================

    /**
     * Filtrer les utilisateurs actifs
     */
    public function getActive(): self
    {
        return $this->filter(fn(int $key, User $user) => $user->isActive());
    }

    /**
     * Rechercher un utilisateur par email
     */
    public function findByEmail(string $email): ?User
    {
        foreach ($this->items as $user) {
            if ($user->getEmail() === $email) {
                return $user;
            }
        }
        return null;
    }

    /**
     * Obtenir tous les emails
     * @return string[]
     */
    public function getEmails(): array
    {
        return $this->map(fn(User $user) => $user->getEmail());
    }

    /**
     * Vérifier si un email existe
     */
    public function hasEmail(string $email): bool
    {
        return null !== $this->findByEmail($email);
    }
}
```

**Avantages de cette approche :**
- ✅ Domaine 100% indépendant (pur PHP)
- ✅ Aucune dépendance à Doctrine ou autre framework
- ✅ `AbstractCollection` écrit une seule fois, réutilisé partout
- ✅ Type safety garanti
- ✅ Méthodes métier dans la collection
- ✅ Facilement testable sans infrastructure
- ✅ Permet le changement de stack technique sans impact

### Utilisation dans le Repository

```php
<?php
// src/User/Domain/Repository/UserRepositoryInterface.php

namespace App\User\Domain\Repository;

use App\User\Domain\Model\User;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\Collection\UserCollection;

interface UserRepositoryInterface
{
    public function nextIdentity(): UserId;
    public function add(User $user): void;
    public function get(UserId $id): User;

    /**
     * Retourne une collection typée (pas un array !)
     */
    public function findActive(): UserCollection;
}
```

```php
<?php
// src/User/Infrastructure/Persistence/Doctrine/DoctrineUserRepository.php

namespace App\User\Infrastructure\Persistence\Doctrine;

use App\User\Domain\Collection\UserCollection;
use App\User\Domain\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function findActive(): UserCollection
    {
        // Méthode 1 : DQL avec getResult()
        $dql = 'SELECT u FROM ' . User::class . ' u WHERE u.active = :active';
        $users = $this->entityManager
            ->createQuery($dql)
            ->setParameter('active', true)
            ->getResult(); // Retourne un array

        return new UserCollection($users);

        // Méthode 2 : QueryBuilder plus élégant
        $users = $this->entityManager
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        return new UserCollection($users);
    }
}
```

### Utilisation dans les Use Cases

```php
<?php
// src/User/Application/Query/GetActiveUsersHandler.php

namespace App\User\Application\Query;

use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\Collection\UserCollection;

final class GetActiveUsersHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function __invoke(GetActiveUsersQuery $query): UserCollection
    {
        $users = $this->userRepository->findActive();

        // ✅ Type-safe : on sait que c'est une UserCollection
        // ✅ Méthodes métier disponibles
        if ($query->filterByEmail) {
            return new UserCollection(
                $users->filter(fn($k, $u) => str_contains($u->getEmail(), $query->filterByEmail))->toArray()
            );
        }

        return $users;
    }
}
```

```php
<?php
// Dans un Controller ou Presenter

$users = $this->queryBus->handle(new GetActiveUsersQuery());

// ✅ Countable
echo sprintf('Found %d active users', count($users));

// ✅ Iterator
foreach ($users as $user) {
    echo $user->getEmail(); // Autocomplétion parfaite !
}

// ✅ ArrayAccess
$firstUser = $users[0];

// ✅ Méthodes métier
$emails = $users->getEmails();
$hasAdmin = $users->hasEmail('admin@example.com');
$activeOnly = $users->getActive();
```

### Collections et Architecture Hexagonale

Les Collections typées renforcent plusieurs principes de l'architecture hexagonale :

| Principe | Explication |
|----------|-------------|
| **Type Safety** | Le domaine garantit que seuls les bons objets circulent |
| **Encapsulation** | La logique métier est dans la collection (`getActive()`, `findByEmail()`) |
| **Expressivité** | `$users->getActive()` est plus clair que `array_filter($users, ...)` |
| **Testabilité** | Facile de créer des collections de test avec des fixtures |
| **Immutabilité** | Les méthodes peuvent retourner de nouvelles instances (pattern Value Object) |
| **Domain Logic** | Les collections portent la logique métier liée aux ensembles d'objets |

### BONNE PRATIQUE: Bonne pratique 2 : Collection pragmatique avec ArrayCollection (Compromis acceptable)

Si vous acceptez une dépendance légère à `doctrine/collections` (qui est juste une lib de collections, pas l'ORM), vous pouvez hériter de `ArrayCollection`.

**Note :** Cette approche est **pragmatique mais techniquement viole l'hexagonal pur**. C'est un compromis acceptable pour beaucoup de projets.

```php
<?php
// src/User/Domain/Collection/UserCollection.php

namespace App\User\Domain\Collection;

use App\User\Domain\Model\User;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Collection typée héritant de ArrayCollection
 * Note : Dépend de doctrine/collections (acceptable pour beaucoup de projets)
 * ✅ Profite de toutes les méthodes utilitaires
 *
 * @extends ArrayCollection<int, User>
 */
final class UserCollection extends ArrayCollection
{
    /**
     * @param User[] $users
     */
    public function __construct(array $users = [])
    {
        foreach ($users as $user) {
            $this->validateType($user);
        }
        parent::__construct($users);
    }

    public function add(mixed $user): bool
    {
        $this->validateType($user);
        return parent::add($user);
    }

    private function validateType(mixed $user): void
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(
                sprintf('UserCollection can only contain %s instances, %s given',
                    User::class,
                    get_debug_type($user)
                )
            );
        }
    }

    // Méthodes métier
    public function getActive(): self
    {
        return new self(
            $this->filter(fn(int $key, User $user) => $user->isActive())->toArray()
        );
    }

    public function findByEmail(string $email): ?User
    {
        $result = $this->filter(
            fn(int $key, User $user) => $user->getEmail() === $email
        );
        return $result->first() ?: null;
    }
}
```

**Avantages :**
- ✅ Rapidité de développement
- ✅ Toutes les méthodes d'ArrayCollection disponibles (`map`, `filter`, `slice`, etc.)
- ✅ Type safety garanti par validation

**Inconvénients :**
- Note : Dépendance à `doctrine/collections` dans le Domain
- Note : Techniquement viole l'indépendance hexagonale stricte

**Quand l'utiliser :**
- Projets pragmatiques où la perfection hexagonale n'est pas critique
- Équipes qui veulent aller vite sans réinventer la roue
- Projets qui utilisent déjà Doctrine ORM

---

### Comparaison: Tableau comparatif des approches

| Critère | `array` brut | `ArrayCollection` directe | Collection héritant `ArrayCollection` | Collection Domain pure |
|---------|--------------|---------------------------|---------------------------------------|------------------------|
| **Type safety** | ❌ Aucune | ❌ Aucune | ✅ Garantie | ✅ Garantie |
| **Dépendance externe** | ✅ Aucune | ❌ `doctrine/collections` | Note : `doctrine/collections` | ✅ Aucune |
| **Hexagonal pur** | ❌ Non | ❌ Non | Note : Compromis | ✅ Oui |
| **Méthodes métier** | ❌ Aucune | ❌ Aucune | ✅ Oui | ✅ Oui |
| **Autocomplétion IDE** | ❌ Faible | Note : Générique | ✅ Parfaite | ✅ Parfaite |
| **Méthodes utilitaires** | Note : Fonctions PHP | ✅ Toutes | ✅ Toutes héritées | ✅ Vous implémentez |
| **Effort développement** | ✅ Minimal | ✅ Minimal | ✅ Faible | Note : Moyen (une fois) |
| **Testabilité** | Note : Moyenne | Note : Moyenne | ✅ Bonne | ✅ Excellente |
| **Migration stack** | Note : Difficile | ❌ Très difficile | Note : Difficile | ✅ Facile |

---

### Recommandation: Recommandation par contexte

#### Pour l'architecture hexagonale STRICTE (recommandé)
```php
// ✅ FAIRE : Collection Domain pure
final class UserCollection extends AbstractCollection
{
    // Pur PHP, aucune dépendance
}
```

**Utilisez cette approche si :**
- Vous voulez respecter l'hexagonal à 100%
- Vous voulez un domaine complètement indépendant
- Vous prévoyez de changer de stack technique
- Vous voulez tester le domaine sans aucune infrastructure

#### Pour les projets pragmatiques
```php
// Note : ACCEPTABLE : Hérite d'ArrayCollection
final class UserCollection extends ArrayCollection
{
    // Validation + méthodes métier
}
```

**Utilisez cette approche si :**
- Vous utilisez déjà Doctrine ORM
- Vous voulez aller vite
- La perfection hexagonale n'est pas critique
- Vous acceptez le compromis dépendance légère vs rapidité

#### À ÉVITER absolument
```php
// ❌ NE PAS FAIRE : Utiliser ArrayCollection directement dans les interfaces
interface UserRepositoryInterface
{
    public function findActive(): ArrayCollection; // ❌ Non !
}

// ❌ NE PAS FAIRE : Retourner des arrays bruts
interface UserRepositoryInterface
{
    /**
     * @return User[]
     */
    public function findActive(): array; // ❌ Aucune garantie de type
}
```

---

### Structure: Structure des dossiers recommandée

```
src/
├── Shared/
│   └── Domain/
│       └── Collection/
│           └── AbstractCollection.php    ✅ Collection abstraite réutilisable
│
└── User/
    ├── Domain/
    │   ├── Model/
    │   │   └── User.php                  ✅ Entité
    │   ├── Collection/
    │   │   └── UserCollection.php        ✅ Collection Domain (extend AbstractCollection)
    │   └── Repository/
    │       └── UserRepositoryInterface.php ✅ Port (Interface)
    │
    ├── Application/
    │   └── Query/
    │       └── GetActiveUsersHandler.php
    │
    └── Infrastructure/
        └── Persistence/
            └── Doctrine/
                └── DoctrineUserRepository.php ✅ Adapter (Implémentation)
```

---

## Patterns avancés

### Pattern Memento

Pour optimiser les performances, vous pouvez implémenter le pattern Memento pour ne persister que les changements.

Voir l'article de Matthias Noback : [https://matthiasnoback.nl/](https://matthiasnoback.nl/)

### Flush via Middleware

Au lieu de flusher dans le repository, vous pouvez déléguer à un middleware du MessageBus :

```php
final class DoctrineTransactionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->entityManager->beginTransaction();

        try {
            $result = $stack->next()->handle($envelope, $stack);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $result;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
```

---

## Testabilité

### Repository In-Memory pour les tests

```php
<?php
// tests/User/Infrastructure/InMemory/InMemoryUserRepository.php

namespace App\Tests\User\Infrastructure\InMemory;

use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\Exception\UserNotFoundException;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    private array $users = [];

    public function nextIdentity(): UserId
    {
        return UserId::fromString((string) count($this->users) + 1);
    }

    public function add(User $user): void
    {
        $this->users[$user->getId()->toString()] = $user;
    }

    public function get(UserId $id): User
    {
        $user = $this->users[$id->toString()] ?? null;

        if (null === $user) {
            throw new UserNotFoundException(
                sprintf('User with id %s not found', $id->toString())
            );
        }

        return $user;
    }

    public function getByEmail(string $email): User
    {
        foreach ($this->users as $user) {
            if ($user->getEmail() === $email) {
                return $user;
            }
        }

        throw new UserNotFoundException(
            sprintf('User with email %s not found', $email)
        );
    }

    public function findActive(): array
    {
        return array_filter(
            $this->users,
            fn(User $user) => $user->isActive()
        );
    }
}
```

### Test unitaire

```php
<?php

namespace App\Tests\User\Application\Command;

use App\User\Application\Command\CreateUserCommand;
use App\User\Application\Command\CreateUserHandler;
use App\Tests\User\Infrastructure\InMemory\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

final class CreateUserHandlerTest extends TestCase
{
    public function testItCreatesAUser(): void
    {
        // Given
        $repository = new InMemoryUserRepository();
        $handler = new CreateUserHandler($repository);
        $command = new CreateUserCommand('john@example.com', 'John Doe');

        // When
        $handler($command);

        // Then
        $user = $repository->getByEmail('john@example.com');
        $this->assertEquals('John Doe', $user->getName());
    }
}
```

---

## Conclusion

Le pattern Repository correctement implémenté permet de :

1. **Isoler le domaine** de l'infrastructure de persistance
2. **Respecter les principes SOLID** (notamment Dependency Inversion)
3. **Faciliter les tests** avec des implémentations in-memory
4. **Centraliser la persistance** en un point unique
5. **Migrer facilement** vers une autre solution de persistance
6. **Séparer lecture et écriture** (CQRS léger avec Query Functions)

---

## Ressources

- [Article de Matthias Noback sur le pattern Repository](https://matthiasnoback.nl/)
- [Conférence d'Arnaud Lemaire sur le pattern Repository](https://www.youtube.com/results?search_query=arnaud+lemaire+repository)
- [Domain-Driven Design par Eric Evans](https://www.domainlanguage.com/)
- [Implementing Domain-Driven Design par Vaughn Vernon](https://vaughnvernon.com/)

---

## Exemple complet

Un exemple complet d'implémentation est disponible dans le bundle :
- [Module User avec Repository](../../examples/user-registration.md)
- [Tests avec InMemoryRepository](../../examples/testing.md)
