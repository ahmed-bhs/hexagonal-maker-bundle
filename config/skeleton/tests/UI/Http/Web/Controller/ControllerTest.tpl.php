<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $entity_namespace ?>\<?= $entity_class ?>;
use <?= $repository_namespace ?>\<?= $repository_class ?>;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * UI Layer - Controller Test
 *
 * Full integration test for <?= $controller_class ?>.
 * Tests HTTP requests, form submissions, and responses.
 */
final class <?= $class_name ?> extends WebTestCase
{
    private KernelBrowser $client;
    private <?= $repository_class ?> $repository;
    private string $path = '<?= $route_path ?>';

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Get repository from container
        $this->repository = static::getContainer()->get(<?= $repository_class ?>::class);

        // Clean up database before each test
        foreach ($this->repository->findAll() as $entity) {
            $this->repository->remove($entity);
        }
    }

    public function testPageLoads(): void
    {
        // Act
        $this->client->request('GET', $this->path);

        // Assert
        self::assertResponseStatusCodeSame(200);
        // TODO: Add more assertions
        // self::assertPageTitleContains('Expected Title');
        // self::assertSelectorExists('form');
    }

    public function testPageRedirectsIfNeeded(): void
    {
        // Act
        $this->client->request('GET', $this->path);
        $this->client->followRedirect();

        // Assert
        self::assertResponseStatusCodeSame(200);
    }

    public function testFormSubmissionSuccess(): void
    {
        // Arrange
        $originalCount = count($this->repository->findAll());

        // Act
        $this->client->request('GET', $this->path);

        $this->client->submitForm('save', [
            // TODO: Add form field values
            // Example:
            // 'form_name[title]' => 'Test Title',
            // 'form_name[content]' => 'Test Content',
        ]);

        // Assert
        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Verify entity was created/updated
        // self::assertSame($originalCount + 1, count($this->repository->findAll()));

        // TODO: Add assertions to verify the operation succeeded
    }

    public function testFormSubmissionWithInvalidData(): void
    {
        // Act
        $this->client->request('GET', $this->path);

        $this->client->submitForm('save', [
            // TODO: Add invalid form data
            // Example:
            // 'form_name[title]' => '', // Empty title
            // 'form_name[content]' => '', // Empty content
        ]);

        // Assert
        self::assertResponseStatusCodeSame(200); // Should stay on same page

        // TODO: Verify error messages are displayed
        // self::assertSelectorTextContains('.form-error', 'This field is required');
    }

    public function testFormSubmissionValidatesData(): void
    {
        // Arrange
        $originalCount = count($this->repository->findAll());

        // Act
        $this->client->request('GET', $this->path);

        $this->client->submitForm('save', [
            // TODO: Add data that should fail validation
        ]);

        // Assert
        self::assertResponseStatusCodeSame(200);
        self::assertSame($originalCount, count($this->repository->findAll()));
    }
}
