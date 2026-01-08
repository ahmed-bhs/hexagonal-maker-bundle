# Architecture Hexagonale et Clean Architecture : Guide Pratique

## Introduction

Ce document présente les principes de l'architecture hexagonale et de la clean architecture, en illustrant comment migrer d'une architecture en couches techniques vers une architecture centrée sur le métier.

## Cas d'étude : Application de gestion de cadeaux municipaux

### Contexte
Une mairie souhaite offrir des cadeaux personnalisés aux habitants qui célèbrent leur première année dans la commune. L'application doit :

- Sélectionner les habitants éligibles (arrivés depuis plus d'un an)
- Attribuer un cadeau approprié selon l'âge
- Notifier les habitants par email
- Envoyer un récapitulatif quotidien au maire

### Sources de données
- **Base de données** : informations sur les habitants (H2, MySQL, PostgreSQL)
- **Fichiers** : catalogue de cadeaux par tranche d'âge
- **Serveur mail** : notifications aux habitants et à la mairie

### Points d'entrée
- Interface graphique pour les employés
- API REST pour les développeurs et tests
- Tâche automatique (exécution périodique)

## Problèmes de l'architecture en couches techniques (N-tiers)

### Structure classique
```
├── Controllers (Présentation)
│   ├── HabitantController
│   └── HappyTownController
├── Services (Métier)
│   ├── HabitantService
│   └── CadeauService
└── Repositories (Données)
    └── HabitantRepository
```

### Limitations identifiées

1. **Couplage fort aux frameworks**
   - Annotations de persistance dans le domaine (@Entity, @Id)
   - Annotations de validation (@NotNull, @NotBlank)
   - Annotations de présentation (@JsonProperty)

2. **Modèle unique pour toutes les couches**
   - Le même objet transite de la base de données à la présentation
   - Évolutions contraintes par le modèle de stockage
   - Perte de flexibilité

3. **Logique métier polluée par la technique**
   ```java
   public void attribuerCadeaux(String nomFichier, Date date,
                                String smtpHost, int smtpPort, ...) {
       // Configuration technique mélangée au métier
   }
   ```

4. **Tests difficiles**
   - Nécessité de démarrer un serveur mail pour tester
   - Tests aléatoires (dépendance aux ports disponibles)
   - Tests écrits après coup, pas orientés métier
   - Absence de base solide dans la pyramide de tests

## Principes de la Clean Architecture

### Philosophie

> "Votre application n'est pas définie par votre base de données ou vos frameworks, mais par vos cas d'utilisation métier"
> — Robert C. Martin (Uncle Bob)

### Organisation en cercles concentriques

```
┌─────────────────────────────────────┐
│   Infrastructure & Frameworks      │
│  ┌───────────────────────────────┐ │
│  │   Adaptateurs & Controllers   │ │
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
- Pas d'imports vers l'extérieur depuis le centre

## Architecture Hexagonale (Ports & Adaptateurs)

### Structure

```
┌──────────────────────────────────────────────┐
│            ADAPTATEURS PRIMAIRES             │
│   (Déclencheurs : UI, API, Tâches auto)     │
└──────────────────┬───────────────────────────┘
                   │
         ┌─────────▼─────────┐
         │   PORTS PRIMAIRES │
         │   (Interfaces)    │
         └─────────┬─────────┘
                   │
    ┌──────────────▼──────────────┐
    │         HEXAGONE            │
    │    (Logique Métier)         │
    │  - Entités                  │
    │  - Cas d'utilisation        │
    └──────────────┬──────────────┘
                   │
         ┌─────────▼─────────┐
         │  PORTS SECONDAIRES│
         │   (Interfaces)    │
         └─────────┬─────────┘
                   │
┌──────────────────▼───────────────────────────┐
│         ADAPTATEURS SECONDAIRES              │
│  (Fournisseurs : BDD, Fichiers, Mail)       │
└──────────────────────────────────────────────┘
```

### Concepts clés

**Ports** : Interfaces définissant les contrats
- Ports primaires (entrées) : déclenchent les cas d'utilisation
- Ports secondaires (sorties) : fournissent les données

**Adaptateurs** : Implémentations concrètes
- Adaptateurs primaires : API REST, CLI, UI, Scheduler
- Adaptateurs secondaires : Repository BDD, FileReader, MailSender

## Migration étape par étape

### Étape 1 : Nouvelle structure de packages

```
src/
├── domain/
│   ├── entities/        # Cadeau, Habitant, TrancheAge
│   └── usecases/        # AttribuerCadeaux, RecupererHabitants
├── application/
│   └── ports/
│       ├── in/          # Ports primaires (déclencheurs)
│       └── out/         # Ports secondaires (providers)
└── infrastructure/
    ├── persistence/     # Adaptateurs BDD
    ├── files/           # Adaptateurs fichiers
    ├── mail/            # Adaptateurs mail
    └── web/             # Controllers REST
```

### Étape 2 : Nettoyer les entités

**Avant** :
```java
@Entity
@Table(name = "habitants")
@Data
@AllArgsConstructor
public class Habitant {
    @Id
    @GeneratedValue
    private Long id;

    @NotNull
    @NotBlank
    private String nom;

    @JsonProperty("first_name")
    private String prenom;
}
```

**Après** :
```java
public class Habitant {
    private final String nom;
    private final String prenom;
    private final LocalDate dateArrivee;
    private final int age;

    public Habitant(String nom, String prenom,
                    LocalDate dateArrivee, int age) {
        this.nom = nom;
        this.prenom = prenom;
        this.dateArrivee = dateArrivee;
        this.age = age;
    }

    public boolean estEligible() {
        return ChronoUnit.YEARS.between(dateArrivee, LocalDate.now()) >= 1;
    }
}
```

### Étape 3 : Définir les ports (interfaces)

**Port secondaire (provider)** :
```java
public interface HabitantProvider {
    List<Habitant> recupererHabitants();
}
```

**Port primaire (use case)** :
```java
public interface AttribuerCadeauxUseCase {
    void attribuer();
}
```

### Étape 4 : Créer des modèles dédiés par couche

**Modèle de persistance** :
```java
@Entity
@Table(name = "habitants")
class HabitantJpaEntity {
    @Id
    @GeneratedValue
    private Long id;

    @Column(nullable = false)
    private String nom;
    // ... annotations JPA uniquement
}
```

**Modèle de présentation** :
```java
public class HabitantDto {
    @JsonProperty("last_name")
    private String nom;

    @JsonProperty("first_name")
    private String prenom;
    // ... annotations JSON uniquement
}
```

### Étape 5 : Implémenter les adaptateurs

**Adaptateur de persistance** :
```java
@Component
public class HabitantJpaAdapter implements HabitantProvider {
    private final HabitantJpaRepository repository;

    @Override
    public List<Habitant> recupererHabitants() {
        return repository.findAll()
            .stream()
            .map(this::toDomain)
            .collect(Collectors.toList());
    }

    private Habitant toDomain(HabitantJpaEntity entity) {
        return new Habitant(
            entity.getNom(),
            entity.getPrenom(),
            entity.getDateArrivee(),
            entity.getAge()
        );
    }
}
```

### Étape 6 : Implémenter le cas d'utilisation

```java
public class AttribuerCadeauxService implements AttribuerCadeauxUseCase {
    private final HabitantProvider habitantProvider;
    private final CadeauProvider cadeauProvider;
    private final NotificationProvider notificationProvider;

    @Override
    public void attribuer() {
        List<Habitant> habitants = habitantProvider.recupererHabitants();

        List<Habitant> eligibles = habitants.stream()
            .filter(Habitant::estEligible)
            .collect(Collectors.toList());

        for (Habitant habitant : eligibles) {
            Cadeau cadeau = cadeauProvider
                .trouverCadeauAleatoire(habitant.getAge());

            notificationProvider.notifier(habitant, cadeau);
        }
    }
}
```

### Étape 7 : Isoler la configuration technique

```java
@Component
public class MailNotificationAdapter implements NotificationProvider {
    private final String smtpHost;
    private final int smtpPort;
    private final MailSender mailSender;

    @Autowired
    public MailNotificationAdapter(
            @Value("${mail.smtp.host}") String smtpHost,
            @Value("${mail.smtp.port}") int smtpPort) {
        this.smtpHost = smtpHost;
        this.smtpPort = smtpPort;
        this.mailSender = new MailSender(smtpHost, smtpPort);
    }

    @Override
    public void notifier(Habitant habitant, Cadeau cadeau) {
        mailSender.send(habitant.getEmail(),
                       construireMessage(habitant, cadeau));
    }
}
```

## Avantages de l'architecture hexagonale

### 1. Tests simplifiés

**Tests unitaires du métier (sans frameworks)** :
```java
@Test
void devrait_attribuer_cadeau_aux_habitants_eligibles() {
    // Given
    HabitantProvider habitantProvider = mock(HabitantProvider.class);
    CadeauProvider cadeauProvider = mock(CadeauProvider.class);
    NotificationProvider notificationProvider = mock(NotificationProvider.class);

    when(habitantProvider.recupererHabitants())
        .thenReturn(List.of(
            new Habitant("Dupont", "Jean",
                        LocalDate.now().minusYears(2), 5)
        ));

    AttribuerCadeauxUseCase useCase = new AttribuerCadeauxService(
        habitantProvider, cadeauProvider, notificationProvider
    );

    // When
    useCase.attribuer();

    // Then
    verify(notificationProvider, times(1)).notifier(any(), any());
}
```

**Pyramide de tests équilibrée** :
```
        /\
       /UI\         Tests end-to-end (peu nombreux)
      /────\
     /Intég.\       Tests d'intégration (moyennement nombreux)
    /────────\
   / Unitaire \     Tests unitaires (très nombreux, rapides)
  /────────────\
```

### 2. Évolutivité

- **Changement de base de données** : seul l'adaptateur change
- **Ajout d'un canal de notification** : nouveau provider (SMS, Push)
- **Nouveau point d'entrée** : nouvel adaptateur primaire (GraphQL, gRPC)

### 3. Maintenabilité

- Code métier lisible et compréhensible par le métier
- Isolation des problèmes techniques
- Mise à jour des frameworks sans impact sur le métier
- Refactoring facilité

### 4. Flexibilité

- Développement possible sans infrastructure complète
- Démonstrations précoces avec des adaptateurs en mémoire
- Tests indépendants de l'infrastructure

### 5. Gestion des exceptions

**Exceptions métier** :
```java
public class CadeauIntrouvableException extends BusinessException {
    public CadeauIntrouvableException(int age) {
        super("Aucun cadeau disponible pour l'âge : " + age);
    }
}
```

Les exceptions techniques (IOException, SQLException) sont transformées en exceptions métier dans les adaptateurs.

## Bonnes pratiques

### 1. Isoler le métier
- Aucune annotation de framework dans le domaine
- Aucun import technique dans les entités et cas d'utilisation
- Code en langage pur (Java, TypeScript, etc.)

### 2. Respecter la règle de dépendance
- Vérifier les imports : le domaine ne doit jamais importer l'infrastructure
- Utiliser des outils d'analyse statique (ArchUnit, Dependency Cruiser)

### 3. Penser use case
- Un cas d'utilisation = une classe dédiée
- Noms explicites : `AttribuerCadeaux`, `RecupererHabitantsEligibles`
- Pas de paramètres techniques dans les signatures

### 4. Séparer les modèles
- Modèle métier (domain)
- Modèle de persistance (infrastructure/persistence)
- Modèle de présentation (infrastructure/web)
- Utiliser des mappers pour les conversions

### 5. Tests orientés métier
- Privilégier les tests unitaires sur le domaine
- Mocker les ports pour tester les cas d'utilisation
- Tests d'intégration uniquement pour les adaptateurs

### 6. Ne pas sur-ingénierie
- Ajouter seulement ce qui est nécessaire
- Pas d'abstractions prématurées
- Simplicité avant tout

## Anti-patterns à éviter

1. **Mélanger les responsabilités**
   - Configuration technique dans les cas d'utilisation
   - Logique métier dans les contrôleurs

2. **Dépendances inversées**
   - Le domaine qui importe l'infrastructure
   - Annotations techniques dans les entités métier

3. **Modèle anémique**
   - Objets sans comportement (getters/setters uniquement)
   - Logique métier dispersée dans les services

4. **Tests couplés à l'infrastructure**
   - Démarrage de serveurs pour tester le métier
   - Tests dépendants de l'état de la base de données

## Outillage

### Validation de l'architecture
```java
@Test
void domain_ne_doit_pas_dependre_de_infrastructure() {
    noClasses()
        .that().resideInAPackage("..domain..")
        .should().dependOnClassesThat()
        .resideInAPackage("..infrastructure..")
        .check(importedClasses);
}
```

### BDD pour les cas d'utilisation
```gherkin
Feature: Attribution de cadeaux

  Scenario: Habitant éligible reçoit un cadeau adapté
    Given un habitant de 5 ans arrivé il y a 2 ans
    When on déclenche l'attribution des cadeaux
    Then l'habitant reçoit un cadeau pour la tranche 3-10 ans
    And une notification est envoyée
```

## Conclusion

L'architecture hexagonale et la clean architecture ne sont pas des dogmes mais des guides pour :

- **Protéger la valeur métier** de la volatilité technique
- **Faciliter les tests** en isolant les dépendances
- **Améliorer la maintenabilité** en séparant les responsabilités
- **Accélérer les évolutions** en réduisant le couplage

Le temps investi au départ pour structurer correctement l'application est largement rentabilisé lors de la maintenance et des évolutions.

### Signes d'alerte d'une mauvaise architecture

- "C'est compliqué à tester"
- "On ne peut pas tester unitairement"
- "Les tests sont aléatoires"
- "Il y a trop de régressions"
- "On ne peut pas mettre à jour les frameworks"

### Bénéfices d'une bonne architecture

- Tests rapides et fiables
- Évolutions sans régression
- Code compréhensible par le métier
- Mise à jour des dépendances sans stress
- Démonstrations précoces possibles

---

**Ressource recommandée** : Blog de Robert C. Martin (Uncle Bob)
**Principe clé** : *"Votre application est définie par ce qu'elle fait, pas par comment elle le fait"*
