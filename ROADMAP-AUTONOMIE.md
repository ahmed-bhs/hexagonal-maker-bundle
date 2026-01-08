# Roadmap - Package Autonome

## 🎯 Objectif
Rendre le bundle complètement autonome : un utilisateur doit pouvoir générer une feature complète sans aucune configuration manuelle.

## ✅ Déjà implémenté

- ✅ Génération des entités Domain avec factory methods
- ✅ Génération des ValueObjects
- ✅ Génération des Ports (interfaces de repository)
- ✅ Génération des Adapters (implémentations Doctrine)
- ✅ Génération des Commands/Queries
- ✅ Génération des Handlers (avec smart templates)
- ✅ Génération des Controllers
- ✅ Génération des Templates Twig
- ✅ Auto-détection des properties uniques → génération findByX(), existsByX()

## 🚧 À implémenter (par priorité)

### PRIORITÉ 1 - Génération automatique de configuration

#### 1.1 Mappings Doctrine (XML)
**Problème**: Les fichiers `.orm.xml` ne sont pas générés, juste des `.orm.yml` avec des TODOs

**Solution**:
```php
// Dans MakeEntity.php
- Détecter si Doctrine ORM 3.x est installé
- Si oui, générer des fichiers .orm.xml au lieu de .yml
- Générer le mapping complet basé sur les properties:
  * Types personnalisés pour les ValueObjects (habitant_id, age, email_vo)
  * Gestion des relations (oneToMany, manyToOne, etc.)
  * Contraintes (unique, nullable, length)
```

**Template à créer**: `config/skeleton/src/Module/Infrastructure/Persistence/Doctrine/Orm/Mapping/Entity.orm.xml.tpl.php`

#### 1.2 Types Doctrine personnalisés
**Problème**: Les types personnalisés (HabitantIdType, AgeType, etc.) doivent être créés manuellement

**Solution**:
```php
// Commande: make:hexagonal:doctrine-type
- Générer automatiquement un type Doctrine pour chaque ValueObject
- Ajouter automatiquement dans config/packages/doctrine.yaml
- Vérifier l'existence avant d'ajouter (éviter duplicatas)
```

**Templates à créer**:
- `config/skeleton/src/Module/Infrastructure/Persistence/Doctrine/Type/ValueObjectType.tpl.php`

#### 1.3 Configuration Doctrine
**Problème**: Le mapping YAML/XML doit être ajouté manuellement dans `doctrine.yaml`

**Solution**:
```php
// Dans chaque commande make:hexagonal:*
- Vérifier si config/packages/doctrine.yaml existe
- Ajouter automatiquement le mapping pour le module si absent:
  CadeauAttribution:
      type: xml
      dir: '%kernel.project_dir%/src/Cadeau/Attribution/Infrastructure/...'
      prefix: 'App\Cadeau\Attribution\Domain\Model'
```

**Classe à créer**: `src/Config/DoctrineConfigUpdater.php`

#### 1.4 Configuration Messenger (CQRS)
**Problème**: Les bus doivent être configurés manuellement

**Solution**:
```php
// À la première commande make:hexagonal:command ou make:hexagonal:query
- Vérifier si messenger.yaml contient command.bus et query.bus
- Si non, ajouter automatiquement:
  buses:
      command.bus:
          middleware: [validation, doctrine_transaction]
      query.bus:
          middleware: [validation]
```

**Classe à créer**: `src/Config/MessengerConfigUpdater.php`

#### 1.5 Configuration Services
**Problème**: Les bindings de repositories doivent être ajoutés manuellement

**Solution**:
```php
// Dans MakeRepository.php
- Ajouter automatiquement le binding dans services.yaml:
  App\Module\Domain\Port\XRepositoryInterface:
      class: App\Module\Infrastructure\Persistence\Doctrine\DoctrineXRepository
- Vérifier l'existence avant d'ajouter
```

**Classe à créer**: `src/Config/ServicesConfigUpdater.php`

#### 1.6 Configuration Routes
**Problème**: Les contrôleurs hexagonaux ne sont pas découverts automatiquement

**Solution**:
```php
// Dans MakeController.php
- Vérifier si routes.yaml contient le path vers le module
- Si non, ajouter automatiquement:
  module_controllers:
      resource:
          path: ../src/Module/UI/Http/Web/Controller/
          namespace: App\Module\UI\Http\Web\Controller
      type: attribute
```

**Classe à créer**: `src/Config/RoutesConfigUpdater.php`

### PRIORITÉ 2 - Commandes de maintenance

#### 2.1 Commande de mise à jour
```bash
php bin/console make:hexagonal:update
```
- Compare les templates du bundle avec les fichiers générés
- Propose de mettre à jour les fichiers obsolètes
- Affiche un diff avant application
- Permet de sélectionner quels fichiers mettre à jour

