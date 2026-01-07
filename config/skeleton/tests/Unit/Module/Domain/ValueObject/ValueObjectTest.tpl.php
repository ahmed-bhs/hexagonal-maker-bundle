<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use PHPUnit\Framework\TestCase;
use <?= $value_object_namespace; ?>\<?= $value_object_class; ?>;

/**
 * Unit test for <?= $value_object_class; ?> value object
 *
 * Tests validation and immutability
 */
final class <?= $test_class_name; ?> extends TestCase
{
    public function testValueObjectCanBeCreatedWithValidData(): void
    {
        // Arrange & Act
        $valueObject = new <?= $value_object_class; ?>(
            // TODO: Add valid constructor parameters
        );

        // Assert
        $this->assertInstanceOf(<?= $value_object_class; ?>::class, $valueObject);
        // TODO: Verify value is stored correctly
    }

    public function testValueObjectThrowsExceptionWithInvalidData(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new <?= $value_object_class; ?>(
            // TODO: Add invalid parameters
        );
    }

    public function testValueObjectIsImmutable(): void
    {
        // Arrange
        $valueObject = new <?= $value_object_class; ?>(
            // TODO: Add parameters
        );

        // Assert
        // Value objects should be readonly or have no setters
        $this->assertTrue(true); // TODO: Add specific immutability checks
    }

    public function testValueObjectEquality(): void
    {
        // Arrange
        $valueObject1 = new <?= $value_object_class; ?>(
            // TODO: Add parameters
        );
        $valueObject2 = new <?= $value_object_class; ?>(
            // TODO: Add same parameters
        );

        // Assert
        // TODO: If equals() method exists
        // $this->assertTrue($valueObject1->equals($valueObject2));
        $this->assertTrue(true); // TODO: Replace with actual equality check
    }

    // TODO: Add tests for all validation rules
    // TODO: Add tests for any transformation methods
}
