<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $port_namespace ?>\<?= $port_class ?>;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Repository Adapter (Infrastructure)
 *
 * This is an adapter in hexagonal architecture - it implements the port interface
 * and provides the actual infrastructure implementation (Doctrine ORM in this case).
 *
 * This adapter translates domain operations to infrastructure-specific operations.
 */
final class <?= $class_name ?> implements <?= $port_class ?>

{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(<?= $entity_name ?> $<?= strtolower($entity_name) ?>): void
    {
        $this->entityManager->persist($<?= strtolower($entity_name) ?>);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?<?= $entity_name ?>

    {
        return $this->entityManager->find(<?= $entity_name ?>::class, $id);
    }

    public function delete(<?= $entity_name ?> $<?= strtolower($entity_name) ?>): void
    {
        $this->entityManager->remove($<?= strtolower($entity_name) ?>);
        $this->entityManager->flush();
    }
}
