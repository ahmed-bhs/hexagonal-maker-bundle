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
<?php if (!empty($properties)): ?>
<?php foreach ($properties as $prop): ?>
        <?= $prop['name'] ?>:
            type: <?= $prop['doctrineType'] ?>

<?php if ($prop['doctrineType'] === 'string'): ?>
            length: <?= $prop['maxLength'] ?? 255 ?>

<?php endif; ?>
<?php if ($prop['nullable']): ?>
            nullable: true
<?php endif; ?>
<?php if ($prop['unique']): ?>
            unique: true
<?php endif; ?>
<?php if (in_array($prop['doctrineType'], ['decimal', 'float'])): ?>
            precision: 10
            scale: 2
<?php endif; ?>
<?php endforeach; ?>
<?php else: ?>
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
<?php endif; ?>

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
