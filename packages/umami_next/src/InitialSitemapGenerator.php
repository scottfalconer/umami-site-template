<?php

declare(strict_types=1);

namespace Drupal\umami_next;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Psr\Container\ContainerInterface;

/**
 * Generates Umami's initial XML sitemap once the installed site is routable.
 */
final readonly class InitialSitemapGenerator {

  public function __construct(
    private StateInterface $state,
    private ModuleHandlerInterface $moduleHandler,
    private ContainerInterface $container,
    private LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Generates the initial sitemap when default content and routes are ready.
   */
  public function generateIfReady(): void {
    if ($this->state->get('umami_next.initial_sitemap_generated')) {
      return;
    }
    if (!$this->state->get('umami_next.default_content_references_backfilled')) {
      return;
    }
    if (!$this->moduleHandler->moduleExists('simple_sitemap')) {
      return;
    }
    if (!$this->container->has('simple_sitemap.generator') || !$this->container->has('path.validator')) {
      return;
    }

    $path_validator = $this->container->get('path.validator');
    foreach (['/', '/recipes', '/stories', '/contact'] as $path) {
      if (!$path_validator->getUrlIfValidWithoutAccessCheck($path)) {
        return;
      }
    }

    try {
      // Use backend mode to generate synchronously during the small demo import.
      $this->container->get('simple_sitemap.generator')->generate('backend');
      $this->state->set('umami_next.initial_sitemap_generated', TRUE);
    }
    catch (\Throwable $exception) {
      $this->loggerFactory->get('umami_next')->warning('Unable to generate the initial XML sitemap: @message', [
        '@message' => $exception->getMessage(),
      ]);
    }
  }

}
