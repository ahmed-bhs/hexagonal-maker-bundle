<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use <?= $command_namespace; ?>\<?= $command_class; ?>;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Integration test for <?= $handler_class; ?>
 *
 * Tests the full stack with real dependencies (database, services, etc.)
 */
final class <?= $test_class_name; ?> extends KernelTestCase
{
    private MessageBusInterface $commandBus;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->commandBus = static::getContainer()->get(MessageBusInterface::class);
    }

    public function testCommandIsHandledSuccessfully(): void
    {
        // Arrange
        $command = new <?= $command_class; ?>(
            // TODO: Add command parameters
        );

        // Act
        $this->commandBus->dispatch($command);

        // Assert
        // TODO: Verify side effects (database, events, etc.)
        // Example:
        // $repository = static::getContainer()->get(<?= $entity_name; ?>RepositoryInterface::class);
        // $entity = $repository->findById($entityId);
        // $this->assertNotNull($entity);
    }

    public function testCommandFailsWithInvalidData(): void
    {
        // Arrange
        $command = new <?= $command_class; ?>(
            // TODO: Add invalid parameters
        );

        // Assert
        $this->expectException(\Exception::class);

        // Act
        $this->commandBus->dispatch($command);
    }

    // TODO: Add more integration test cases
}
