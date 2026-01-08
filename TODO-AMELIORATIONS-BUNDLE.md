# 🎯 TODO: Améliorations à Implémenter dans le Bundle

**Objectif**: Que les commandes du bundle génèrent **100% du code fonctionnel** au lieu de 60%

---

## 📋 Priorités d'Implémentation

### ⭐⭐⭐ PRIORITÉ HAUTE (Implémenter en premier)

#### 1. Auto-génération des méthodes Repository basées sur les propriétés

**Problème actuel**:
```bash
php bin/console make:hexagonal:entity cadeau/attribution Cadeau \
  --properties="nom:string(3,100):unique,quantite:int(0,1000)"
```

Génère un repository avec seulement 3 méthodes:
```php
interface CadeauRepositoryInterface {
    public function save(Cadeau $cadeau): void;
    public function findById(string $id): ?Cadeau;
    public function delete(Cadeau $cadeau): void;
}
```

**Solution à implémenter**:

Détecter les propriétés `unique` et générer automatiquement:
```php
interface CadeauRepositoryInterface {
    public function save(Cadeau $cadeau): void;
    public function findById(string $id): ?Cadeau;
    public function delete(Cadeau $cadeau): void;

    // ✅ AUTO-GÉNÉRÉ depuis --properties
    /**
     * @return Cadeau[]
     */
    public function findAll(): array;

    // ✅ AUTO-GÉNÉRÉ car nom:unique
    public function findByNom(string $nom): ?Cadeau;
    public function existsByNom(string $nom): bool;
}
```

**Implémentation dans le bundle**:

1. **Fichier**: `src/Generator/HexagonalGenerator.php`
   - Méthode: `generateRepository()`
   - Extraire les propriétés depuis `PropertyConfig`
   - Filtrer les propriétés avec `unique` option
   - Passer au template

2. **Fichier**: `config/skeleton/src/Module/Domain/Port/RepositoryInterface.tpl.php`
   ```php
   <?php if (!empty($unique_properties)): ?>

   <?php foreach ($unique_properties as $prop): ?>
       public function findBy<?= ucfirst($prop['name']) ?>(<?= $prop['phpType'] ?> $<?= $prop['name'] ?>): ?<?= $entity_name ?>;
       public function existsBy<?= ucfirst($prop['name']) ?>(<?= $prop['phpType'] ?> $<?= $prop['name'] ?>): bool;
   <?php endforeach; ?>
   <?php endif; ?>
   ```

3. **Fichier**: `config/skeleton/src/Module/Infrastructure/Persistence/Doctrine/DoctrineRepository.tpl.php`
   ```php
   <?php if (!empty($unique_properties)): ?>

   <?php foreach ($unique_properties as $prop): ?>
       public function findBy<?= ucfirst($prop['name']) ?>(<?= $prop['phpType'] ?> $<?= $prop['name'] ?>): ?<?= $entity_name ?>
       {
           return $this->entityManager->getRepository(<?= $entity_name ?>::class)
               ->findOneBy(['<?= $prop['name'] ?>' => $<?= $prop['name'] ?>]);
       }

       public function existsBy<?= ucfirst($prop['name']) ?>(<?= $prop['phpType'] ?> $<?= $prop['name'] ?>): bool
       {
           return $this->findBy<?= ucfirst($prop['name']) ?>($<?= $prop['name'] ?>) !== null;
       }
   <?php endforeach; ?>
   <?php endif; ?>
   ```

**Impact**: Repository 100% fonctionnel dès la génération

---

#### 2. Template CommandHandler intelligent basé sur le pattern du nom

**Problème actuel**:
```bash
php bin/console make:hexagonal:command cadeau/attribution AttribuerCadeaux \
  --properties="habitantId:string,cadeauId:string"
```

Génère un Handler vide:
```php
public function __invoke(AttribuerCadeauxCommand $command): void {
    // TODO: Implement your business logic here
}
```

**Solution à implémenter**:

Détecter le pattern et générer l'implémentation:

**Patterns supportés**:
- `Create*` → Créer une entité
- `Update*` → Mettre à jour une entité
- `Delete*` → Supprimer une entité
- `Attribuer*` / `Assign*` → Créer une relation
- `Activate*` / `Deactivate*` → Changer un statut

**Exemple pour `AttribuerCadeaux`**:
```php
#[AsMessageHandler]
final readonly class AttribuerCadeauxCommandHandler
{
    public function __construct(
        private HabitantRepositoryInterface $habitantRepository,
        private CadeauRepositoryInterface $cadeauRepository,
        private AttributionRepositoryInterface $attributionRepository,
    ) {
    }

    public function __invoke(AttribuerCadeauxCommand $command): void
    {
        // ✅ AUTO-GÉNÉRÉ: Validation habitant
        $habitant = $this->habitantRepository->findById($command->habitantId);
        if (!$habitant) {
            throw new \InvalidArgumentException(
                sprintf('Habitant with ID "%s" not found', $command->habitantId)
            );
        }

        // ✅ AUTO-GÉNÉRÉ: Validation cadeau
        $cadeau = $this->cadeauRepository->findById($command->cadeauId);
        if (!$cadeau) {
            throw new \InvalidArgumentException(
                sprintf('Cadeau with ID "%s" not found', $command->cadeauId)
            );
        }

        // ✅ AUTO-GÉNÉRÉ: Création attribution
        $attribution = Attribution::create(
            $command->habitantId,
            $command->cadeauId
        );

        // ✅ AUTO-GÉNÉRÉ: Persistance
        $this->attributionRepository->save($attribution);
    }
}
```

