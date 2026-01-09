# Custom Templates

Customize code generation templates to match your team's conventions.

---

## Configuration

```yaml
# config/packages/hexagonal_maker.yaml
hexagonal_maker:
    skeleton_dir: '%kernel.project_dir%/config/skeleton'
```

---

## Template Structure

```
config/skeleton/
└── src/Module/
    ├── Domain/
    │   └── Model/
    │       └── Entity.tpl.php
    ├── Application/
    │   └── Command/
    │       └── CommandHandler.tpl.php
    └── ...
```

---

## Example Custom Template

```php
<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

// Your custom headers, comments, etc.

final class <?= $class_name ?>
{
    // Your custom template logic
}
```

---

## Template Variables

Available variables depend on the maker. Common ones:

- `$namespace` - Full namespace
- `$class_name` - Class name
- `$module` - Module path
- `$entity_name` - Entity name

---

See default templates in `vendor/ahmed-bhs/hexagonal-maker-bundle/src/Resources/skeleton/`
