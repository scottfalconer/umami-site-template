<?php

declare(strict_types=1);

namespace Drupal\umami_next\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts denied access to unpublished nodes into not-found responses.
 */
final readonly class UnpublishedNodeNotFoundSubscriber implements EventSubscriberInterface {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Converts unpublished node 403s before Drupal logs the exception.
   */
  public function onException(ExceptionEvent $event): void {
    $exception = $event->getThrowable();
    if (!$exception instanceof HttpExceptionInterface || $exception->getStatusCode() !== 403) {
      return;
    }

    $node = $event->getRequest()->attributes->get('node');
    if (!$node instanceof NodeInterface && is_scalar($node) && (string) $node !== '') {
      $node = $this->entityTypeManager->getStorage('node')->load((int) $node);
    }

    if (!$node instanceof NodeInterface || $node->isPublished()) {
      return;
    }

    $event->setThrowable(new NotFoundHttpException('', $exception));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Run before core's exception logger so denied unpublished nodes are
      // logged as ordinary 404s, not as access-denied warnings.
      KernelEvents::EXCEPTION => ['onException', 60],
    ];
  }

}
