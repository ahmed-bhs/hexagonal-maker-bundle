<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use PHPUnit\Framework\TestCase;
use <?= $query_namespace; ?>\<?= $query_class; ?>;
use <?= $handler_namespace; ?>\<?= $handler_class; ?>;
use <?= $response_namespace; ?>\<?= $response_class; ?>;

/**
 * Unit test for <?= $handler_class; ?>
 *
 * Tests query handling logic in isolation
 */
final class <?= $test_class_name; ?> extends TestCase
{
    public function testHandlerReturnsExpectedResponse(): void
    {
        // Arrange
        $query = new <?= $query_class; ?>(
            // TODO: Add query parameters
        );

        // TODO: Create mocks for dependencies
        // Example:
        // $repository = $this->createMock(<?= $entity_name; ?>RepositoryInterface::class);
        // $repository->expects($this->once())
        //     ->method('findById')
        //     ->willReturn($expectedEntity);

        $handler = new <?= $handler_class; ?>(
            // TODO: Inject mocked dependencies
        );

        // Act
        $response = $handler($query);

        // Assert
        $this->assertInstanceOf(<?= $response_class; ?>::class, $response);
        // TODO: Add specific assertions on response data
    }

    public function testHandlerReturnsNullWhenNotFound(): void
    {
        // Arrange
        $query = new <?= $query_class; ?>(
            // TODO: Add parameters for non-existent entity
        );

        // TODO: Mock repository to return null
        $handler = new <?= $handler_class; ?>(
            // TODO: Inject mocked dependencies
        );

        // Act
        $response = $handler($query);

        // Assert
        $this->assertNull($response);
    }

    // TODO: Add more test cases for different scenarios
}
