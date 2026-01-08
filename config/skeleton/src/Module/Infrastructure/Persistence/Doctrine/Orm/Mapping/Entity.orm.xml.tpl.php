<?= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" ?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                  https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <entity name="<?= $entity_full_class_name ?>"
            table="<?= strtolower($entity_name) ?>">

        <!-- Primary Key -->
        <id name="id" type="<?= $id_type ?? 'string' ?>"<?php if (isset($id_length)): ?> length="<?= $id_length ?>"<?php endif; ?> />

<?php if (!empty($properties)): ?>
        <!-- Fields -->
<?php foreach ($properties as $property): ?>
<?php if ($property['name'] === 'id') continue; // Skip ID, already defined ?>
        <field name="<?= $property['name'] ?>"
               type="<?= $property['doctrineType'] ?? $property['type'] ?>"
<?php if (isset($property['length'])): ?>
               length="<?= $property['length'] ?>"
<?php endif; ?>
<?php if ($property['nullable'] ?? false): ?>
               nullable="true"
<?php endif; ?>
<?php if ($property['unique'] ?? false): ?>
               unique="true"
<?php endif; ?>
        />
<?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($embeddables)): ?>
        <!-- Embedded Value Objects -->
<?php foreach ($embeddables as $embedded): ?>
        <embedded name="<?= $embedded['name'] ?>"
                  class="<?= $embedded['class'] ?>"
                  use-column-prefix="<?= $embedded['useColumnPrefix'] ?? 'false' ?>" />
<?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($associations)): ?>
        <!-- Associations -->
<?php foreach ($associations as $assoc): ?>
<?php if ($assoc['type'] === 'one-to-many'): ?>
        <one-to-many field="<?= $assoc['field'] ?>"
                     target-entity="<?= $assoc['targetEntity'] ?>"
                     mapped-by="<?= $assoc['mappedBy'] ?>">
<?php if (isset($assoc['cascade'])): ?>
            <cascade>
<?php foreach ($assoc['cascade'] as $operation): ?>
                <cascade-<?= $operation ?> />
<?php endforeach; ?>
            </cascade>
<?php endif; ?>
        </one-to-many>
<?php endif; ?>

<?php if ($assoc['type'] === 'many-to-one'): ?>
        <many-to-one field="<?= $assoc['field'] ?>"
                     target-entity="<?= $assoc['targetEntity'] ?>"
<?php if (isset($assoc['inversedBy'])): ?>
                     inversed-by="<?= $assoc['inversedBy'] ?>"
<?php endif; ?>
        >
            <join-column name="<?= $assoc['joinColumn']['name'] ?>"
                        referenced-column-name="<?= $assoc['joinColumn']['referencedColumnName'] ?>"
<?php if ($assoc['joinColumn']['nullable'] ?? false): ?>
                        nullable="true"
<?php endif; ?>
            />
<?php if (isset($assoc['cascade'])): ?>
            <cascade>
<?php foreach ($assoc['cascade'] as $operation): ?>
                <cascade-<?= $operation ?> />
<?php endforeach; ?>
            </cascade>
<?php endif; ?>
        </many-to-one>
<?php endif; ?>

<?php if ($assoc['type'] === 'many-to-many'): ?>
        <many-to-many field="<?= $assoc['field'] ?>"
                      target-entity="<?= $assoc['targetEntity'] ?>">
            <join-table name="<?= $assoc['joinTable']['name'] ?>">
                <join-columns>
                    <join-column name="<?= $assoc['joinTable']['joinColumn'] ?>"
                                referenced-column-name="id" />
                </join-columns>
                <inverse-join-columns>
                    <join-column name="<?= $assoc['joinTable']['inverseJoinColumn'] ?>"
                                referenced-column-name="id" />
                </inverse-join-columns>
            </join-table>
<?php if (isset($assoc['cascade'])): ?>
            <cascade>
<?php foreach ($assoc['cascade'] as $operation): ?>
                <cascade-<?= $operation ?> />
<?php endforeach; ?>
            </cascade>
<?php endif; ?>
        </many-to-many>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($lifecycle_callbacks)): ?>
        <!-- Lifecycle Callbacks -->
        <lifecycle-callbacks>
<?php foreach ($lifecycle_callbacks as $event => $method): ?>
            <lifecycle-callback type="<?= $event ?>" method="<?= $method ?>" />
<?php endforeach; ?>
        </lifecycle-callbacks>
<?php endif; ?>

    </entity>

</doctrine-mapping>
