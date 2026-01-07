# Doctrine ORM Mapping Configuration

This directory contains Doctrine ORM mapping files (YAML format) for your domain entities.

## Why YAML Mapping in Infrastructure?

In **Hexagonal Architecture**, we keep the **Domain layer PURE** - free from infrastructure dependencies.

- 👌 **Domain Entities** (`Domain/Model/*.php`) - No Doctrine attributes, pure PHP
- 👌 **Doctrine Mapping** (`Infrastructure/Persistence/Doctrine/Orm/Mapping/*.orm.yml`) - Infrastructure concern

This separation allows:
- Domain logic independent of persistence framework
- Easy testing without database
- Ability to switch ORM/databases without touching domain code

## Configuration Required

After generating entities, you need to configure Doctrine to find these mapping files.

### Step 1: Update `config/packages/doctrine.yaml`

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'

    orm:
        auto_generate_proxy_classes: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            # Add one mapping configuration per module
            UserAccount:
                is_bundle: false
                type: yml
                dir: '%kernel.project_dir%/src/User/Account/Infrastructure/Persistence/Doctrine/Orm/Mapping'
                prefix: 'App\User\Account\Domain\Model'
                alias: UserAccount

            # Example for another module:
            # Product:
            #     is_bundle: false
            #     type: yml
            #     dir: '%kernel.project_dir%/src/Catalog/Product/Infrastructure/Persistence/Doctrine/Orm/Mapping'
            #     prefix: 'App\Catalog\Product\Domain\Model'
            #     alias: Product
```

### Step 2: Generate Database Schema

```bash
# Create migration
bin/console doctrine:migrations:diff

# Review generated migration
# Then execute:
bin/console doctrine:migrations:migrate

# Or for development (direct schema update):
bin/console doctrine:schema:update --force
```

## YAML Mapping Syntax

### Basic Entity
```yaml
App\Domain\Model\User:
    type: entity
    repositoryClass: App\Infrastructure\Persistence\Doctrine\DoctrineUserRepository
    table: user

    id:
        id:
            type: string
            length: 36

    fields:
        email:
            type: string
            length: 180
            unique: true
        createdAt:
            type: datetime_immutable
            column: created_at
```

### Field Types
```yaml
fields:
    # String
    name:
        type: string
        length: 255

    # Text (unlimited)
    description:
        type: text

    # Numbers
    age:
        type: integer
    price:
        type: decimal
        precision: 10
        scale: 2

    # Boolean
    isActive:
        type: boolean

    # Dates
    createdAt:
        type: datetime_immutable
    birthDate:
        type: date_immutable

    # JSON
    metadata:
        type: json

    # Nullable
    middleName:
        type: string
        length: 255
        nullable: true
```

### Identity Strategies

```yaml
# UUID (Recommended for DDD)
id:
    id:
        type: uuid

# ULID (Sortable UUID)
id:
    id:
        type: ulid

# Auto-increment
id:
    id:
        type: integer
        generator:
            strategy: AUTO
```

### Associations

```yaml
# One-to-Many
oneToMany:
    orders:
        targetEntity: App\Domain\Model\Order
        mappedBy: user
        cascade: ['persist', 'remove']

# Many-to-One
manyToOne:
    category:
        targetEntity: App\Domain\Model\Category
        inversedBy: products
        joinColumn:
            name: category_id
            referencedColumnName: id
            nullable: false

# Many-to-Many
manyToMany:
    tags:
        targetEntity: App\Domain\Model\Tag
        inversedBy: products
        joinTable:
            name: product_tag
```

### Embedded Value Objects

```yaml
# In Address.orm.yml
App\Domain\ValueObject\Address:
    type: embeddable
    fields:
        street:
            type: string
        city:
            type: string
        zipCode:
            type: string
            length: 10

# In User.orm.yml
App\Domain\Model\User:
    type: entity
    embedded:
        address:
            class: App\Domain\ValueObject\Address
```

## Reference

Full Doctrine YAML mapping documentation:
https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/yaml-mapping.html
