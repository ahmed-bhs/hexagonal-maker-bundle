# 🎯 Recommandations pour rendre le package autonome

## Problème actuel

Le package génère les fichiers PHP correctement, **MAIS** l'utilisateur doit encore:
1. ✋ Configurer manuellement `doctrine.yaml` (mappings + types)
2. ✋ Configurer manuellement `messenger.yaml` (buses CQRS)
3. ✋ Configurer manuellement `services.yaml` (bindings repositories)
4. ✋ Configurer manuellement `routes.yaml` (découverte des contrôleurs)
5. ✋ Installer manuellement des packages (`symfony/uid`, `doctrine/fixtures`, etc.)

## Solution recommandée

### 🥇 APPROCHE #1: Auto-configuration intelligente (RECOMMANDÉE)

**Principe**: Le bundle détecte et configure automatiquement tout ce qui manque.

```php
// Chaque commande make:hexagonal:* vérifie et configure automatiquement

make:hexagonal:entity
  → Génère l'entité
  → Génère le mapping XML (au lieu de YML avec TODO)
  → Génère les types Doctrine pour les ValueObjects
  → Ajoute automatiquement dans doctrine.yaml (si absent)
  → Ajoute exclusion Domain/Model dans services.yaml

make:hexagonal:command
  → Génère Command + Handler
  → Configure automatiquement command.bus dans messenger.yaml (si absent)

make:hexagonal:controller
  → Génère Controller + Template
  → Configure automatiquement la route dans routes.yaml (si absent)

make:hexagonal:repository
  → Génère Port + Adapter
  → Ajoute automatiquement le binding dans services.yaml (si absent)
```

**Avantages**:
- ✅ Zéro config manuelle
- ✅ L'utilisateur ne peut pas oublier une étape
- ✅ Expérience développeur optimale
- ✅ Le package devient vraiment "autonomous"

