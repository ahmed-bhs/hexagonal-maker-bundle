<?= $entity_full_class_name ?>:
    type: entity
    repositoryClass: <?= $repository_full_class_name ?>

    table: <?= strtolower($entity_name) ?>

    id:
        id:
            type: string
            length: 36
            # For UUID, use: type: uuid
            # For ULID, use: type: ulid

    fields:
        # TODO: Add your entity fields mapping here
        # Example:
        # name:
        #     type: string
        #     length: 255
        # email:
        #     type: string
        #     length: 180
        #     unique: true
        # createdAt:
        #     type: datetime_immutable
        #     column: created_at
        # isActive:
        #     type: boolean
        #     column: is_active

    # Uncomment for lifecycle callbacks
    # lifecycleCallbacks: {  }

    # Uncomment for associations
    # oneToMany:
    #     items:
    #         targetEntity: App\Domain\Item\Item
    #         mappedBy: parent
    # manyToOne:
    #     category:
    #         targetEntity: App\Domain\Category\Category
    #         inversedBy: items
    #         joinColumn:
    #             name: category_id
    #             referencedColumnName: id
