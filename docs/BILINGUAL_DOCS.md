# Bilingual Documentation System

## Overview

The Hexagonal Maker Bundle documentation is available in two languages:
- 🇬🇧 **English** (default)
- 🇫🇷 **French**

## Language Switcher

A language switcher is available in the top-right corner of every documentation page, allowing users to seamlessly switch between languages.

## Structure

```
docs/
├── index.md                     # English home page
├── fr/
│   └── index.md                 # French home page
├── advanced/                    # English advanced guides
│   ├── domain-vs-application.md
│   ├── request-response-flow.md
│   ├── port-design-principles.md
│   ├── primary-secondary-adapters.md
│   ├── cqrs-cost-benefit.md
│   ├── dependency-injection-guide.md
│   ├── factory-pattern-guide.md
│   ├── error-handling-strategy.md
│   └── anti-patterns-pitfalls.md
├── ARCHITECTURE.md              # Available in French
├── WHY-HEXAGONAL.md            # English
├── SOLID.md                    # English
└── _includes/
    └── language-switcher.html  # Language switcher component
```

## How It Works

### 1. Layout Template

The `default_with_lang` layout includes the language switcher:

```yaml
---
layout: default_with_lang
title: Your Page Title
lang: en  # or 'fr'
lang_ref: fr/page.md  # Optional: reference to translated version
---
```

### 2. Language Attributes

Each page with bilingual support should include:

- `lang`: Current language (`en` or `fr`)
- `lang_ref`: Relative path to the translated version (optional)

**Example (English page):**

```yaml
---
layout: default_with_lang
title: Home
lang: en
lang_ref: fr/index.md
---
```

**Example (French page):**

```yaml
---
layout: default_with_lang
title: Accueil
lang: fr
lang_ref: index.md
---
```

### 3. Language Switcher Component

The `_includes/language-switcher.html` component:
- Shows current language as text
- Shows link to alternate language
- Uses flags (🇬🇧/🇫🇷) for visual identification
- Fixed position in top-right corner
- Responsive (moves to static position on mobile)

## Adding a New Bilingual Page

### Step 1: Create English Version

```yaml
---
layout: default_with_lang
title: My New Page
parent: Advanced Topics
nav_order: 20
lang: en
lang_ref: fr/advanced/my-new-page.md
---

# My New Page

Content in English...
```

### Step 2: Create French Version

```yaml
---
layout: default_with_lang
title: Ma Nouvelle Page
parent: Sujets Avancés
nav_order: 20
lang: fr
lang_ref: advanced/my-new-page.md
---

# Ma Nouvelle Page

Contenu en français...
```

### Step 3: Update Navigation (if needed)

For French-only navigation sections, create `fr/advanced/index.md`:

```yaml
---
layout: default_with_lang
title: Sujets Avancés
nav_order: 3
has_children: true
lang: fr
---
```

## Current Translation Status

### ✅ Fully Translated

- Home page (`index.md` ↔ `fr/index.md`)

### 🔄 Partially Translated

- Architecture Guide (`ARCHITECTURE.md` - has French version)

### ❌ Not Yet Translated

- Advanced Topics (9 guides) - currently English only
- Getting Started guides
- Maker Commands reference
- Examples
- WHY-HEXAGONAL.md
- SOLID.md

## Translation Workflow

### Priority Order

1. **High Priority** (User-facing):
   - Home page ✅
   - Quick Start guide
   - Architecture Guide (partial ✅)

2. **Medium Priority** (Conceptual):
   - Why Hexagonal
   - SOLID Principles
   - Advanced Topics (9 guides)

3. **Low Priority** (Reference):
   - Maker Commands
   - Examples
   - Contributing guides

### Translation Guidelines

1. **Consistency**: Use consistent terminology
   - "Port" → "Port" (keep in French too)
   - "Adapter" → "Adaptateur"
   - "Domain" → "Domaine"
   - "Handler" → "Handler" (keep English term)

2. **Code Examples**: Keep code in English (variable names, comments in English)

3. **Technical Terms**: Keep well-established English terms when appropriate
   - CQRS (don't translate)
   - Factory Pattern → "Pattern Factory"
   - Dependency Injection → "Injection de Dépendances"

4. **File Names**:
   - French content goes in `fr/` subdirectories
   - Keep original English file names for consistency

## Maintenance

### When Adding New Content

1. Create English version first (default)
2. Add `layout: default_with_lang` and `lang: en`
3. Add `lang_ref` if French version exists or planned
4. Create French version in `fr/` subdirectory
5. Update both index pages to reference new content

### When Updating Content

1. Update English version
2. Update French version (if exists)
3. If major changes, add note about translation status

## Styling

The language switcher uses:
- Fixed positioning (top-right)
- White background with border
- Hover effects on links
- Emoji flags for visual recognition
- Responsive design for mobile

Custom CSS in `language-switcher.html` ensures:
- Proper z-index (1000) to stay on top
- No interference with main navigation
- Accessibility considerations

## Future Enhancements

Potential improvements:
1. Add more languages (ES, DE, etc.)
2. Automatic language detection based on browser
3. Translation progress indicator
4. Crowdin integration for community translations
5. Search within specific language
6. Language-specific navigation sidebar

## Contributing Translations

To contribute a translation:

1. Fork the repository
2. Create translations in `docs/fr/` (or other language folder)
3. Add `lang` and `lang_ref` metadata
4. Test locally with Jekyll
5. Submit pull request

## Testing

### Local Testing

```bash
cd docs
bundle install
bundle exec jekyll serve

# Visit http://localhost:4000
# Test language switcher functionality
# Verify both EN and FR versions
```

### Checklist

- [ ] Language switcher appears on all pages
- [ ] Clicking switcher navigates to correct language
- [ ] Navigation works in both languages
- [ ] Search works in both languages
- [ ] Mobile responsive design works
- [ ] No broken links between language versions

---

**Documentation Structure by:** Ahmed EBEN HASSINE
**Last Updated:** 2024-01-15
