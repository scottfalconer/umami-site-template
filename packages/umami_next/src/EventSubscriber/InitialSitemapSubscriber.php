<?php

declare(strict_types=1);

namespace Drupal\umami_next\EventSubscriber;

use Drupal\umami_next\InitialSitemapGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures the first sitemap exists after recipe installation.
 */
final readonly class InitialSitemapSubscriber implements EventSubscriberInterface {

  public function __construct(
    private InitialSitemapGenerator $initialSitemapGenerator,
  ) {}

  /**
   * Generates the initial sitemap before the sitemap controller handles it.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $this->initialSitemapGenerator->generateIfReady();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 40],
    ];
  }

}
