<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use PHPUnit\Framework\TestCase;
use <?= $entity_namespace; ?>\<?= $entity_class; ?>;

/**
 * Unit test for <?= $entity_class; ?> entity
 *
 * Tests business logic and invariants
 */
final class <?= $test_class_name; ?> extends TestCase
{
    public function testEntityCanBeCreated(): void
    {
        // Arrange & Act
        $entity = new <?= $entity_class; ?>(
            // TODO: Add constructor parameters
        );

        // Assert
        $this->assertInstanceOf(<?= $entity_class; ?>::class, $entity);
        // TODO: Add assertions on entity state
    }

    public function testEntityEnforcesBusinessRules(): void
    {
        // Arrange
        $entity = new <?= $entity_class; ?>(
            // TODO: Add valid parameters
        );

        // Act & Assert
        // TODO: Test business rule enforcement
        // Example:
        // $entity->doSomething();
        // $this->assertTrue($entity->isInExpectedState());
    }

    public function testEntityThrowsExceptionOnInvalidState(): void
    {
        // Arrange
        $entity = new <?= $entity_class; ?>(
            // TODO: Add parameters
        );

        // Assert
        $this->expectException(\DomainException::class);

        // Act
        // TODO: Try to put entity in invalid state
        // Example: $entity->performInvalidOperation();
    }

    // TODO: Add tests for all business methods
    // TODO: Add tests for value object validation
    // TODO: Add tests for state transitions
}
