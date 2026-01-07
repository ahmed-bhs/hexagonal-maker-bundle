<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use PHPUnit\Framework\TestCase;
use <?= $command_namespace; ?>\<?= $command_class; ?>;
use <?= $handler_namespace; ?>\<?= $handler_class; ?>;

/**
 * Unit test for <?= $handler_class; ?>
 *
 * Tests the business logic in isolation using mocks/stubs
 */
final class <?= $test_class_name; ?> extends TestCase
{
    public function testHandlerExecutesSuccessfully(): void
    {
        // Arrange
        $command = new <?= $command_class; ?>(
            // TODO: Add command parameters
        );

        // TODO: Create mocks for dependencies
        // Example:
        // $repository = $this->createMock(<?= $entity_name; ?>RepositoryInterface::class);
        // $repository->expects($this->once())
        //     ->method('save')
        //     ->with($this->isInstanceOf(<?= $entity_name; ?>::class));

        $handler = new <?= $handler_class; ?>(
            // TODO: Inject mocked dependencies
        );

        // Act
        $handler($command);

        // Assert
        $this->assertTrue(true); // TODO: Replace with actual assertions
    }

    public function testHandlerThrowsExceptionWhenInvalidData(): void
    {
        // Arrange
        $command = new <?= $command_class; ?>(
            // TODO: Add invalid parameters
        );

        $handler = new <?= $handler_class; ?>(
            // TODO: Inject mocked dependencies
        );

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $handler($command);
    }

    // TODO: Add more test cases for edge cases and business rules
}