**Inconvénients**:
- ⚠️ Nécessite de parser/modifier les fichiers YAML
- ⚠️ Risque de casser le fichier si mal codé (d'où les backups)

**Implémentation**: Voir `ROADMAP-AUTONOMIE.md` Sprint 1

---

### 🥈 APPROCHE #2: Commande d'init + détection

**Principe**: Une commande unique configure tout au début.

```bash
# Une seule fois au début du projet
php bin/console make:hexagonal:init

# Configure automatiquement:
# - doctrine.yaml (structure de base)
# - messenger.yaml (command.bus + query.bus)
# - services.yaml (exclusions Domain)
# - routes.yaml (pattern de découverte)
```

Ensuite, chaque commande `make:hexagonal:*` vérifie si la config existe et avertit si manquante.

**Avantages**:
- ✅ Plus simple à implémenter
- ✅ Moins risqué (une seule modification initiale)
- ✅ Configuration centralisée

**Inconvénients**:
- ⚠️ L'utilisateur doit penser à lancer `init`
- ⚠️ Ne détecte pas les ajouts manquants au fil du temps
- ⚠️ Pas vraiment "autonomous"

---

### 🥉 APPROCHE #3: Template de projet complet

**Principe**: Fournir un projet Symfony pré-configuré.

```bash
composer create-project ahmed-bhs/symfony-hexagonal-skeleton my-app
```

Le projet contient déjà:
- ✅ Doctrine configuré pour hexagonal
- ✅ Messenger configuré pour CQRS
- ✅ Routes configurées
- ✅ Structure de base
- ✅ Exemples complets

**Avantages**:
- ✅ Très simple pour démarrer
- ✅ Zéro configuration
- ✅ Exemples fournis

**Inconvénients**:
- ⚠️ Ne fonctionne que pour nouveaux projets
- ⚠️ Pas utilisable sur projet existant
- ⚠️ Double maintenance (bundle + skeleton)

---

## 📊 Comparaison

| Critère | Auto-config (1) | Init command (2) | Skeleton (3) |
|---------|----------------|------------------|--------------|
| **Autonomie** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Facilité dev** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Projet existant** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐ |
| **Complexité code** | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Risque bugs** | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Maintenance** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |

## 🏆 Recommandation finale

### Combinaison Approche #1 + #2:

1. **Créer `make:hexagonal:init`** (quick win)
   - Configure tout en une fois
   - Vérifiable avec `make:hexagonal:doctor`
   - Sprint 0 (1 jour)

2. **Implémenter auto-configuration progressive** (meilleur long terme)
   - Commencer par les Config Updaters
   - Intégrer dans chaque commande make:hexagonal:*
   - Sprint 1-2 (4-6 jours)

3. **Ajouter commandes de diagnostic** (qualité)
   - `make:hexagonal:audit` - vérifie fichiers obsolètes
   - `make:hexagonal:doctor` - vérifie config manquante
   - `make:hexagonal:update` - met à jour fichiers
   - Sprint 3 (2-3 jours)

4. **[Optionnel] Créer skeleton** (marketing)
   - Pour vitrine et démos
   - Pas prioritaire
   - Sprint 4+ (2 jours)

## 🚀 Quick Start (ce qu'on peut faire maintenant)

### Étape 1: Implémenter `make:hexagonal:doctor`

```php
// Vérifications:
✓ Doctrine ORM version (doit être 3.x pour XML)
✓ Mappings XML présents et configurés
✓ Types Doctrine enregistrés
✓ Buses Messenger configurés
✓ Routes configurées
✓ Bindings repositories présents
✓ Packages requis installés (symfony/uid, doctrine/fixtures)

// Output:
[OK] Configuration complete!
[WARNING] Missing doctrine type: habitant_id
  → Run: php bin/console make:hexagonal:fix

[ERROR] Messenger buses not configured
  → Run: php bin/console make:hexagonal:init
```

### Étape 2: Implémenter `make:hexagonal:fix`

```php
// Auto-fix des problèmes détectés:
- Ajouter types Doctrine manquants
- Ajouter mappings manquants
- Ajouter bindings manquants
```

### Étape 3: Améliorer les templates existants

```php
// À faire dès maintenant dans les templates:
1. Générer XML au lieu de YML si Doctrine ORM >= 3
2. Auto-détecter les ValueObjects depuis properties (email → Email VO)
3. Générer les types Doctrine automatiquement
4. Améliorer le smart CommandHandler (support Update/Delete/etc)
```

## 📝 Notes importantes

### Doctrine ORM 3.x
- ⚠️ YAML n'est plus supporté → utiliser XML
- ⚠️ Classes doivent être non-final pour lazy ghost objects
- ⚠️ Types personnalisés requis pour ValueObjects (pas de embedded)

### Best Practices
- ✅ Toujours backup avant modifier un fichier config
- ✅ Vérifier existence avant ajouter (éviter doublons)
- ✅ Messages clairs et actionnables
- ✅ Proposer auto-fix quand possible
- ✅ Logger les modifications pour rollback

## 📚 Ressources créées

- `ROADMAP-AUTONOMIE.md` - Plan détaillé d'implémentation
- `src/Config/ConfigFileUpdater.php` - Classe de base
- `src/Config/DoctrineConfigUpdater.php` - Updater Doctrine
- À créer: `MessengerConfigUpdater.php`, `ServicesConfigUpdater.php`, `RoutesConfigUpdater.php`

## 🎯 Objectif final

Un développeur doit pouvoir faire:

```bash
# Init projet (une seule fois)
composer require ahmed-bhs/hexagonal-maker-bundle --dev
php bin/console make:hexagonal:init

# Créer feature complète
php bin/console make:hexagonal:entity Cadeau Attribution Habitant \
  --properties="prenom:string,nom:string,age:int,email:email"

php bin/console make:hexagonal:command Cadeau Attribution EnregistrerHabitant \
  --properties="prenom:string,nom:string,age:int,email:email"

php bin/console make:hexagonal:controller Cadeau Attribution EnregistrerHabitant

# Vérifier
php bin/console make:hexagonal:doctor
# → [OK] Everything configured correctly! 🎉

# L'app fonctionne directement, sans aucune config manuelle!
symfony serve
# → http://127.0.0.1:8000 ✅
```

C'est ça l'autonomie! 🚀
