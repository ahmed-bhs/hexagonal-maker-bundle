<?= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" ?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                  https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <embeddable name="<?= $embeddable_full_class_name ?>">

<?php if (!empty($properties)): ?>
        <!-- Fields -->
<?php foreach ($properties as $property): ?>
        <field name="<?= $property['name'] ?>"
               type="<?= $property['doctrineType'] ?? $property['type'] ?>"
<?php if (isset($property['length'])): ?>
               length="<?= $property['length'] ?>"
<?php endif; ?>
<?php if ($property['nullable'] ?? false): ?>
               nullable="true"
<?php endif; ?>
        />
<?php endforeach; ?>
<?php endif; ?>

    </embeddable>

</doctrine-mapping>