**Implémentation dans le bundle**:

1. **Nouvelle classe**: `src/Analyzer/CommandPatternAnalyzer.php`
   ```php
   final class CommandPatternAnalyzer
   {
       public function detectPattern(string $commandName): CommandPattern
       {
           if (str_starts_with($commandName, 'Create')) {
               return CommandPattern::CREATE;
           }
           if (str_starts_with($commandName, 'Attribuer') || str_starts_with($commandName, 'Assign')) {
               return CommandPattern::CREATE_RELATION;
           }
           // ...
           return CommandPattern::CUSTOM;
       }

       public function inferEntitiesFromPattern(string $commandName, CommandPattern $pattern): array
       {
           // Attribuer + Cadeaux → [Habitant, Cadeau, Attribution]
           // Create + User → [User]
       }
   }
   ```

2. **Fichier**: `src/Maker/MakeCommand.php`
   - Ajouter option `--entities="Habitant,Cadeau,Attribution"`
   - Utiliser `CommandPatternAnalyzer` si pas fourni

3. **Nouveau template**: `config/skeleton/src/Module/Application/Command/CommandHandlerWithPattern.tpl.php`

**Impact**: CommandHandler 80% fonctionnel dès la génération

---

#### 3. Template QueryResponse intelligent basé sur l'entité

**Problème actuel**:
```bash
php bin/console make:hexagonal:query cadeau/attribution RecupererHabitants
```

Génère une Response vide:
```php
final readonly class RecupererHabitantsResponse {
    public function __construct(
        // TODO: Add your response properties here
    ) {}
}
```

**Solution à implémenter**:

Option `--entity` pour générer automatiquement:
```bash
php bin/console make:hexagonal:query cadeau/attribution RecupererHabitants \
  --entity="Habitant" \
  --collection
```

Génère:
```php
final readonly class RecupererHabitantsResponse
{
    /**
     * @param Habitant[] $habitants
     */
    public function __construct(
        public array $habitants,
    ) {
    }

    /**
     * @return array<int, array{id: string, prenom: string, nom: string, age: int, email: string}>
     */
    public function toArray(): array
    {
        return array_map(
            fn(Habitant $h) => [
                'id' => $h->getId()->toString(),
                'prenom' => $h->getPrenom(),
                'nom' => $h->getNom(),
                'age' => $h->getAge()->value,
                'email' => $h->getEmail()->value,
            ],
            $this->habitants
        );
    }
}
```

**Implémentation dans le bundle**:

1. **Fichier**: `src/Maker/MakeQuery.php`
   - Ajouter option `--entity=EntityName`
   - Ajouter option `--collection` (vs single entity)
   - Analyser l'entité pour extraire les getters

2. **Nouvelle classe**: `src/Analyzer/EntityAnalyzer.php`
   ```php
   final class EntityAnalyzer
   {
       public function extractGetters(string $entityPath): array
       {
           // Parse Entity.php
           // Extraire tous les getters publics
           // Détecter les ValueObjects (->value)
           // Retourner: ['id' => 'getId()->toString()', 'age' => 'getAge()->value']
       }
   }
   ```

3. **Nouveau template**: `config/skeleton/src/Module/Application/Query/ResponseWithEntity.tpl.php`

**Impact**: QueryResponse 100% fonctionnel dès la génération

---

### ⭐⭐ PRIORITÉ MOYENNE (Implémenter ensuite)

#### 4. Génération de factory methods dans les entités

**Implémentation**:

Option `--with-factory` par défaut pour les entités:
```bash
php bin/console make:hexagonal:entity cadeau/attribution Cadeau \
  --with-factory  # Déjà supporté, mais pas par défaut
```

**Modifier**:
- Fichier: `src/Maker/MakeEntity.php`
- Rendre `--with-factory` à `true` par défaut
- Template: `Entity.tpl.php` devrait générer `create()` et `reconstitute()` automatiquement

---

#### 5. Auto-génération de méthodes métier basiques

**Solution**:

Pour les entités avec quantité/stock:
```bash
php bin/console make:hexagonal:entity cadeau/attribution Cadeau \
  --properties="quantite:int(0,1000)" \
  --with-business-methods
```

Auto-générer:
```php
public function augmenterStock(int $quantite): void { ... }
public function diminuerStock(int $quantite): void { ... }
public function isEnStock(): bool { return $this->quantite > 0; }
```

**Patterns détectés**:
- `quantite`, `stock` → méthodes de gestion de stock
- `actif`, `active`, `enabled` → méthodes activate/deactivate
- `statut`, `status` → méthodes de transition d'état

---

