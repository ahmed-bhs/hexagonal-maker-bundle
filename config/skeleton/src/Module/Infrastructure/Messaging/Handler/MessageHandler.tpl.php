<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use <?= $message_namespace; ?>\<?= $message_class; ?>;

/**
 * Message Handler - Infrastructure Layer
 *
 * Handles async messages from Symfony Messenger.
 * This is a Secondary Adapter (Infrastructure) for async processing.
 *
 * Example use cases:
 * - Send email notifications asynchronously
 * - Process background jobs
 * - Handle domain events asynchronously
 * - Sync data to external APIs
 */
#[AsMessageHandler]
final readonly class <?= $class_name; ?>

{
    public function __construct(
        // Inject your dependencies here
        // Examples:
        // private EmailServiceInterface $emailService,
        // private LoggerInterface $logger,
        // private YourUseCaseInterface $useCase,
    ) {
    }

    public function __invoke(<?= $message_class; ?> $message): void
    {
        // TODO: Implement your async message handling logic

        // Example 1: Send async email
        // $this->emailService->sendEmail(
        //     to: $message->recipientEmail,
        //     subject: 'Welcome!',
        //     body: 'Thank you for registering'
        // );

        // Example 2: Log event
        // $this->logger->info('Processing message', [
        //     'message_id' => $message->getId(),
        //     'type' => '<?= $message_class; ?>',
        // ]);

        // Example 3: Call use case
        // $this->useCase->execute($message->toCommand());
    }
}
