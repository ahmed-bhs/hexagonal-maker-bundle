<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $use_case_namespace ?>\<?= $use_case_class ?>;
use <?= $command_namespace ?>\<?= $command_class ?>;
use <?= $response_namespace ?>\<?= $response_class ?>;
use <?= $repository_namespace ?>\<?= $repository_class ?>;
use <?= $exception_namespace ?>\<?= $exception_class ?>;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Application Layer - Use Case Test
 *
 * Tests the business logic of the <?= $use_case_class ?>.
 * Uses KernelTestCase for full container access.
 */
final class <?= $class_name ?> extends KernelTestCase
{
    private <?= $use_case_class ?> $useCase;
    private <?= $repository_class ?> $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        // Get repository from container
        $this->repository = static::getContainer()->get(<?= $repository_class ?>::class);

        // Instantiate use case with dependencies
        $this->useCase = new <?= $use_case_class ?>(
            $this->repository,
            // TODO: Add other dependencies
        );

        // Clean up database before each test
        // TODO: Implement cleanup logic if needed
    }

    public function testExecuteSuccess(): void
    {
        // Arrange
        $command = new <?= $command_class ?>(
            // TODO: Add command parameters
        );

        // Act
        $response = $this->useCase->execute($command);

        // Assert
        $this->assertInstanceOf(<?= $response_class ?>::class, $response);
        // TODO: Add more assertions to verify response data
    }

    /**
     * @dataProvider provideInvalidData
     */
    public function testExecuteWithInvalidData(array $data): void
    {
        $this->expectException(<?= $exception_class ?>::class);

        // Arrange
        $command = new <?= $command_class ?>(
            // TODO: Use $data array to create invalid command
            ...$data
        );

        // Act
        $this->useCase->execute($command);
    }

    /**
     * Data provider for invalid input testing
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function provideInvalidData(): array
    {
        return [
            // TODO: Add test cases with invalid data
            // Example:
            // [['title' => '', 'content' => 'Valid content']],
            // [['title' => 'Valid title', 'content' => '']],
            [[]],
        ];
    }

    /**
     * Helper method to get repository with different implementations
     *
     * @param string $type 'memory', 'doctrine', 'file'
     * @return <?= $repository_class ?>

     */
    private function getRepository(string $type = 'doctrine'): <?= $repository_class ?>

    {
        switch ($type) {
            case 'memory':
                // TODO: Return InMemory implementation for fast tests
                // return new InMemory<?= str_replace('RepositoryInterface', '', $repository_class) ?>();
                break;
            case 'doctrine':
                return static::getContainer()->get(<?= $repository_class ?>::class);
            default:
                throw new \InvalidArgumentException("Unknown repository type: {$type}");
        }

        return static::getContainer()->get(<?= $repository_class ?>::class);
    }
}