#### 6. Form Type auto-généré depuis Input DTO

**Problème actuel**:
```bash
php bin/console make:hexagonal:form cadeau/attribution Cadeau
```

Génère un form vide.

**Solution**:
```bash
php bin/console make:hexagonal:form cadeau/attribution CreateCadeau \
  --from-input="CreateCadeauInput"
```

Lire l'Input DTO et générer les champs automatiquement:
```php
$builder
    ->add('nom', TextType::class, [
        'label' => 'Nom',
        'attr' => ['maxlength' => 100],
    ])
    ->add('quantite', IntegerType::class, [
        'label' => 'Quantité',
        'attr' => ['min' => 0, 'max' => 1000],
    ])
    // ...
```

---

#### 7. Tests auto-générés avec données réalistes

**Problème actuel**:
```php
public function testHandlerExecutesSuccessfully(): void
{
    $this->assertTrue(true); // TODO
}
```

**Solution**:

Générer des tests avec vraies données basées sur les propriétés:
```php
public function testHandlerCreatesAttribution(): void
{
    // Given
    $habitantId = Uuid::v4()->toRfc4122();
    $cadeauId = Uuid::v4()->toRfc4122();

    $habitant = $this->createMock(Habitant::class);
    $cadeau = $this->createMock(Cadeau::class);

    $habitantRepository = $this->createMock(HabitantRepositoryInterface::class);
    $habitantRepository->expects($this->once())
        ->method('findById')
        ->with($habitantId)
        ->willReturn($habitant);

    // When & Then...
}
```

---

### ⭐ PRIORITÉ BASSE (Nice to have)

#### 8. Commande `make:hexagonal:module` - Tout générer en une commande

```bash
php bin/console make:hexagonal:module cadeau/attribution Cadeau \
  --properties="nom:string(3,100):unique,quantite:int(0,1000)" \
  --type=crud
```

Génère:
- Entity + Repository + ValueObject (ID)
- 5 Commands: Create, Update, Delete, Activate, Deactivate
- 2 Queries: FindById, FindAll
- Controller + Form
- Tests

**= Module complet en 1 commande**

---

## 🎯 Feuille de Route d'Implémentation

### Phase 1 (Semaine 1) - Quick Wins
- [ ] Auto-génération méthodes Repository (#1)
- [ ] Factory methods par défaut dans Entity (#4)

### Phase 2 (Semaine 2) - Patterns Intelligents
- [ ] CommandHandler intelligent (#2)
- [ ] QueryResponse intelligent (#3)

### Phase 3 (Semaine 3) - Compléments
- [ ] Méthodes métier auto-générées (#5)
- [ ] Form Type auto-généré (#6)

### Phase 4 (Semaine 4) - Finalisation
- [ ] Tests avec vraies données (#7)
- [ ] Commande module complète (#8)

---

## 📊 Impact Estimé

| Phase | Amélioration | % Code Généré | Temps Économisé |
|-------|-------------|---------------|-----------------|
| Actuel | Baseline | 60% | 68% |
| Phase 1 | Repositories + Factories | 70% | 75% |
| Phase 2 | Handlers intelligents | 85% | 85% |
| Phase 3 | Business methods + Forms | 90% | 90% |
| Phase 4 | Tests + Module | 95% | 95% |

**Objectif final**: Générer **95% du code fonctionnel** directement depuis les commandes

---

## 🔧 Fichiers à Modifier

### Core Generator
- `src/Generator/HexagonalGenerator.php` - Logique de génération principale
- `src/Generator/PropertyConfig.php` - Parser de propriétés (déjà fait ✅)

### Makers
- `src/Maker/MakeEntity.php` - Factory methods par défaut
- `src/Maker/MakeCommand.php` - Pattern detection
- `src/Maker/MakeQuery.php` - Entity-based responses
- `src/Maker/MakeRepository.php` - Auto-generate methods
- `src/Maker/MakeForm.php` - Input DTO parsing

### New Analyzers
- `src/Analyzer/CommandPatternAnalyzer.php` (nouveau)
- `src/Analyzer/EntityAnalyzer.php` (nouveau)
- `src/Analyzer/PropertyPatternAnalyzer.php` (nouveau)

### Templates
- `config/skeleton/src/Module/Application/Command/CommandHandlerWithPattern.tpl.php` (nouveau)
- `config/skeleton/src/Module/Application/Query/ResponseWithEntity.tpl.php` (nouveau)
- `config/skeleton/src/Module/Domain/Port/RepositoryInterface.tpl.php` (modifier)
- `config/skeleton/src/Module/Infrastructure/Persistence/Doctrine/DoctrineRepository.tpl.php` (modifier)

---

## 📚 Références

- [AMELIORATIONS.md](/home/ahmed/Projets/hexagonal-maker-bundle/AMELIORATIONS.md) - Améliorations détaillées
- [hexagonal-demo](/home/ahmed/Projets/hexagonal-demo) - Projet de démonstration
- Exemple concret: AttribuerCadeaux CommandHandler

---

**Auteur**: Claude + Ahmed
**Date**: 2026-01-08
**Version cible**: 2.0.0