#### 2.2 Commande d'audit
```bash
php bin/console make:hexagonal:audit
```
- Vérifie que tous les fichiers générés sont à jour
- Vérifie que les configurations sont complètes
- Liste les améliorations manquantes (findAll, etc.)
- Propose des corrections

#### 2.3 Commande de diagnostic
```bash
php bin/console make:hexagonal:doctor
```
- Vérifie que Doctrine ORM est bien configuré
- Vérifie que Messenger est bien configuré
- Vérifie que les mappings XML/YAML existent
- Vérifie que les types personnalisés sont enregistrés
- Propose des fixes automatiques

### PRIORITÉ 3 - Smart Generation

#### 3.1 Détection automatique des ValueObjects
**Problème**: L'utilisateur doit spécifier manuellement les ValueObjects

**Solution**:
```php
// Dans PropertyConfigurationParser
- Détecter automatiquement certains patterns:
  * email → EmailType + Email ValueObject
  * phone/telephone → PhoneType + Phone ValueObject
  * id/uuid → UuidType + Uuid ValueObject
  * price/amount → MoneyType + Money ValueObject
- Générer automatiquement le ValueObject et le Type si absent
```

#### 3.2 Génération de fixtures
```bash
php bin/console make:hexagonal:fixtures Module Entity
```
- Générer automatiquement une classe DoctrineFixtures
- Utiliser les factory methods create() et reconstitute()
- Générer des données réalistes avec Faker

#### 3.3 Génération de tests
```bash
php bin/console make:hexagonal:test Module UseCase
```
- Générer automatiquement les tests unitaires
- Générer les tests d'intégration (repository, handler)
- Générer les tests fonctionnels (controller)

### PRIORITÉ 4 - Developer Experience

#### 4.1 Mode interactif amélioré
- Proposer des suggestions intelligentes
- Auto-complétion des modules existants
- Validation en temps réel des inputs

#### 4.2 Gestion des erreurs
- Messages d'erreur clairs et actionnables
- Suggestions de correction automatique
- Rollback en cas d'échec

#### 4.3 Documentation générée
- Générer automatiquement un README.md pour chaque module
- Documenter les use cases et leurs paramètres
- Générer des diagrammes d'architecture

## 📋 Plan d'implémentation

### Sprint 1 (2-3 jours)
1. Implémenter DoctrineConfigUpdater
2. Implémenter MessengerConfigUpdater
3. Implémenter ServicesConfigUpdater
4. Implémenter RoutesConfigUpdater
5. Mettre à jour toutes les commandes make:hexagonal:* pour utiliser ces updaters

### Sprint 2 (2-3 jours)
1. Créer le template Entity.orm.xml.tpl.php
2. Modifier MakeEntity pour générer XML au lieu de YAML si ORM 3.x
3. Créer MakeDoctrine Type pour générer les types personnalisés
4. Intégrer la génération automatique de types dans MakeValueObject

### Sprint 3 (1-2 jours)
1. Créer la commande make:hexagonal:audit
2. Créer la commande make:hexagonal:doctor
3. Créer la commande make:hexagonal:update

### Sprint 4 (2-3 jours)
1. Améliorer PropertyConfigurationParser pour détecter les ValueObjects
2. Créer MakeFixtures
3. Améliorer les templates de tests

## 🎯 Résultat attendu

Après ces implémentations, un développeur devrait pouvoir faire:

```bash
# 1. Créer un module complet
php bin/console make:hexagonal:module Cadeau Attribution

# 2. Créer une entité avec ValueObjects auto-détectés
php bin/console make:hexagonal:entity Cadeau Attribution Habitant \
  --properties="prenom:string,nom:string,age:int,email:email"
# → Génère automatiquement Email ValueObject + EmailType
# → Génère le mapping XML complet
# → Enregistre le type dans doctrine.yaml
# → Ajoute le mapping dans doctrine.yaml

# 3. Créer un use case
php bin/console make:hexagonal:command Cadeau Attribution EnregistrerHabitant \
  --properties="prenom:string,nom:string,age:int,email:email"
# → Génère Command + CommandHandler avec logique smart
# → Configure automatiquement le bus dans messenger.yaml

# 4. Créer un contrôleur
php bin/console make:hexagonal:controller Cadeau Attribution EnregistrerHabitant
# → Génère le contrôleur + template Twig
# → Configure automatiquement la route dans routes.yaml

# 5. Vérifier que tout est OK
php bin/console make:hexagonal:doctor
# ✓ Doctrine configured correctly
# ✓ Messenger configured correctly
# ✓ All mappings present
# ✓ All custom types registered
# ✓ All routes configured

# Et voilà, l'app fonctionne sans aucune config manuelle! 🎉
```
