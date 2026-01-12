---
layout: default_with_lang
title: Configuration Injection de Dépendances
parent: Sujets Avancés
nav_order: 15
lang: fr
lang_ref: advanced/dependency-injection-guide.md
---

# Guide de Configuration de l'Injection de Dépendances

## Table des Matières

1. [Vue d'Ensemble](#vue-densemble)
2. [Bases Autowiring Symfony](#bases-autowiring-symfony)
3. [Liaison Ports vers Adaptateurs](#liaison-ports-vers-adaptateurs)
4. [Liaisons Spécifiques Environnement](#liaisons-spécifiques-environnement)
5. [Services Taggués](#services-taggués)
6. [Décoration de Services](#décoration-de-services)
7. [Exemples Configuration Complète](#exemples-configuration-complète)
8. [Dépannage](#dépannage)

---

## Vue d'Ensemble

En architecture hexagonale, **l'Injection de Dépendances (DI)** est critique pour lier ports (interfaces) à adaptateurs (implémentations). Le conteneur DI de Symfony gère ce câblage automatiquement avec configuration appropriée.

### Le Problème que DI Résout

```php
// ❌ Sans DI : Dépendance codée en dur
class RegisterUserHandler
{
    public function __invoke(RegisterUserCommand $command): void
    {
        $repository = new DoctrineUserRepository(); // ❌ Couplage fort!
        // ...
    }
}

// ✅ Avec DI : Dépendance injectée
class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users // ✅ Interface, pas implémentation
    ) {}

    public function __invoke(RegisterUserCommand $command): void
    {
        $this->users->save(...); // Utilise implémentation configurée
    }
}
```

**Configuration Conteneur DI :** Dit à Symfony "quand quelqu'un a besoin de `UserRepositoryInterface`, donne-lui `DoctrineUserRepository`".

---

## Bases Autowiring Symfony

### Qu'est-ce que l'Autowiring ?

**Autowiring :** Symfony résout automatiquement dépendances constructeur en regardant type hints.

```php
// Handler avec dépendances type-hintées
class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users,      // Autowired
        private EmailSenderInterface $emailSender,   // Autowired
        private EventDispatcherInterface $events,    // Autowired
    ) {}
}
```

Symfony voit ces type hints et fournit automatiquement services corrects.

---

### Activer Autowiring

Dans `config/services.yaml` :

```yaml
services:
    _defaults:
        autowire: true      # Activer autowiring
        autoconfigure: true # Configurer automatiquement services (tags, etc.)
        public: false       # Services privés par défaut

    # Auto-enregistrer toutes classes dans src/ comme services
    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
            - '../src/*/Domain/Model/'      # Exclure entités
            - '../src/*/Domain/ValueObject/' # Exclure value objects
            - '../src/Kernel.php'
```

**Comment ça marche :**
1. Symfony scanne répertoire `src/`
2. Enregistre chaque classe comme service
3. Autowire dépendances constructeur

---

## Liaison Ports vers Adaptateurs

### Configuration de Base

**Problème :** Interface ne peut être instanciée—Symfony doit savoir quelle implémentation utiliser.

```php
// Handler nécessite UserRepositoryInterface
class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users // ❌ Interface, ne peut être instanciée!
    ) {}
}
```

**Solution :** Lier interface à implémentation dans `services.yaml`.

---

### Méthode 1 : Liaison Directe (Plus Simple)

```yaml
services:
    # Lier interface → implémentation
    App\User\Domain\Port\UserRepositoryInterface:
        class: App\User\Infrastructure\Persistence\DoctrineUserRepository
```

**Explication :**
- Quand quelqu'un a besoin `UserRepositoryInterface`
- Symfony fournit `DoctrineUserRepository`

---

### Méthode 2 : Alias (Recommandé)

```yaml
services:
    # Implémentation
    App\User\Infrastructure\Persistence\DoctrineUserRepository:
        # Autowired par défaut

    # Alias : interface → implémentation
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\DoctrineUserRepository
```

**Bénéfices :**
- Plus explicite
- Plus facile changer implémentations

---

### Méthode 3 : Bind pour Tous Services

```yaml
services:
    _defaults:
        bind:
            # Lier automatiquement cette interface à cette implémentation
            # pour TOUS les services
            App\User\Domain\Port\UserRepositoryInterface: '@App\User\Infrastructure\Persistence\DoctrineUserRepository'
```

**Quand utiliser :** Interface utilisée dans beaucoup d'endroits, éviter répéter configuration.

---

## Liaisons Spécifiques Environnement

### Le Problème : Implémentations Différentes pour Environnements Différents

- **Développement :** Utiliser repository en mémoire (tests rapides)
- **Test :** Utiliser repository en mémoire
- **Production :** Utiliser repository Doctrine (vraie base données)

---

### Solution : Configuration Spécifique Environnement

#### 1. Configuration Principale (`config/services.yaml`)

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Enregistrer toutes implémentations
    App\User\Infrastructure\Persistence\DoctrineUserRepository:
    App\User\Infrastructure\Persistence\InMemoryUserRepository:

    # Liaison par défaut (production)
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\DoctrineUserRepository
```

---

#### 2. Configuration Test (`config/services_test.yaml`)

```yaml
services:
    # Surcharger liaison pour environnement test
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\InMemoryUserRepository
```

**Résultat :**
- `bin/phpunit` → utilise `InMemoryUserRepository`
- Production → utilise `DoctrineUserRepository`

---

#### 3. Configuration Développement (`config/services_dev.yaml`)

```yaml
# Optionnel : utiliser en mémoire pour feedback dev rapide
services:
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\InMemoryUserRepository

    # Ou activer logging debug
    App\User\Infrastructure\Persistence\DoctrineUserRepository:
        decorates: App\User\Infrastructure\Persistence\DoctrineUserRepository
        arguments:
            $decorated: '@.inner'
            $logger: '@logger'
```

---

### Exemple Complet : Email Sender

```yaml
# config/services.yaml (défaut : production)
services:
    # Production : vrai SMTP
    App\Notification\Domain\Port\EmailSenderInterface:
        alias: App\Notification\Infrastructure\Email\SymfonyEmailSender

# config/services_test.yaml
services:
    # Test : fake en mémoire
    App\Notification\Domain\Port\EmailSenderInterface:
        alias: App\Notification\Infrastructure\Email\InMemoryEmailSender

# config/services_dev.yaml
services:
    # Dev : logger emails au lieu d'envoyer
    App\Notification\Domain\Port\EmailSenderInterface:
        alias: App\Notification\Infrastructure\Email\LoggingEmailSender
```

---

## Services Taggués

### Le Problème : Multiples Implémentations Même Interface

**Exemple :** Multiples souscripteurs événement pour même événement.

```php
interface EventSubscriberInterface
{
    public function handle(DomainEvent $event): void;
}

class SendEmailSubscriber implements EventSubscriberInterface { /* ... */ }
class LogEventSubscriber implements EventSubscriberInterface { /* ... */ }
class UpdateCacheSubscriber implements EventSubscriberInterface { /* ... */ }
```

**Besoin :** Injecter toutes implémentations, pas juste une.

---

### Solution : Services Taggués

#### 1. Taguer Toutes Implémentations

```yaml
services:
    # Taguer chaque implémentation
    App\Notification\Infrastructure\Event\SendEmailSubscriber:
        tags: ['app.event_subscriber']

    App\Notification\Infrastructure\Event\LogEventSubscriber:
        tags: ['app.event_subscriber']

    App\Notification\Infrastructure\Event\UpdateCacheSubscriber:
        tags: ['app.event_subscriber']
```

---

#### 2. Injecter Tous Services Taggués

```yaml
services:
    # Event dispatcher reçoit tous subscribers
    App\Shared\Infrastructure\Event\EventDispatcher:
        arguments:
            $subscribers: !tagged_iterator app.event_subscriber
```

---

#### 3. Utiliser dans Service

```php
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        #[TaggedIterator('app.event_subscriber')]
        private iterable $subscribers // Tous services taggués injectés ici
    ) {}

    public function dispatch(DomainEvent $event): void
    {
        foreach ($this->subscribers as $subscriber) {
            $subscriber->handle($event);
        }
    }
}
```

---

### Auto-Tagging avec Interfaces

**Tagging automatique :** Taguer toutes classes implémentant une interface.

```yaml
services:
    _instanceof:
        # Taguer automatiquement toutes classes implémentant EventSubscriberInterface
        App\Shared\Domain\Event\EventSubscriberInterface:
            tags: ['app.event_subscriber']
```

**Maintenant vous n'avez pas besoin de taguer manuellement chaque implémentation !**

---

### Exemple Complet : Query Bus avec Multiples Handlers

```yaml
services:
    _instanceof:
        # Auto-taguer tous query handlers
        App\Shared\Application\Query\QueryHandlerInterface:
            tags: ['app.query_handler']

    # Query bus reçoit tous handlers
    App\Shared\Infrastructure\Query\QueryBus:
        arguments:
            $handlers: !tagged_iterator app.query_handler
```

```php
class QueryBus
{
    public function __construct(
        #[TaggedIterator('app.query_handler')]
        private iterable $handlers
    ) {}

    public function dispatch(Query $query): mixed
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($query)) {
                return $handler->handle($query);
            }
        }

        throw new NoHandlerFoundException();
    }
}
```

---

## Décoration de Services

### Le Problème : Ajouter Fonctionnalité Sans Modifier Code

**Exemple :** Ajouter cache au repository sans changer code repository.

---

### Solution : Pattern Decorator avec DI

#### 1. Créer Décorateur

```php
namespace App\User\Infrastructure\Persistence;

final readonly class CachedUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private UserRepositoryInterface $decorated, // Repository original
        private CacheInterface $cache,
    ) {}

    public function findById(UserId $id): ?User
    {
        return $this->cache->get(
            "user:{$id}",
            fn() => $this->decorated->findById($id) // Déléguer à l'original
        );
    }

    public function save(User $user): void
    {
        $this->decorated->save($user);
        $this->cache->delete("user:{$user->getId()}"); // Invalider cache
    }
}
```

---

#### 2. Configurer Décoration

```yaml
services:
    # Repository original
    App\User\Infrastructure\Persistence\DoctrineUserRepository:

    # Décorateur enveloppe l'original
    App\User\Infrastructure\Persistence\CachedUserRepository:
        decorates: App\User\Infrastructure\Persistence\DoctrineUserRepository
        arguments:
            $decorated: '@.inner' # @.inner = le service décoré
```

**Résultat :**
- Quiconque demande `DoctrineUserRepository` obtient `CachedUserRepository`
- `CachedUserRepository` enveloppe `DoctrineUserRepository`
- Cache ajouté de façon transparente

---

### Priorité Décoration

**Multiples décorateurs :**

```yaml
services:
    App\User\Infrastructure\Persistence\DoctrineUserRepository:

    # Premier décorateur : cache
    App\User\Infrastructure\Persistence\CachedUserRepository:
        decorates: App\User\Infrastructure\Persistence\DoctrineUserRepository
        decoration_priority: 10 # Priorité plus élevée = couche externe
        arguments:
            $decorated: '@.inner'

    # Second décorateur : logging
    App\User\Infrastructure\Persistence\LoggingUserRepository:
        decorates: App\User\Infrastructure\Persistence\DoctrineUserRepository
        decoration_priority: 5 # Priorité plus basse = couche interne
        arguments:
            $decorated: '@.inner'
```

**Chaîne d'appel :**
```
Handler → LoggingUserRepository → CachedUserRepository → DoctrineUserRepository → Base de Données
```

---

## Exemples Configuration Complète

### Exemple 1 : Module User

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Auto-enregistrer tous services
    App\:
        resource: '../src/'
        exclude:
            - '../src/*/Domain/Model/'
            - '../src/*/Domain/ValueObject/'
            - '../src/Kernel.php'

    # Lier ports à adaptateurs
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\DoctrineUserRepository

    App\User\Domain\Port\PasswordHasherInterface:
        alias: App\User\Infrastructure\Security\SymfonyPasswordHasher

    App\Shared\Domain\Port\EmailSenderInterface:
        alias: App\Shared\Infrastructure\Email\SymfonyEmailSender

    App\Shared\Domain\Port\EventDispatcherInterface:
        alias: App\Shared\Infrastructure\Event\SymfonyEventDispatcher
```

---

### Exemple 2 : Module Order avec CQRS

```yaml
services:
    # Côté écriture
    App\Order\Domain\Port\OrderRepositoryInterface:
        alias: App\Order\Infrastructure\Persistence\DoctrineOrderRepository

    # Côté lecture (CQRS)
    App\Order\Application\Query\OrderQueryInterface:
        alias: App\Order\Infrastructure\Query\SqlOrderQuery

    # Services externes
    App\Order\Domain\Port\PaymentProcessorInterface:
        alias: App\Order\Infrastructure\Payment\StripePaymentProcessor

    App\Order\Domain\Port\InventoryServiceInterface:
        alias: App\Order\Infrastructure\Inventory\HttpInventoryService
```

---

### Exemple 3 : Application Multi-Tenant

```yaml
services:
    # Résolveur tenant
    App\Shared\Infrastructure\Tenancy\TenantResolver:

    # Repository tenant-aware
    App\User\Infrastructure\Persistence\TenantAwareUserRepository:
        arguments:
            $tenantResolver: '@App\Shared\Infrastructure\Tenancy\TenantResolver'

    # Lier interface à implémentation tenant-aware
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\TenantAwareUserRepository
```

---

### Exemple 4 : Repository Décoré avec Cache & Logging

```yaml
services:
    # Repository de base
    App\Product\Infrastructure\Persistence\DoctrineProductRepository:

    # Décorateur : cache
    App\Product\Infrastructure\Persistence\CachedProductRepository:
        decorates: App\Product\Infrastructure\Persistence\DoctrineProductRepository
        decoration_priority: 10
        arguments:
            $decorated: '@.inner'
            $cache: '@cache.app'

    # Décorateur : logging
    App\Product\Infrastructure\Persistence\LoggingProductRepository:
        decorates: App\Product\Infrastructure\Persistence\DoctrineProductRepository
        decoration_priority: 5
        arguments:
            $decorated: '@.inner'
            $logger: '@logger'

    # Lier interface à la base (décorateurs l'enveloppent automatiquement)
    App\Product\Domain\Port\ProductRepositoryInterface:
        alias: App\Product\Infrastructure\Persistence\DoctrineProductRepository
```

**Chaîne d'appel :** `Handler → Logging → Caching → Doctrine → BD`

---

## Dépannage

### Problème 1 : "Cannot autowire service: argument is type-hinted with interface"

**Erreur :**
```
Cannot autowire service "App\User\Application\Handler\RegisterUserHandler":
argument "$users" of method "__construct()" is type-hinted with the interface
"App\User\Domain\Port\UserRepositoryInterface" but no implementation is registered.
```

**Solution :** Lier interface à implémentation.

```yaml
services:
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\DoctrineUserRepository
```

---

### Problème 2 : "Service not found"

**Erreur :**
```
Service "App\User\Infrastructure\Persistence\DoctrineUserRepository" not found.
```

**Solution :** Vérifier que répertoire n'est pas exclu dans `services.yaml`.

```yaml
services:
    App\:
        resource: '../src/'
        exclude:
            - '../src/*/Domain/Model/'  # ✅ Exclure entités
            # ❌ Ne pas exclure Infrastructure!
```

---

### Problème 3 : "Circular reference detected"

**Erreur :**
```
Circular reference detected for service "App\User\Infrastructure\Persistence\DoctrineUserRepository".
```

**Solution :** Refactorer pour supprimer dépendance circulaire ou utiliser injection setter.

```yaml
services:
    App\User\Infrastructure\Persistence\DoctrineUserRepository:
        calls:
            - setLogger: ['@logger'] # Injection setter au lieu constructeur
```

---

### Problème 4 : "Mauvaise implémentation injectée dans tests"

**Problème :** Test utilise implémentation production au lieu fake.

**Solution :** Créer `config/services_test.yaml` et surcharger liaison.

```yaml
# config/services_test.yaml
services:
    App\User\Domain\Port\UserRepositoryInterface:
        alias: App\User\Infrastructure\Persistence\InMemoryUserRepository
```

---

### Problème 5 : Services taggués non injectés

**Erreur :** `$subscribers` est vide même si services sont taggués.

**Solution :** Vérifier nom tag correspond.

```yaml
services:
    # Définition tag
    _instanceof:
        App\Shared\Domain\Event\EventSubscriberInterface:
            tags: ['app.event_subscriber'] # Nom tag

    # Injection (doit correspondre!)
    App\Shared\Infrastructure\Event\EventDispatcher:
        arguments:
            $subscribers: !tagged_iterator app.event_subscriber # Même nom tag
```

---

## Bonnes Pratiques

### 1. Utiliser Alias pour Liaisons Port

✅ **BON :**
```yaml
App\User\Domain\Port\UserRepositoryInterface:
    alias: App\User\Infrastructure\Persistence\DoctrineUserRepository
```

❌ **ÉVITER :**
```yaml
App\User\Domain\Port\UserRepositoryInterface:
    class: App\User\Infrastructure\Persistence\DoctrineUserRepository
```

**Raison :** Alias plus clairs et plus faciles à surcharger.

---

### 2. Fichiers Configuration Spécifiques Environnement

```
config/
├── services.yaml          # Défaut (production)
├── services_dev.yaml      # Surcharges développement
├── services_test.yaml     # Surcharges test
└── services_prod.yaml     # Spécifique production (optionnel)
```

---

### 3. Utiliser `_instanceof` pour Auto-Tagging

✅ **BON :**
```yaml
_instanceof:
    App\Shared\Application\Query\QueryHandlerInterface:
        tags: ['app.query_handler']
```

❌ **ÉVITER :**
```yaml
App\User\Application\Query\FindUserQueryHandler:
    tags: ['app.query_handler']

App\Order\Application\Query\FindOrderQueryHandler:
    tags: ['app.query_handler']

# ... taguer manuellement chacun
```

---

### 4. Exclure Classes Non-Service

```yaml
App\:
    resource: '../src/'
    exclude:
        - '../src/*/Domain/Model/'       # Entités
        - '../src/*/Domain/ValueObject/' # Value objects
        - '../src/*/Application/Command/' # DTOs
        - '../src/*/Application/Query/'   # DTOs
        - '../src/Kernel.php'
```

**Raison :** Seulement services devraient être enregistrés, pas objets données.

---

## Résumé Antisèche

| Tâche | Configuration |
|-------|---------------|
| Lier port à adaptateur | `alias: App\...\Implementation` |
| Injecter tous services taggués | `!tagged_iterator tag_name` |
| Auto-taguer par interface | `_instanceof: { Interface: tags: [...] }` |
| Surcharger pour test | Créer `services_test.yaml` |
| Décorer service | `decorates: OriginalService` + `$decorated: '@.inner'` |
| Exclure répertoire | `exclude: ['../src/Path/']` |

---

**Suivant :** [Implémentation Pattern Factory →](./pattern-factory-guide.md)
