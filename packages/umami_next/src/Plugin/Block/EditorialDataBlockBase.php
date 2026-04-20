<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\umami_next\EditorialDataBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for blocks backed by editorial data queries.
 */
abstract class EditorialDataBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Creates a new block instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EditorialDataBuilder $editorialData,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('umami_next.editorial_data'),
    );
  }

  /**
   * Builds cache metadata for output derived from content entity access checks.
   *
   * @param string[] $tags
   *   Cache tags to include.
   *
   * @return array<string, string[]>
   *   Render cache metadata.
   */
  protected function editorialCache(array $tags): array {
    return [
      'tags' => $tags,
      'contexts' => [
        'user.node_grants:view',
        'user.permissions',
      ],
    ];
  }

}
