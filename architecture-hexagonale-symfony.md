# Architecture Hexagonale avec Symfony : Guide Pratique

## Introduction

Ce document présente les principes de l'architecture hexagonale et de la clean architecture appliqués à Symfony, en illustrant comment migrer d'une architecture en couches techniques vers une architecture centrée sur le métier.

## Cas d'étude : Application de gestion de cadeaux municipaux

### Contexte
Une mairie souhaite offrir des cadeaux personnalisés aux habitants qui célèbrent leur première année dans la commune. L'application doit :

- Sélectionner les habitants éligibles (arrivés depuis plus d'un an)
- Attribuer un cadeau approprié selon l'âge
- Notifier les habitants par email
- Envoyer un récapitulatif quotidien au maire

### Sources de données
- **Base de données** : informations sur les habitants (Doctrine ORM)
- **Fichiers** : catalogue de cadeaux par tranche d'âge
- **Serveur mail** : notifications aux habitants et à la mairie (Symfony Mailer)

### Points d'entrée
- Interface graphique Twig pour les employés
- API REST pour les développeurs et tests
- Commande Symfony (exécution périodique via Cron)

## Problèmes de l'architecture Symfony classique

### Le problème de la volatilité technique

**💡 Concept clé : La volatilité technique**

La **volatilité technique** désigne la fréquence et l'amplitude des changements dans les technologies, frameworks et librairies que nous utilisons.

**Exemples concrets de volatilité technique :**

| Composant | Volatilité | Exemples de changements |
|-----------|-----------|-------------------------|
| **Framework (Symfony)** | 🔴 Haute | - Symfony 4 → 5 → 6 → 7 (breaking changes)<br>- Suppression de fonctionnalités (FormEvents)<br>- Changement d'API (Security, Mailer) |
| **ORM (Doctrine)** | 🟠 Moyenne | - Doctrine 2.x → 3.x (annotations → attributs)<br>- Changement des types (datetime → datetime_immutable)<br>- API QueryBuilder |
| **Librairies tierces** | 🔴 Haute | - Abandon de maintenance<br>- Vulnerabilités de sécurité<br>- Incompatibilités PHP 8+ |
| **PHP lui-même** | 🟡 Faible-Moyenne | - PHP 7.4 → 8.0 → 8.1 → 8.2 → 8.3<br>- Nouvelles fonctionnalités (readonly, enums)<br>- Déprécations |
| **Règles métier** | 🟢 Très faible | - Stable dans le temps<br>- Changements contrôlés<br>- Validation business |

**🎯 Le principe fondamental :**
> Les éléments volatils ne doivent JAMAIS contaminer les éléments stables

**❌ Ce qui se passe dans une architecture classique :**

```php
// Domain/Entity/User.php - CONTAMINÉ par la volatilité technique !

use Doctrine\ORM\Mapping as ORM;              // 🔴 Volatilité Doctrine
use Symfony\Component\Validator\Constraints as Assert;  // 🔴 Volatilité Symfony
use JMS\Serializer\Annotation as Serializer;  // 🔴 Volatilité JMS (abandon de maintenance)

#[ORM\Entity]                                  // 🔴 Couplage Doctrine
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    #[Assert\NotBlank]                          // 🔴 Couplage Validator
    #[Assert\Email]
    #[Serializer\Groups(['user:read'])]        // 🔴 Couplage Serializer
    private string $email;

    #[ORM\Column(type: 'datetime')]            // 🔴 Type Doctrine déprécié
    private \DateTime $createdAt;               // 🔴 DateTime mutable (mauvaise pratique)

    // Logique métier noyée dans les annotations techniques...
    public function canAccessPremiumFeature(): bool
    {
        return $this->isPremium && !$this->isExpired();
    }
}
```

**🎯 Conséquences de cette contamination :**

1. **Migration Doctrine 2 → 3** (annotations → attributs) :
   - ❌ Modifier TOUTES les entités métier
   - ❌ Risque de casser la logique métier
   - ❌ Tests à refaire
   - ⏱️ Temps estimé : 2-4 semaines pour 50 entités

2. **Changement Symfony 5 → 7** :
   - ❌ API Validator changée
   - ❌ Security component réécrit
   - ❌ Mailer remplace SwiftMailer
   - ⏱️ Temps estimé : 1-3 mois

3. **Abandon de JMS Serializer** :
   - ❌ Supprimer toutes les annotations
   - ❌ Refaire la configuration
   - ❌ Adapter les groupes
   - ⏱️ Temps estimé : 2-6 semaines

**✅ Avec l'architecture hexagonale :**

```php
// Domain/Entity/User.php - PURE, ZÉRO volatilité technique !

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserId;

/**
 * 💎 ENTITÉ PURE - Survit à tous les changements techniques
 * ✅ Aucune annotation
 * ✅ Aucun import de framework
 * ✅ Code métier lisible
 */
final class User
{
    private UserId $id;
    private Email $email;
    private \DateTimeImmutable $createdAt;
    private bool $isPremium;
    private ?\DateTimeImmutable $premiumExpiresAt;

    public function __construct(
        UserId $id,
        Email $email,
        bool $isPremium = false
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->createdAt = new \DateTimeImmutable();
        $this->isPremium = $isPremium;
    }

    /**
     * RÈGLE MÉTIER : Un utilisateur premium peut accéder aux fonctionnalités
     * premium tant que son abonnement n'a pas expiré
     */
    public function canAccessPremiumFeature(): bool
    {
        if (!$this->isPremium) {
            return false;
        }

        if ($this->premiumExpiresAt === null) {
            return true; // Premium à vie
        }

        return $this->premiumExpiresAt > new \DateTimeImmutable();
    }

    public function upgradeToPremium(\DateTimeImmutable $expiresAt): void
    {
        $this->isPremium = true;
        $this->premiumExpiresAt = $expiresAt;
    }

    // Getters only - aucune logique technique
}
```

**🎯 Bénéfices face à la volatilité :**

| Changement technique | Architecture classique | Architecture hexagonale |
|---------------------|------------------------|-------------------------|
| **Doctrine 2 → 3** | 🔴 Modifier 100% des entités<br>⏱️ 2-4 semaines | 🟢 Modifier uniquement les YAML<br>⏱️ 2-3 jours |
| **Symfony 5 → 7** | 🔴 Refonte complète<br>⏱️ 1-3 mois | 🟢 Adapter uniquement l'infrastructure<br>⏱️ 1-2 semaines |
| **PHP 7.4 → 8.3** | 🟠 Risques sur les entités<br>⏱️ 2-4 semaines | 🟢 Profiter des nouveautés (readonly)<br>⏱️ 3-5 jours |
| **Abandon d'une lib** | 🔴 Contamination profonde<br>⏱️ 1-2 mois | 🟢 Changement local (adapter)<br>⏱️ 2-5 jours |
| **Nouvelle fonctionnalité** | 🔴 Impacte tout<br>⏱️ Variable | 🟢 Isolée dans l'infra<br>⏱️ Rapide |

**💰 Impact financier de la volatilité :**

Prenons un projet sur 5 ans avec 50 entités métier :

| Scénario | Architecture classique | Architecture hexagonale | Économie |
|----------|------------------------|-------------------------|----------|
| **Migration Symfony** | 3 mois × 60k€/dev = 180k€ | 2 semaines × 60k€/dev = 15k€ | **-92%** |
| **Migration Doctrine** | 1 mois × 60k€ = 60k€ | 3 jours × 60k€ = 2.5k€ | **-96%** |
| **Upgrade PHP** | 1 mois = 60k€ | 1 semaine = 7.5k€ | **-88%** |
| **Total sur 5 ans** | **~300k€** | **~25k€** | **-92%** (275k€ économisés) |

**🎯 La règle d'or :**
> Investissez 20% de temps en plus au départ pour économiser 90% sur 5 ans

**📊 Graphique de l'impact de la volatilité :**

```
Coût de maintenance cumulé (5 ans)

Architecture Classique :
Année 1: ████░░░░░░ (10k€ - développement initial)
Année 2: ████████░░ (30k€ - première migration)
Année 3: ██████████ (80k€ - dette technique)
Année 4: ██████████████ (180k€ - refonte majeure)
Année 5: ████████████████████ (300k€ - insoutenable)

Architecture Hexagonale :
Année 1: ██████░░░░ (12k€ - setup initial +20%)
Année 2: ███████░░░ (15k€ - stable)
Année 3: ████████░░ (18k€ - petites adaptations)
Année 4: █████████░ (22k€ - croissance maîtrisée)
Année 5: ██████████ (25k€ - ÉCONOMIE DE 275k€ !)
```

**🚀 Comment le bundle protège du volatilité :**

```bash
# Le bundle génère AUTOMATIQUEMENT la séparation :

bin/console make:hexagonal:entity user/account User
# ✅ Génère : Domain/Model/User.php (PURE)
# ✅ Génère : Infrastructure/.../User.orm.yml (VOLATILITÉ ISOLÉE)

# Migration Doctrine 2 → 3 ?
# ✅ Domaine inchangé
# ✅ Modifier uniquement les fichiers YAML

# Changement de Doctrine vers autre ORM ?
# ✅ Créer un nouvel adaptateur
# ✅ Domaine inchangé
```

### Structure classique (Bundle-centric)
```
src/
├── Controller/
│   ├── HabitantController.php
│   └── CadeauController.php
├── Entity/
│   ├── Habitant.php
│   └── Cadeau.php
├── Repository/
│   └── HabitantRepository.php
└── Service/
    ├── HabitantService.php
    └── CadeauService.php
```

### Limitations identifiées

1. **Couplage fort à Doctrine**
   - Annotations Doctrine dans les entités métier
   - Active Record pattern via les repositories
   - Modèle métier = modèle de persistance

2. **Modèle unique pour toutes les couches**
   ```php
   use Doctrine\ORM\Mapping as ORM;
   use Symfony\Component\Validator\Constraints as Assert;
   use Symfony\Component\Serializer\Annotation\SerializedName;

   #[ORM\Entity]
   class Habitant
   {
       #[ORM\Id]
       #[ORM\GeneratedValue]
       #[ORM\Column]
       private ?int $id = null;

       #[ORM\Column(length: 255)]
       #[Assert\NotBlank]
       #[SerializedName('last_name')]
       private string $nom;
   }
   ```

3. **Logique métier polluée par la technique**
   ```php
   class CadeauService
   {
       public function attribuerCadeaux(
           string $nomFichier,
           \DateTime $date,
           string $smtpHost,
           int $smtpPort,
           string $smtpUser,
           string $smtpPassword
       ): void {
           // Configuration technique mélangée au métier
       }
   }
   ```

4. **Tests difficiles**
   - Nécessité de charger tout le container Symfony
   - KernelTestCase pour tester la logique métier
   - Tests lents et couplés à l'infrastructure
   - Dépendance à une base de données de test

## Principes de la Clean Architecture

### Philosophie

> "Votre application n'est pas définie par Symfony ou Doctrine, mais par vos cas d'utilisation métier"

### Organisation en cercles concentriques

```
┌─────────────────────────────────────┐
│   Infrastructure (Symfony, Doctrine)│
│  ┌───────────────────────────────┐ │
│  │   Adaptateurs (Controllers)   │ │
│  │  ┌─────────────────────────┐ │ │
│  │  │   Cas d'utilisation     │ │ │
│  │  │  ┌───────────────────┐ │ │ │
│  │  │  │    Entités       │ │ │ │
│  │  │  │    (Domaine)     │ │ │ │
│  │  │  └───────────────────┘ │ │ │
│  │  └─────────────────────────┘ │ │
│  └───────────────────────────────┘ │
└─────────────────────────────────────┘
```

### Règle fondamentale
**Les dépendances vont toujours de l'extérieur vers l'intérieur**
- Le domaine ne connaît JAMAIS l'infrastructure
- Pas d'imports de classes Symfony ou Doctrine dans le domaine

## Architecture Hexagonale avec Symfony

### Structure recommandée (avec CQRS)

```
src/
├── Domain/
│   ├── Entity/
│   │   ├── Habitant.php
│   │   ├── Cadeau.php
│   │   └── TrancheAge.php
│   ├── Exception/
│   │   ├── HabitantNotFoundException.php
│   │   └── CadeauIntrouvableException.php
│   ├── Port/                          # ← Ports secondaires (Out) uniquement
│   │   ├── HabitantRepositoryInterface.php
│   │   ├── CadeauRepositoryInterface.php
│   │   └── NotificationProviderInterface.php
│   └── ValueObject/
│       ├── Email.php
│       └── Age.php
│
├── Application/                       # ← Organisation par Use Case (CQRS)
│   ├── AttribuerCadeaux/
│   │   ├── AttribuerCadeauxCommand.php        # ← Port In (Command)
│   │   ├── AttribuerCadeauxCommandHandler.php # ← Use Case
│   │   └── CadeauFactory.php
│   └── RecupererHabitants/
│       ├── RecupererHabitantsQuery.php        # ← Port In (Query)
│       ├── RecupererHabitantsQueryHandler.php # ← Use Case
│       └── RecupererHabitantsResponse.php
│
└── Infrastructure/
    ├── Doctrine/
    │   ├── Entity/
    │   │   └── HabitantEntity.php
    │   ├── Repository/
    │   │   └── DoctrineHabitantRepository.php
    │   └── Mapper/
    │       └── HabitantMapper.php
    ├── Filesystem/
    │   ├── CadeauFileReader.php
    │   └── Mapper/
    │       └── CadeauMapper.php
    ├── Mail/
    │   └── SymfonyMailerAdapter.php
    ├── Http/
    │   ├── Controller/
    │   │   ├── HabitantController.php
    │   │   └── CadeauController.php
    │   └── DTO/
    │       ├── HabitantResponse.php
    │       └── CadeauResponse.php
    └── Console/
        └── AttribuerCadeauxCommand.php
```

## Migration étape par étape

> **💡 Avec le Bundle Hexagonal Maker**
>
> Chaque étape mentionne la commande du bundle qui peut générer automatiquement les fichiers nécessaires !

### Étape 1 : Créer les entités du domaine (sans Doctrine)

**🚀 Commande du bundle :**
```bash
# Générer une entité pure du domaine avec mapping Doctrine YAML
bin/console make:hexagonal:entity user/account User

# Avec repository automatiquement
bin/console make:hexagonal:entity user/account User --with-repository

# Avec Value Object ID
bin/console make:hexagonal:entity user/account User --with-id-vo
```

**Avant (Entity Doctrine classique)** :
```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HabitantRepository::class)]
#[ORM\Table(name: 'habitants')]
class Habitant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $nom;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $prenom;

    #[ORM\Column(type: 'date')]
    private \DateTime $dateArrivee;

    // Getters et setters...
}
```

**Après (Entité métier pure)** :
```php
<?php

namespace App\Domain\Entity;

use App\Domain\ValueObject\Age;
use App\Domain\ValueObject\Email;

final class Habitant
{
    private string $nom;
    private string $prenom;
    private \DateTimeImmutable $dateArrivee;
    private Age $age;
    private Email $email;

    public function __construct(
        string $nom,
        string $prenom,
        \DateTimeImmutable $dateArrivee,
        Age $age,
        Email $email
    ) {
        $this->nom = $nom;
        $this->prenom = $prenom;
        $this->dateArrivee = $dateArrivee;
        $this->age = $age;
        $this->email = $email;
    }

    public function estEligible(): bool
    {
        $maintenant = new \DateTimeImmutable();
        $interval = $this->dateArrivee->diff($maintenant);

        return $interval->y >= 1;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function getAge(): Age
    {
        return $this->age;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getDateArrivee(): \DateTimeImmutable
    {
        return $this->dateArrivee;
    }
}
```

**Value Object exemple** :

**🚀 Commande du bundle :**
```bash
# Générer un Value Object immutable
bin/console make:hexagonal:value-object user/account Age
bin/console make:hexagonal:value-object user/account Email
```

```php
<?php

namespace App\Domain\ValueObject;

final class Age
{
    private int $valeur;

    public function __construct(int $valeur)
    {
        if ($valeur < 0 || $valeur > 150) {
            throw new \InvalidArgumentException('Age invalide');
        }

        $this->valeur = $valeur;
    }

    public function getValeur(): int
    {
        return $this->valeur;
    }

    public function estDansTrancheAge(TrancheAge $tranche): bool
    {
        return $this->valeur >= $tranche->getMin()
            && $this->valeur <= $tranche->getMax();
    }
}
```

```php
<?php

namespace App\Domain\ValueObject;

final class Email
{
    private string $valeur;

    public function __construct(string $valeur)
    {
        if (!filter_var($valeur, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }

        $this->valeur = $valeur;
    }

    public function getValeur(): string
    {
        return $this->valeur;
    }
}
```

### Étape 2 : Définir les ports (interfaces)

**🚀 Commande du bundle :**
```bash
# Générer le port (interface) + adaptateur Doctrine
bin/console make:hexagonal:repository user/account Habitant

# Cela génère automatiquement :
# - Domain/Port/HabitantRepositoryInterface.php (Port)
# - Infrastructure/Persistence/Doctrine/DoctrineHabitantRepository.php (Adaptateur)
```

**Port secondaire (fournisseur de données)** :
```php
<?php

namespace App\Application\Port\Out;

use App\Domain\Entity\Habitant;

interface HabitantProviderInterface
{
    /**
     * @return Habitant[]
     */
    public function recupererTous(): array;

    public function recupererParId(int $id): ?Habitant;

    public function sauvegarder(Habitant $habitant): void;
}
```

```php
<?php

namespace App\Application\Port\Out;

use App\Domain\Entity\Cadeau;
use App\Domain\ValueObject\Age;

interface CadeauProviderInterface
{
    /**
     * @return Cadeau[]
     */
    public function recupererParAge(Age $age): array;

    public function trouverCadeauAleatoire(Age $age): ?Cadeau;
}
```

```php
<?php

namespace App\Application\Port\Out;

use App\Domain\Entity\Habitant;
use App\Domain\Entity\Cadeau;

interface NotificationProviderInterface
{
    public function notifierHabitant(Habitant $habitant, Cadeau $cadeau): void;

    public function envoyerRecapitulatifMaire(array $attributions): void;
}
```

**Ports primaires (cas d'utilisation) avec CQRS** :

### 💡 Les Commands et Queries SONT les Ports In

En architecture hexagonale avec CQRS, les **Commands et Queries sont déjà les Ports primaires** !

**Pas besoin d'interfaces séparées** dans `Application/Port/In/` - c'est une redondance.

**🚀 Commandes du bundle :**
```bash
# Commande CQRS = Port In pour les opérations d'écriture
bin/console make:hexagonal:command cadeau/attribution attribuer-cadeaux --factory

# Query CQRS = Port In pour les opérations de lecture
bin/console make:hexagonal:query habitant/eligibilite recuperer-eligibles
```

**Structure générée :**
```
Application/
├── AttribuerCadeaux/
│   ├── AttribuerCadeauxCommand.php        # ← Port In (contrat d'entrée)
│   ├── AttribuerCadeauxCommandHandler.php # ← Use Case (logique métier)
│   └── CadeauFactory.php
│
└── RecupererHabitants/
    ├── RecupererHabitantsQuery.php        # ← Port In (contrat d'entrée)
    ├── RecupererHabitantsQueryHandler.php # ← Use Case (logique métier)
    └── RecupererHabitantsResponse.php     # ← DTO de réponse
```

**Exemple de Command (Port In) :**
```php
<?php

namespace App\Application\AttribuerCadeaux;

/**
 * ✅ Cette Command EST le Port In (port primaire) !
 * Elle définit le contrat d'entrée de ce cas d'utilisation
 */
final readonly class AttribuerCadeauxCommand
{
    public function __construct(
        public \DateTimeImmutable $date,
    ) {
    }
}
```

**Exemple de CommandHandler (Use Case) :**
```php
<?php

namespace App\Application\AttribuerCadeaux;

use App\Domain\Port\HabitantRepositoryInterface;
use App\Domain\Port\CadeauRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AttribuerCadeauxCommandHandler
{
    public function __construct(
        private HabitantRepositoryInterface $habitantRepository,
        private CadeauRepositoryInterface $cadeauRepository,
    ) {
    }

    public function __invoke(AttribuerCadeauxCommand $command): void
    {
        // Logique métier ici
        $habitants = $this->habitantRepository->recupererTous();

        $eligibles = array_filter(
            $habitants,
            fn($habitant) => $habitant->estEligible()
        );

        foreach ($eligibles as $habitant) {
            $cadeau = $this->cadeauRepository->trouverCadeauAleatoire(
                $habitant->getAge()
            );
            // ... attribution
        }
    }
}
```

**Exemple de Query (Port In) :**
```php
<?php

namespace App\Application\RecupererHabitants;

/**
 * ✅ Cette Query EST le Port In (port primaire) !
 */
final readonly class RecupererHabitantsQuery
{
    public function __construct(
        public bool $eligiblesUniquement = false,
    ) {
    }
}
```

**Exemple de QueryHandler (Use Case) :**
```php
<?php

namespace App\Application\RecupererHabitants;

use App\Domain\Port\HabitantRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecupererHabitantsQueryHandler
{
    public function __construct(
        private HabitantRepositoryInterface $habitantRepository,
    ) {
    }

    public function __invoke(RecupererHabitantsQuery $query): RecupererHabitantsResponse
    {
        $habitants = $this->habitantRepository->recupererTous();

        if ($query->eligiblesUniquement) {
            $habitants = array_filter(
                $habitants,
                fn($habitant) => $habitant->estEligible()
            );
        }

        return new RecupererHabitantsResponse($habitants);
    }
}
```

### 📊 Schéma des dépendances CQRS

```
┌─────────────────────────────────────────────┐
│  UI (Controller, CLI, API)                  │
│  ┌────────────────────────────────────────┐ │
│  │ dispatch(AttribuerCadeauxCommand)      │ │
│  └────────────────┬───────────────────────┘ │
└───────────────────┼─────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│  APPLICATION LAYER                          │
│  ┌────────────────────────────────────────┐ │
│  │ AttribuerCadeauxCommand (Port In)      │ │ ← Contrat d'entrée
│  └────────────────┬───────────────────────┘ │
│                   │                          │
│  ┌────────────────▼───────────────────────┐ │
│  │ AttribuerCadeauxCommandHandler         │ │ ← Use Case
│  │ (Logique métier)                       │ │
│  └────────────────┬───────────────────────┘ │
└───────────────────┼─────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│  DOMAIN LAYER                               │
│  ┌────────────────────────────────────────┐ │
│  │ HabitantRepositoryInterface (Port Out) │ │ ← Interface
│  └────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
                    │
                    ▼ implements
┌─────────────────────────────────────────────┐
│  INFRASTRUCTURE LAYER                       │
│  ┌────────────────────────────────────────┐ │
│  │ DoctrineHabitantRepository (Adapter)   │ │ ← Implémentation
│  └────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
```

### ✅ Avantages de CQRS avec Ports implicites

1. **Simplicité** : Command/Query = contrat d'entrée clair (DTO)
2. **Moins de fichiers** : Pas besoin d'interfaces `Port/In/`
3. **Async natif** : Compatible Symfony Messenger out-of-the-box
4. **Séparation claire** : Command (écriture) vs Query (lecture)
5. **Testabilité** : Mock du Handler facilement

### 📝 Utilisation dans les Controllers

```php
<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\AttribuerCadeaux\AttribuerCadeauxCommand;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/cadeaux', name: 'api_cadeaux_')]
final class CadeauController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    #[Route('/attribuer', name: 'attribuer', methods: ['POST'])]
    public function attribuer(): Response
    {
        // Créer la Command (Port In)
        $command = new AttribuerCadeauxCommand(
            date: new \DateTimeImmutable()
        );

        // Dispatcher vers le Handler
        $this->commandBus->dispatch($command);

        return $this->json([
            'message' => 'Attribution des cadeaux lancée'
        ]);
    }
}
```

**🎯 En résumé : Avec CQRS, Command/Query = Port In. Simple et efficace !**

### Étape 3 : Implémenter les cas d'utilisation (Command & Handler)

**🚀 Commande du bundle :**
```bash
# Générer une commande CQRS complète avec handler
bin/console make:hexagonal:command cadeau/attribution attribuer-cadeaux --with-tests
```

**Structure générée :**
```
Application/
└── AttribuerCadeaux/
    ├── AttribuerCadeauxCommand.php        # ← Port In (DTO)
    ├── AttribuerCadeauxCommandHandler.php # ← Use Case
    └── CadeauFactory.php                  # ← Factory (si --factory)
```

**Command (Port In - contrat d'entrée) :**
```php
<?php

namespace App\Application\AttribuerCadeaux;

/**
 * Command = Port In (contrat d'entrée du cas d'utilisation)
 */
final readonly class AttribuerCadeauxCommand
{
    public function __construct(
        public \DateTimeImmutable $date,
    ) {
    }
}
```

**CommandHandler (Use Case - logique métier) :**
```php
<?php

namespace App\Application\AttribuerCadeaux;

use App\Domain\Port\HabitantRepositoryInterface;
use App\Domain\Port\CadeauRepositoryInterface;
use App\Domain\Port\NotificationProviderInterface;
use App\Domain\Exception\CadeauIntrouvableException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AttribuerCadeauxCommandHandler
{
    public function __construct(
        private HabitantRepositoryInterface $habitantRepository,
        private CadeauRepositoryInterface $cadeauRepository,
        private NotificationProviderInterface $notificationProvider,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(AttribuerCadeauxCommand $command): void
    {
        $habitants = $this->habitantRepository->recupererTous();

        $eligibles = array_filter(
            $habitants,
            fn($habitant) => $habitant->estEligible()
        );

        $attributions = [];

        foreach ($eligibles as $habitant) {
            try {
                $cadeau = $this->cadeauRepository->trouverCadeauAleatoire(
                    $habitant->getAge()
                );

                if ($cadeau === null) {
                    throw new CadeauIntrouvableException(
                        $habitant->getAge()->getValeur()
                    );
                }

                $this->notificationProvider->notifierHabitant($habitant, $cadeau);

                $attributions[] = [
                    'habitant' => $habitant,
                    'cadeau' => $cadeau
                ];

                $this->logger->info('Cadeau attribué', [
                    'habitant' => $habitant->getNom(),
                    'cadeau' => $cadeau->getReference()
                ]);

            } catch (CadeauIntrouvableException $e) {
                $this->logger->error($e->getMessage());
                continue;
            }
        }

        if (!empty($attributions)) {
            $this->notificationProvider->envoyerRecapitulatifMaire($attributions);
        }
    }
}
```

**Query exemple (lecture) :**
```bash
bin/console make:hexagonal:query habitant/eligibilite recuperer-eligibles
```

```php
<?php

namespace App\Application\RecupererHabitants;

/**
 * Query = Port In pour les opérations de lecture
 */
final readonly class RecupererHabitantsQuery
{
    public function __construct(
        public bool $eligiblesUniquement = false,
    ) {
    }
}
```

```php
<?php

namespace App\Application\RecupererHabitants;

use App\Domain\Port\HabitantRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecupererHabitantsQueryHandler
{
    public function __construct(
        private HabitantRepositoryInterface $habitantRepository,
    ) {
    }

    public function __invoke(RecupererHabitantsQuery $query): RecupererHabitantsResponse
    {
        $habitants = $this->habitantRepository->recupererTous();

        if ($query->eligiblesUniquement) {
            $habitants = array_filter(
                $habitants,
                fn($habitant) => $habitant->estEligible()
            );
        }

        return new RecupererHabitantsResponse($habitants);
    }
}
```

### Étape 4 : Créer l'adaptateur Doctrine

**🚀 Commande du bundle :**
```bash
# L'adaptateur Doctrine a déjà été généré à l'étape 2 !
# Avec la commande : make:hexagonal:repository

# Si vous voulez le générer séparément :
bin/console make:hexagonal:repository cadeau/attribution Habitant
```

**Entité Doctrine (Infrastructure)** :
```php
<?php

namespace App\Infrastructure\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'habitants')]
class HabitantEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $nom;

    #[ORM\Column(length: 255)]
    private string $prenom;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dateArrivee;

    #[ORM\Column]
    private int $age;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cadeauReference = null;

    // Getters et setters uniquement (pas de logique métier)

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getDateArrivee(): \DateTimeImmutable
    {
        return $this->dateArrivee;
    }

    public function setDateArrivee(\DateTimeImmutable $dateArrivee): self
    {
        $this->dateArrivee = $dateArrivee;
        return $this;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): self
    {
        $this->age = $age;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getCadeauReference(): ?string
    {
        return $this->cadeauReference;
    }

    public function setCadeauReference(?string $cadeauReference): self
    {
        $this->cadeauReference = $cadeauReference;
        return $this;
    }
}
```

**Mapper Domain <-> Infrastructure** :

**Option 1 : Mapper manuel (contrôle total)**
```php
<?php

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Entity\Habitant;
use App\Domain\ValueObject\Age;
use App\Domain\ValueObject\Email;
use App\Infrastructure\Doctrine\Entity\HabitantEntity;

final class HabitantMapper
{
    public function toDomain(HabitantEntity $entity): Habitant
    {
        return new Habitant(
            nom: $entity->getNom(),
            prenom: $entity->getPrenom(),
            dateArrivee: $entity->getDateArrivee(),
            age: new Age($entity->getAge()),
            email: new Email($entity->getEmail())
        );
    }

    public function toEntity(Habitant $habitant): HabitantEntity
    {
        $entity = new HabitantEntity();
        $entity->setNom($habitant->getNom());
        $entity->setPrenom($habitant->getPrenom());
        $entity->setDateArrivee($habitant->getDateArrivee());
        $entity->setAge($habitant->getAge()->getValeur());
        $entity->setEmail($habitant->getEmail()->getValeur());

        return $entity;
    }
}
```

**Option 2 : Utiliser le Object Mapper de Symfony (recommandé pour projets complexes)**

```bash
# Installation
composer require symfony/object-mapper
```

```php
<?php

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Entity\Habitant;
use App\Domain\ValueObject\Age;
use App\Domain\ValueObject\Email;
use App\Infrastructure\Doctrine\Entity\HabitantEntity;
use Symfony\Component\ObjectMapper\ObjectMapper;

final class HabitantMapper
{
    public function __construct(
        private readonly ObjectMapper $objectMapper
    ) {
    }

    public function toDomain(HabitantEntity $entity): Habitant
    {
        // Mapping automatique avec transformation personnalisée
        return $this->objectMapper->map($entity, Habitant::class, [
            'age' => fn($value) => new Age($value),
            'email' => fn($value) => new Email($value),
        ]);
    }

    public function toEntity(Habitant $habitant): HabitantEntity
    {
        return $this->objectMapper->map($habitant, HabitantEntity::class, [
            'age' => fn(Age $age) => $age->getValeur(),
            'email' => fn(Email $email) => $email->getValeur(),
        ]);
    }
}
```

**Option 3 : Mapper avec transformation avancée**

```php
<?php

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Entity\Habitant;
use App\Infrastructure\Doctrine\Entity\HabitantEntity;
use Symfony\Component\ObjectMapper\Attribute\MapTo;
use Symfony\Component\ObjectMapper\ObjectMapper;

final class HabitantMapper
{
    public function __construct(
        private readonly ObjectMapper $objectMapper
    ) {
    }

    /**
     * Mapping avec contexte pour gérer les cas complexes
     */
    public function toDomain(HabitantEntity $entity): Habitant
    {
        // Utiliser le contexte pour des transformations conditionnelles
        return $this->objectMapper->map($entity, Habitant::class, context: [
            'groups' => ['domain'],
            'datetime_format' => 'Y-m-d',
        ]);
    }

    /**
     * Mapping de collection
     * @param HabitantEntity[] $entities
     * @return Habitant[]
     */
    public function toDomainCollection(array $entities): array
    {
        return array_map(
            fn(HabitantEntity $entity) => $this->toDomain($entity),
            $entities
        );
    }
}
```

**Comparaison des approches :**

| Approche | Avantages | Inconvénients | Quand l'utiliser |
|----------|-----------|---------------|------------------|
| **Mapper Manuel** | - Contrôle total<br>- Pas de dépendance<br>- Facile à déboguer | - Code répétitif<br>- Maintenance lourde | Projets simples, peu de mappings |
| **Object Mapper Symfony** | - Moins de code<br>- Mapping automatique<br>- Transformations flexibles | - Dépendance au composant<br>- Magic (moins explicite) | Projets complexes, nombreux DTOs |
| **Attributs PHP 8** | - Déclaratif<br>- Configuration proche du code<br>- Type-safe | - Couplage aux métadonnées<br>- Moins flexible | Mapping simple et stable |

**💡 Recommandation du bundle :**
Pour la plupart des projets, un **mapper manuel simple** suffit. Utilisez Object Mapper si vous avez :
- Plus de 10 entités à mapper
- Des transformations complexes répétitives
- Besoin de mapper des collections fréquemment

**Voir la documentation complète :** https://symfony.com/doc/current/object_mapper.html

**Repository (Adaptateur)** :
```php
<?php

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Port\Out\HabitantProviderInterface;
use App\Domain\Entity\Habitant;
use App\Infrastructure\Doctrine\Entity\HabitantEntity;
use App\Infrastructure\Doctrine\Mapper\HabitantMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineHabitantRepository extends ServiceEntityRepository implements HabitantProviderInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly HabitantMapper $mapper
    ) {
        parent::__construct($registry, HabitantEntity::class);
    }

    public function recupererTous(): array
    {
        $entities = $this->findAll();

        return array_map(
            fn(HabitantEntity $entity) => $this->mapper->toDomain($entity),
            $entities
        );
    }

    public function recupererParId(int $id): ?Habitant
    {
        $entity = $this->find($id);

        return $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function sauvegarder(Habitant $habitant): void
    {
        $entity = $this->mapper->toEntity($habitant);

        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }
}
```

### Étape 5 : Créer l'adaptateur Filesystem

**💡 Note du bundle :**
```bash
# Les adaptateurs de fichiers sont trop spécifiques pour être auto-générés
# Créez-les manuellement en suivant le pattern Port & Adapter

# Mais vous pouvez créer l'exception métier :
bin/console make:hexagonal:exception cadeau/catalogue CadeauIntrouvableException
```

```php
<?php

namespace App\Infrastructure\Filesystem;

use App\Application\Port\Out\CadeauProviderInterface;
use App\Domain\Entity\Cadeau;
use App\Domain\Entity\TrancheAge;
use App\Domain\ValueObject\Age;
use App\Domain\Exception\CadeauIntrouvableException;

final class CadeauFileReader implements CadeauProviderInterface
{
    private array $cadeaux = [];

    public function __construct(
        private readonly string $cadeauxFilePath
    ) {
        $this->chargerCadeaux();
    }

    public function recupererParAge(Age $age): array
    {
        return array_filter(
            $this->cadeaux,
            fn(Cadeau $cadeau) => $age->estDansTrancheAge($cadeau->getTrancheAge())
        );
    }

    public function trouverCadeauAleatoire(Age $age): ?Cadeau
    {
        $cadeaux = $this->recupererParAge($age);

        if (empty($cadeaux)) {
            return null;
        }

        $index = array_rand($cadeaux);
        return $cadeaux[$index];
    }

    private function chargerCadeaux(): void
    {
        if (!file_exists($this->cadeauxFilePath)) {
            throw new \RuntimeException(
                "Fichier de cadeaux introuvable : {$this->cadeauxFilePath}"
            );
        }

        $lines = file($this->cadeauxFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $parts = explode('|', $line);

            if (count($parts) !== 4) {
                continue;
            }

            [$reference, $description, $montant, $trancheAgeStr] = $parts;

            [$min, $max] = explode('-', $trancheAgeStr);

            $this->cadeaux[] = new Cadeau(
                reference: trim($reference),
                description: trim($description),
                montant: (float) trim($montant),
                trancheAge: new TrancheAge((int) trim($min), (int) trim($max))
            );
        }
    }
}
```

### Étape 6 : Créer l'adaptateur Mail

**💡 Note du bundle :**
```bash
# Les adaptateurs mail sont également trop spécifiques
# Créez-les manuellement selon vos besoins (Symfony Mailer, API externe, etc.)

# Pour un traitement asynchrone, vous pouvez générer un message handler :
bin/console make:hexagonal:message-handler notification/mail EnvoyerEmailHabitant --with-message
```

```php
<?php

namespace App\Infrastructure\Mail;

use App\Application\Port\Out\NotificationProviderInterface;
use App\Domain\Entity\Habitant;
use App\Domain\Entity\Cadeau;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

final class SymfonyMailerAdapter implements NotificationProviderInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $emailMaire,
        private readonly LoggerInterface $logger
    ) {
    }

    public function notifierHabitant(Habitant $habitant, Cadeau $cadeau): void
    {
        $email = (new Email())
            ->from('noreply@mairie.fr')
            ->to($habitant->getEmail()->getValeur())
            ->subject('Félicitations pour votre première année dans notre commune !')
            ->html($this->genererContenuHabitant($habitant, $cadeau));

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email habitant', [
                'habitant' => $habitant->getNom(),
                'error' => $e->getMessage()
            ]);

            // Ne pas bloquer le processus si l'envoi échoue
        }
    }

    public function envoyerRecapitulatifMaire(array $attributions): void
    {
        $email = (new Email())
            ->from('noreply@mairie.fr')
            ->to($this->emailMaire)
            ->subject('Récapitulatif des attributions de cadeaux')
            ->html($this->genererContenuRecapitulatif($attributions));

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email maire', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function genererContenuHabitant(Habitant $habitant, Cadeau $cadeau): string
    {
        return sprintf(
            '<h1>Bonjour %s %s,</h1>
            <p>Nous avons le plaisir de vous offrir un cadeau pour célébrer votre première année dans notre commune !</p>
            <p><strong>Votre cadeau :</strong> %s</p>
            <p>Valeur : %.2f €</p>
            <p>Cordialement,<br>La Mairie</p>',
            $habitant->getPrenom(),
            $habitant->getNom(),
            $cadeau->getDescription(),
            $cadeau->getMontant()
        );
    }

    private function genererContenuRecapitulatif(array $attributions): string
    {
        $contenu = '<h1>Récapitulatif des attributions de cadeaux</h1>';
        $contenu .= '<p>Nombre total : ' . count($attributions) . '</p>';
        $contenu .= '<table border="1"><tr><th>Nom</th><th>Prénom</th><th>Cadeau</th><th>Montant</th></tr>';

        foreach ($attributions as $attribution) {
            $habitant = $attribution['habitant'];
            $cadeau = $attribution['cadeau'];

            $contenu .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%.2f €</td></tr>',
                $habitant->getNom(),
                $habitant->getPrenom(),
                $cadeau->getDescription(),
                $cadeau->getMontant()
            );
        }

        $contenu .= '</table>';

        return $contenu;
    }
}
```

### Étape 7 : Créer les adaptateurs primaires

**🚀 Commande du bundle :**
```bash
# Générer un contrôleur web
bin/console make:hexagonal:controller cadeau/attribution AttribuerCadeaux /cadeaux/attribuer

# Avec workflow complet (contrôleur + formulaire + use case + commande + input)
bin/console make:hexagonal:controller cadeau/attribution AttribuerCadeaux /cadeaux/attribuer --with-workflow

# Générer un formulaire Symfony
bin/console make:hexagonal:form habitant/gestion Habitant

# Avec commande CQRS liée
bin/console make:hexagonal:form habitant/gestion Habitant --with-command --action=Create
```

**Controller API (Single Action Controller - recommandé)** :

### 💡 Bonne pratique : Une action par contrôleur

L'architecture hexagonale recommande le **Single Action Controller** :
- ✅ 1 contrôleur = 1 action = 1 use case/command
- ✅ Principe de Responsabilité Unique (SRP)
- ✅ Cohérent avec CQRS (1 Command → 1 Controller)
- ✅ Plus facile à tester et à maintenir
- ✅ Routes plus explicites

**❌ Mauvaise pratique (approche classique Symfony) :**
```php
// ❌ UN contrôleur avec PLUSIEURS actions (couplage fort)
#[Route('/api/cadeaux')]
class CadeauController
{
    public function attribuer() { }         // Action 1
    public function lister() { }            // Action 2
    public function supprimer() { }         // Action 3
}
```

**✅ Bonne pratique (approche hexagonale) :**
```php
// ✅ UN contrôleur = UNE action (découplage)
#[Route('/api/cadeaux/attribuer', methods: ['POST'])]
class AttribuerCadeauxController { }

#[Route('/api/cadeaux', methods: ['GET'])]
class ListerCadeauxController { }

#[Route('/api/cadeaux/{id}', methods: ['DELETE'])]
class SupprimerCadeauController { }
```

**Exemple complet (Single Action Controller) :**

```php
<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\AttribuerCadeaux\AttribuerCadeauxCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * ✅ Single Action Controller : 1 contrôleur = 1 responsabilité
 */
#[Route('/api/cadeaux/attribuer', name: 'api_cadeaux_attribuer', methods: ['POST'])]
final class AttribuerCadeauxController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    /**
     * Méthode __invoke() permet d'appeler le contrôleur comme une fonction
     */
    public function __invoke(): JsonResponse
    {
        $command = new AttribuerCadeauxCommand(
            date: new \DateTimeImmutable()
        );

        $this->commandBus->dispatch($command);

        return $this->json([
            'message' => 'Attribution des cadeaux lancée'
        ], Response::HTTP_ACCEPTED);
    }
}
```

**Autre exemple (Query) :**

```php
<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\RecupererHabitants\RecupererHabitantsQuery;
use App\Infrastructure\Http\DTO\HabitantResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/habitants/eligibles', name: 'api_habitants_eligibles', methods: ['GET'])]
final class RecupererHabitantsEligiblesController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $queryBus,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $query = new RecupererHabitantsQuery(
            eligiblesUniquement: true
        );

        $envelope = $this->queryBus->dispatch($query);

        /** @var RecupererHabitantsResponse $response */
        $response = $envelope->last(HandledStamp::class)->getResult();

        $dto = array_map(
            fn($habitant) => HabitantResponse::fromDomain($habitant),
            $response->habitants
        );

        return $this->json($dto);
    }
}
```

### 📊 Comparaison des approches

| Aspect | Multi-actions (❌ classique) | Single Action (✅ hexagonal) |
|--------|------------------------------|------------------------------|
| **Couplage** | Fort (plusieurs use cases) | Faible (un seul use case) |
| **Tests** | Complexe (plusieurs méthodes) | Simple (une méthode) |
| **Routes** | `/api/cadeaux/attribuer`<br>`/api/cadeaux/lister` | `/api/cadeaux/attribuer`<br>`/api/cadeaux` |
| **Responsabilité** | Multiple (SRP violé) | Unique (SRP respecté) |
| **Lisibilité** | Moins claire | Très explicite |
| **Injection** | Plusieurs use cases injectés | Un seul use case/command |
| **Évolution** | Modification du contrôleur existant | Nouveau contrôleur isolé |

### 🚀 Commande du bundle pour générer

```bash
# Le bundle génère automatiquement des Single Action Controllers
bin/console make:hexagonal:controller cadeau/attribution AttribuerCadeaux /api/cadeaux/attribuer

# Génère :
# - AttribuerCadeauxController.php (avec méthode __invoke)
# - Route dédiée
# - Injection du CommandBus
```

### 💡 Organisation des fichiers

```
Infrastructure/Http/Controller/
├── AttribuerCadeauxController.php       # POST /api/cadeaux/attribuer
├── ListerCadeauxController.php          # GET  /api/cadeaux
├── RecupererCadeauController.php        # GET  /api/cadeaux/{id}
├── SupprimerCadeauController.php        # DELETE /api/cadeaux/{id}
└── RecupererHabitantsEligiblesController.php  # GET /api/habitants/eligibles
```

### 🎯 Avantages en pratique

**Exemple : Ajouter une nouvelle action**

**❌ Approche multi-actions :**
```php
// Modifier CadeauController existant (risque de régression)
class CadeauController
{
    public function attribuer() { }
    public function lister() { }

    // ❌ Ajouter une nouvelle méthode (modification de code existant)
    public function valider() { }
}
```

**✅ Approche Single Action :**
```bash
# ✅ Créer un nouveau contrôleur (pas de modification de l'existant)
bin/console make:hexagonal:controller cadeau/validation ValiderCadeau /api/cadeaux/valider

# Aucun risque sur les contrôleurs existants !
```

### 📝 Note sur le naming

**Convention de nommage :**
- Nom du contrôleur = Nom de l'action au métier
- `AttribuerCadeauxController` (verbe à l'infinitif)
- `RecupererHabitantsEligiblesController`
- Route = chemin REST classique

**🎯 En résumé : Single Action Controller = Meilleure pratique en architecture hexagonale !**

**DTO de présentation** :
```php
<?php

namespace App\Infrastructure\Http\DTO;

use App\Domain\Entity\Habitant;

final class HabitantResponse
{
    public function __construct(
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $email,
        public readonly int $age,
        public readonly string $dateArrivee
    ) {
    }

    public static function fromDomain(Habitant $habitant): self
    {
        return new self(
            nom: $habitant->getNom(),
            prenom: $habitant->getPrenom(),
            email: $habitant->getEmail()->getValeur(),
            age: $habitant->getAge()->getValeur(),
            dateArrivee: $habitant->getDateArrivee()->format('Y-m-d')
        );
    }
}
```

**Commande Symfony** :

**🚀 Commande du bundle :**
```bash
# Générer une commande CLI
bin/console make:hexagonal:cli-command cadeau/attribution AttribuerCadeaux app:cadeaux:attribuer

# Avec workflow use case complet
bin/console make:hexagonal:cli-command cadeau/attribution AttribuerCadeaux app:cadeaux:attribuer --with-use-case
```

```php
<?php

namespace App\Infrastructure\Console;

use App\Application\Port\In\AttribuerCadeauxUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:attribuer-cadeaux',
    description: 'Attribue les cadeaux aux habitants éligibles'
)]
final class AttribuerCadeauxCommand extends Command
{
    public function __construct(
        private readonly AttribuerCadeauxUseCase $attribuerCadeauxUseCase
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Attribution des cadeaux');
        $io->text('Démarrage du processus...');

        try {
            $this->attribuerCadeauxUseCase->attribuer();

            $io->success('Attribution des cadeaux effectuée avec succès !');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'attribution : ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
```

### Étape 8 : Configuration des services (DI)

**💡 Note du bundle :**
```bash
# Le bundle génère des classes compatibles avec l'autowiring Symfony
# La configuration par défaut fonctionne dans la plupart des cas !

# Les Handlers sont auto-configurés avec AsMessageHandler
# Les Repositories sont auto-wirés via les interfaces
```

**config/services.yaml** :
```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Domain - Aucune configuration nécessaire (POPO purs)

    # Application Layer - Handlers auto-configurés
    App\Application\:
        resource: '../src/Application/'

    # Infrastructure Layer
    App\Infrastructure\:
        resource: '../src/Infrastructure/'
        exclude:
            - '../src/Infrastructure/Doctrine/Entity/'
            - '../src/Infrastructure/Http/DTO/'

    # Bind des interfaces (Ports secondaires) aux implémentations (Adapters)
    App\Domain\Port\HabitantRepositoryInterface:
        class: App\Infrastructure\Doctrine\Repository\DoctrineHabitantRepository

    App\Domain\Port\CadeauRepositoryInterface:
        class: App\Infrastructure\Filesystem\CadeauFileReader
        arguments:
            $cadeauxFilePath: '%kernel.project_dir%/data/cadeaux.txt'

    App\Domain\Port\NotificationProviderInterface:
        class: App\Infrastructure\Mail\SymfonyMailerAdapter
        arguments:
            $emailMaire: '%env(EMAIL_MAIRE)%'
```

**config/packages/messenger.yaml** :
```yaml
framework:
    messenger:
        # Bus pour les Commands (écriture)
        default_bus: command.bus

        buses:
            command.bus:
                middleware:
                    - doctrine_transaction

            # Bus pour les Queries (lecture)
            query.bus:
                middleware: []

        # Routing des messages
        routing:
            'App\Application\*\*Command': command.bus
            'App\Application\*\*Query': query.bus
```

**.env** :
```bash
# Mailer
MAILER_DSN=smtp://localhost:1025

# Application
EMAIL_MAIRE=maire@mairie.fr
```

## Tests avec l'Architecture Hexagonale

**🚀 Commandes du bundle pour les tests :**
```bash
# Générer un test de use case
bin/console make:hexagonal:use-case-test cadeau/attribution AttribuerCadeaux

# Générer un test de contrôleur
bin/console make:hexagonal:controller-test cadeau/attribution AttribuerCadeaux /cadeaux/attribuer

# Générer un test de commande CLI
bin/console make:hexagonal:cli-command-test cadeau/attribution AttribuerCadeaux app:cadeaux:attribuer

# Générer les tests avec la commande CRUD
bin/console make:hexagonal:crud blog/post Post --with-tests

# Générer les tests avec une commande
bin/console make:hexagonal:command cadeau/attribution attribuer --with-tests
```

### Tests unitaires du domaine (sans Symfony)

```php
<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Entity\Habitant;
use App\Domain\ValueObject\Age;
use App\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

final class HabitantTest extends TestCase
{
    public function test_habitant_arrive_il_y_a_plus_dun_an_est_eligible(): void
    {
        // Given
        $dateArrivee = new \DateTimeImmutable('-2 years');
        $habitant = new Habitant(
            'Dupont',
            'Jean',
            $dateArrivee,
            new Age(25),
            new Email('jean.dupont@example.com')
        );

        // When
        $estEligible = $habitant->estEligible();

        // Then
        $this->assertTrue($estEligible);
    }

    public function test_habitant_arrive_il_y_a_moins_dun_an_nest_pas_eligible(): void
    {
        // Given
        $dateArrivee = new \DateTimeImmutable('-6 months');
        $habitant = new Habitant(
            'Martin',
            'Marie',
            $dateArrivee,
            new Age(30),
            new Email('marie.martin@example.com')
        );

        // When
        $estEligible = $habitant->estEligible();

        // Then
        $this->assertFalse($estEligible);
    }
}
```

### Tests unitaires des cas d'utilisation (avec mocks)

```php
<?php

namespace App\Tests\Application\Service;

use App\Application\Port\Out\HabitantProviderInterface;
use App\Application\Port\Out\CadeauProviderInterface;
use App\Application\Port\Out\NotificationProviderInterface;
use App\Application\Service\AttribuerCadeauxService;
use App\Domain\Entity\Habitant;
use App\Domain\Entity\Cadeau;
use App\Domain\Entity\TrancheAge;
use App\Domain\ValueObject\Age;
use App\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AttribuerCadeauxServiceTest extends TestCase
{
    public function test_attribue_cadeaux_aux_habitants_eligibles(): void
    {
        // Given
        $habitantProvider = $this->createMock(HabitantProviderInterface::class);
        $cadeauProvider = $this->createMock(CadeauProviderInterface::class);
        $notificationProvider = $this->createMock(NotificationProviderInterface::class);

        $habitantEligible = new Habitant(
            'Dupont',
            'Jean',
            new \DateTimeImmutable('-2 years'),
            new Age(5),
            new Email('jean.dupont@example.com')
        );

        $habitantNonEligible = new Habitant(
            'Martin',
            'Marie',
            new \DateTimeImmutable('-6 months'),
            new Age(30),
            new Email('marie.martin@example.com')
        );

        $cadeau = new Cadeau(
            'REF001',
            'Jouet éducatif',
            25.0,
            new TrancheAge(3, 10)
        );

        $habitantProvider
            ->method('recupererTous')
            ->willReturn([$habitantEligible, $habitantNonEligible]);

        $cadeauProvider
            ->method('trouverCadeauAleatoire')
            ->willReturn($cadeau);

        // Expect: notification uniquement pour l'habitant éligible
        $notificationProvider
            ->expects($this->once())
            ->method('notifierHabitant')
            ->with($habitantEligible, $cadeau);

        $notificationProvider
            ->expects($this->once())
            ->method('envoyerRecapitulatifMaire');

        $service = new AttribuerCadeauxService(
            $habitantProvider,
            $cadeauProvider,
            $notificationProvider,
            new NullLogger()
        );

        // When
        $service->attribuer();

        // Then: les expectations sont vérifiées automatiquement
    }

    public function test_ne_notifie_pas_si_aucun_habitant_eligible(): void
    {
        // Given
        $habitantProvider = $this->createMock(HabitantProviderInterface::class);
        $cadeauProvider = $this->createMock(CadeauProviderInterface::class);
        $notificationProvider = $this->createMock(NotificationProviderInterface::class);

        $habitantNonEligible = new Habitant(
            'Martin',
            'Marie',
            new \DateTimeImmutable('-6 months'),
            new Age(30),
            new Email('marie.martin@example.com')
        );

        $habitantProvider
            ->method('recupererTous')
            ->willReturn([$habitantNonEligible]);

        // Expect: aucune notification
        $notificationProvider
            ->expects($this->never())
            ->method('notifierHabitant');

        $notificationProvider
            ->expects($this->never())
            ->method('envoyerRecapitulatifMaire');

        $service = new AttribuerCadeauxService(
            $habitantProvider,
            $cadeauProvider,
            $notificationProvider,
            new NullLogger()
        );

        // When
        $service->attribuer();

        // Then: les expectations sont vérifiées
    }
}
```

### Tests d'intégration des adaptateurs

```php
<?php

namespace App\Tests\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Repository\DoctrineHabitantRepository;
use App\Domain\Entity\Habitant;
use App\Domain\ValueObject\Age;
use App\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineHabitantRepositoryTest extends KernelTestCase
{
    private DoctrineHabitantRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(DoctrineHabitantRepository::class);
    }

    public function test_peut_sauvegarder_et_recuperer_un_habitant(): void
    {
        // Given
        $habitant = new Habitant(
            'Test',
            'Integration',
            new \DateTimeImmutable('-2 years'),
            new Age(25),
            new Email('test@example.com')
        );

        // When
        $this->repository->sauvegarder($habitant);
        $habitants = $this->repository->recupererTous();

        // Then
        $this->assertNotEmpty($habitants);
        $trouve = false;
        foreach ($habitants as $h) {
            if ($h->getNom() === 'Test' && $h->getPrenom() === 'Integration') {
                $trouve = true;
                break;
            }
        }
        $this->assertTrue($trouve);
    }
}
```

### Tests fonctionnels (API)

```php
<?php

namespace App\Tests\Infrastructure\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CadeauControllerTest extends WebTestCase
{
    public function test_peut_attribuer_les_cadeaux_via_api(): void
    {
        // Given
        $client = static::createClient();

        // When
        $client->request('POST', '/api/cadeaux/attribuer');

        // Then
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('succès', $data['message']);
    }

    public function test_peut_recuperer_habitants_eligibles(): void
    {
        // Given
        $client = static::createClient();

        // When
        $client->request('GET', '/api/cadeaux/habitants-eligibles');

        // Then
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }
}
```

## Validation de l'architecture avec Deptrac

**deptrac.yaml** :
```yaml
deptrac:
  paths:
    - ./src

  layers:
    - name: Domain
      collectors:
        - type: directory
          value: src/Domain/.*

    - name: Application
      collectors:
        - type: directory
          value: src/Application/.*

    - name: Infrastructure
      collectors:
        - type: directory
          value: src/Infrastructure/.*

  ruleset:
    Domain:
      - Application
      - Infrastructure

    Application:
      - Infrastructure

    Infrastructure: ~

  skip_violations:
    # Ajoutez ici les violations à ignorer temporairement
```

**Installation et utilisation** :
```bash
composer require --dev qossmic/deptrac-shim

# Analyser l'architecture
vendor/bin/deptrac analyze

# Générer un graphique
vendor/bin/deptrac analyze --formatter=graphviz --output=deptrac.png
```

## Avantages avec Symfony

### 1. Indépendance des frameworks

**Avant** : Tout est couplé à Symfony/Doctrine
```php
class HabitantService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer
    ) {}
}
```

**Après** : Le métier ne connaît pas Symfony
```php
class AttribuerCadeauxService
{
    public function __construct(
        private HabitantProviderInterface $habitantProvider,
        private NotificationProviderInterface $notificationProvider
    ) {}
}
```

### 2. Tests rapides et fiables

```bash
# Tests unitaires du domaine (< 1 seconde)
./vendor/bin/phpunit tests/Domain

# Tests unitaires des use cases (< 2 secondes)
./vendor/bin/phpunit tests/Application

# Tests d'intégration uniquement quand nécessaire
./vendor/bin/phpunit tests/Infrastructure
```

### 3. Migration facilitée

**Changer de Doctrine à autre chose** :
- Créer un nouveau repository implémentant `HabitantProviderInterface`
- Modifier `services.yaml`
- Le reste du code ne change pas !

**Ajouter un canal de notification (SMS)** :
```php
class SmsNotificationAdapter implements NotificationProviderInterface
{
    public function notifierHabitant(Habitant $habitant, Cadeau $cadeau): void
    {
        // Implémentation SMS
    }
}
```

### 4. Démonstrations précoces

**Adaptateur en mémoire pour les démos** :
```php
class InMemoryHabitantProvider implements HabitantProviderInterface
{
    private array $habitants = [];

    public function __construct()
    {
        // Données de démo
        $this->habitants = [
            new Habitant('Demo', 'User', new \DateTimeImmutable('-2 years'),
                        new Age(25), new Email('demo@example.com'))
        ];
    }

    public function recupererTous(): array
    {
        return $this->habitants;
    }
}
```

## Bonnes pratiques Symfony

### 1. Ne pas hériter d'AbstractController dans le domaine

**Mauvais** :
```php
// Dans Domain/
class MonService extends AbstractController { }
```

**Bon** :
```php
// Domain/ : classes PHP pures
// Infrastructure/Http/Controller/ : hérite d'AbstractController
```

### 2. Utiliser les Value Objects

```php
final class Email
{
    private function __construct(private string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
```

### 3. Exceptions métier

```php
namespace App\Domain\Exception;

abstract class BusinessException extends \Exception
{
}

class HabitantNotFoundException extends BusinessException
{
    public static function withId(int $id): self
    {
        return new self("Habitant avec l'ID {$id} introuvable");
    }
}
```

### 4. DTOs pour la présentation

```php
final class HabitantRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    public string $nom;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    public function toDomain(): Habitant
    {
        return new Habitant(
            $this->nom,
            $this->prenom,
            new \DateTimeImmutable(),
            new Age($this->age),
            new Email($this->email)
        );
    }
}
```

## Récapitulatif : Générer un module complet en quelques commandes

**🚀 Script rapide avec le bundle :**
```bash
# MODULE COMPLET en 7 commandes !

# 1. Créer l'entité du domaine avec repository et ID
bin/console make:hexagonal:entity cadeau/attribution Cadeau --with-repository --with-id-vo

# 2. Créer les Value Objects
bin/console make:hexagonal:value-object cadeau/attribution Montant
bin/console make:hexagonal:value-object cadeau/attribution Description

# 3. Créer les exceptions métier
bin/console make:hexagonal:exception cadeau/attribution CadeauIntrouvableException

# 4. Créer le use case avec sa commande
bin/console make:hexagonal:command cadeau/attribution attribuer --factory --with-tests

# 5. Créer la query de lecture
bin/console make:hexagonal:query cadeau/attribution lister-cadeaux

# 6. Créer le contrôleur web avec workflow complet
bin/console make:hexagonal:controller cadeau/attribution AttribuerCadeaux /cadeaux/attribuer --with-workflow

# 7. Créer la commande CLI
bin/console make:hexagonal:cli-command cadeau/attribution AttribuerCadeaux app:cadeaux:attribuer
```

**🎯 Résultat : Architecture hexagonale complète en quelques minutes !**

**⚡ Encore plus rapide avec CRUD :**
```bash
# Générer TOUT en une seule commande !
bin/console make:hexagonal:crud blog/post Post --route-prefix=/posts --with-tests --with-id-vo

# Cela génère automatiquement :
# - Entité Domain + Mapping Doctrine
# - Repository (Port + Adaptateur)
# - 5 Use Cases (Create, Update, Delete, Show, List)
# - 5 Contrôleurs
# - 1 Formulaire
# - Tous les tests
```

## Structure finale (CQRS)

```
src/
├── Domain/                           # ❌ Aucun import Symfony/Doctrine
│   ├── Entity/
│   │   ├── Habitant.php
│   │   ├── Cadeau.php
│   │   └── TrancheAge.php
│   ├── ValueObject/
│   │   ├── Age.php
│   │   └── Email.php
│   ├── Port/                         # ← Ports secondaires (Out) uniquement
│   │   ├── HabitantRepositoryInterface.php
│   │   ├── CadeauRepositoryInterface.php
│   │   └── NotificationProviderInterface.php
│   └── Exception/
│       └── CadeauIntrouvableException.php
│
├── Application/                      # ❌ Aucun import Symfony/Doctrine
│   ├── AttribuerCadeaux/
│   │   ├── AttribuerCadeauxCommand.php        # ← Port In (Command)
│   │   ├── AttribuerCadeauxCommandHandler.php # ← Use Case
│   │   └── CadeauFactory.php
│   └── RecupererHabitants/
│       ├── RecupererHabitantsQuery.php        # ← Port In (Query)
│       ├── RecupererHabitantsQueryHandler.php # ← Use Case
│       └── RecupererHabitantsResponse.php
│
└── Infrastructure/                   # ✅ Symfony/Doctrine ici uniquement
    ├── Doctrine/
    │   ├── Entity/
    │   │   └── HabitantEntity.php
    │   ├── Repository/
    │   │   └── DoctrineHabitantRepository.php
    │   └── Mapper/
    │       └── HabitantMapper.php
    ├── Filesystem/
    │   └── CadeauFileReader.php
    ├── Mail/
    │   └── SymfonyMailerAdapter.php
    ├── Http/
    │   ├── Controller/
    │   │   └── CadeauController.php
    │   └── DTO/
    │       └── HabitantResponse.php
    └── Console/
        └── AttribuerCadeauxCommand.php
```

## Commandes utiles

### Commandes du bundle (génération de code)

```bash
# Entités et domaine
bin/console make:hexagonal:entity [module] [Entity] [--with-repository] [--with-id-vo]
bin/console make:hexagonal:value-object [module] [ValueObject]
bin/console make:hexagonal:exception [module] [Exception]

# Application (CQRS)
bin/console make:hexagonal:command [module] [command-name] [--factory] [--with-tests]
bin/console make:hexagonal:query [module] [query-name]
bin/console make:hexagonal:use-case [module] [UseCase] [--with-test]
bin/console make:hexagonal:input [module] [Input]

# Repository
bin/console make:hexagonal:repository [module] [Entity]

# UI (Adaptateurs primaires)
bin/console make:hexagonal:controller [module] [Action] [route] [--with-workflow]
bin/console make:hexagonal:form [module] [Form] [--with-command] [--action=Create]
bin/console make:hexagonal:cli-command [module] [Command] [command-name] [--with-use-case]

# Tests
bin/console make:hexagonal:use-case-test [module] [UseCase]
bin/console make:hexagonal:controller-test [module] [Controller] [route]
bin/console make:hexagonal:cli-command-test [module] [Command] [command-name]
bin/console make:hexagonal:test-config

# Events
bin/console make:hexagonal:domain-event [module] [Event] [--with-subscriber]
bin/console make:hexagonal:event-subscriber [module] [Subscriber] [--layer=application|infrastructure]

# Infrastructure (Messages asynchrones)
bin/console make:hexagonal:message-handler [module] [Handler] [--with-message]

# CRUD complet (20+ fichiers en une commande !)
bin/console make:hexagonal:crud [module] [Entity] [--route-prefix=/path] [--with-tests] [--with-id-vo]
```

### Commandes Symfony standard

```bash
# Créer la base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Lancer l'attribution via CLI (commande générée)
php bin/console app:attribuer-cadeaux

# Tester l'API
curl -X POST http://localhost:8000/api/cadeaux/attribuer

# Tests
./vendor/bin/phpunit tests/Domain          # Rapides (ms)
./vendor/bin/phpunit tests/Application     # Rapides (ms)
./vendor/bin/phpunit tests/Infrastructure  # Plus lents (secondes)

# Validation architecture
vendor/bin/deptrac analyze
```

## Conclusion

L'architecture hexagonale avec Symfony permet de :

- **Protéger le métier** des changements de framework
- **Tester facilement** sans démarrer Symfony
- **Évoluer sereinement** en ajoutant des adaptateurs
- **Remplacer Doctrine** sans réécrire le métier
- **Démontrer tôt** avec des adaptateurs en mémoire
- **Maintenir longtemps** grâce à la séparation des responsabilités

### Le secret : inverser les dépendances

```
❌ Avant : Métier → Doctrine → Base de données
✅ Après : Métier ← DoctrineAdapter → Base de données
```

Le métier définit ses besoins (interfaces), l'infrastructure s'adapte !
