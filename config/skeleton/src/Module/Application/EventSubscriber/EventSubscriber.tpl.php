<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $event_namespace ?>\<?= $event_class ?>;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Application Layer - Event Subscriber
 *
 * Handles domain events and orchestrates application workflows.
 * This subscriber contains business logic orchestration.
 *
 * Use this for:
 * - Calling use cases in response to domain events
 * - Orchestrating multi-step workflows
 * - Coordinating between different bounded contexts
 */
final readonly class <?= $class_name ?> implements EventSubscriberInterface
{
    public function __construct(
        // TODO: Inject use cases or application services
        // Example:
        // private SendEmailUseCase $sendEmail,
        // private CreateInvoiceUseCase $createInvoice,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            <?= $event_class ?>::class => 'on<?= $event_class ?>',
            // TODO: Add more events if needed
        ];
    }

    public function on<?= $event_class ?>(<?= $event_class ?> $event): void
    {
        // TODO: Implement event handling logic
        // Example:
        //
        // $this->sendEmail->execute(
        //     new SendEmailCommand($event->orderId)
        // );
        //
        // $this->createInvoice->execute(
        //     new CreateInvoiceCommand($event->orderId)
        // );
    }
}
