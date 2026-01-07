<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Infrastructure Layer - Event Subscriber
 *
 * Handles technical/infrastructure concerns.
 * This subscriber contains framework-specific logic.
 *
 * Use this for:
 * - Logging, monitoring, metrics
 * - Cache management
 * - Security concerns
 * - Framework-specific events (kernel.*, doctrine.*, messenger.*)
 */
final readonly class <?= $class_name ?> implements EventSubscriberInterface
{
    public function __construct(
        // TODO: Inject infrastructure services
        // Example:
        // private LoggerInterface $logger,
        // private CacheInterface $cache,
        // private MetricsCollectorInterface $metrics,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // TODO: Subscribe to framework events
            // Examples:
            // KernelEvents::EXCEPTION => 'onKernelException',
            // KernelEvents::REQUEST => 'onKernelRequest',
            // KernelEvents::RESPONSE => 'onKernelResponse',
            // 'doctrine.post_persist' => 'onDoctrinePostPersist',
            // 'messenger.message_handled' => 'onMessageHandled',
        ];
    }

    // TODO: Implement event handlers
    // Example:
    //
    // public function onKernelException(ExceptionEvent $event): void
    // {
    //     $exception = $event->getThrowable();
    //
    //     $this->logger->error('Exception occurred', [
    //         'exception' => get_class($exception),
    //         'message' => $exception->getMessage(),
    //         'trace' => $exception->getTraceAsString(),
    //     ]);
    // }
}
