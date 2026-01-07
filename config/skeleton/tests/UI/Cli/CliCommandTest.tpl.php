<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $repository_namespace ?>\<?= $repository_class ?>;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * UI Layer - CLI Command Test
 *
 * Tests the CLI command <?= $command_name ?>.
 * Uses CommandTester for command execution testing.
 */
final class <?= $class_name ?> extends KernelTestCase
{
    private CommandTester $commandTester;
    private <?= $repository_class ?> $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('<?= $command_name ?>');

        $this->commandTester = new CommandTester($command);
        $this->repository = static::getContainer()->get(<?= $repository_class ?>::class);

        // Clean up database before each test
        foreach ($this->repository->findAll() as $entity) {
            $this->repository->remove($entity);
        }
    }

    public function testExecuteSuccess(): void
    {
        // Arrange
        $originalCount = count($this->repository->findAll());

        // Act
        $this->commandTester->execute([
            // TODO: Add command arguments and options
            // Example:
            // 'argument-name' => 'value',
            // '--option-name' => 'value',
        ]);

        // Assert
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        // TODO: Verify output contains success message
        // $this->assertStringContainsString('Success message', $output);

        // TODO: Verify entity was created
        // self::assertSame($originalCount + 1, count($this->repository->findAll()));
    }

    public function testExecuteWithInvalidArguments(): void
    {
        // Act
        $this->commandTester->execute([
            // TODO: Add invalid arguments/options
        ]);

        // Assert
        $statusCode = $this->commandTester->getStatusCode();
        self::assertEquals(1, $statusCode); // Command::FAILURE

        $output = $this->commandTester->getDisplay();
        // TODO: Verify error message in output
        // $this->assertStringContainsString('Error message', $output);
    }

    public function testExecuteWithOptions(): void
    {
        // Act
        $this->commandTester->execute([
            // TODO: Add arguments
            // '--option-name' => true,
        ]);

        // Assert
        $this->commandTester->assertCommandIsSuccessful();

        // TODO: Verify option was applied correctly
    }

    public function testExecuteDisplaysCorrectOutput(): void
    {
        // Act
        $this->commandTester->execute([
            // TODO: Add command parameters
        ]);

        // Assert
        $output = $this->commandTester->getDisplay();

        // TODO: Verify output format and content
        // $this->assertStringContainsString('Expected output', $output);
        // $this->assertMatchesRegularExpression('/pattern/', $output);
    }

    public function testExecuteHandlesErrors(): void
    {
        // Act
        $this->commandTester->execute([
            // TODO: Add parameters that will cause an error
        ]);

        // Assert
        $statusCode = $this->commandTester->getStatusCode();
        self::assertEquals(1, $statusCode);

        $output = $this->commandTester->getDisplay();
        // TODO: Verify error handling
        // $this->assertStringContainsString('Error:', $output);
    }
}
