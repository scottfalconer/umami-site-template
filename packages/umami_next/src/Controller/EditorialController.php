<?php

declare(strict_types=1);

namespace Drupal\umami_next\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\umami_next\EditorialDataBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Editorial route controller for Umami Next.
 */
final class EditorialController extends ControllerBase {

  /**
   * Constructs a new controller.
   */
  public function __construct(
    private readonly EditorialDataBuilder $editorialData,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('umami_next.editorial_data'),
    );
  }

  /**
   * Builds the contact page.
   */
  public function contact(): array {
    $cacheability = (new CacheableMetadata())
      ->addCacheTags(['config:webform_list'])
      ->addCacheContexts(['user.permissions']);

    $build = [
      '#theme' => 'umami_next_contact',
      '#contact_form' => $this->editorialData->buildWebform('contact_form'),
      '#newsletter_form' => $this->editorialData->buildWebform('newsletter_signup'),
      '#social_links' => $this->editorialData->buildMenuLinks('social', $cacheability),
    ];
    $cacheability->applyTo($build);
    return $build;
  }

}
