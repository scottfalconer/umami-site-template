<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Homepage hero section.
 */
#[Block(
  id: 'umami_next_home_hero',
  admin_label: new TranslatableMarkup('Umami Next home hero'),
  category: new TranslatableMarkup('Umami Next'),
)]
final class HomeHeroBlock extends EditorialDataBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $hero_node = $this->editorialData->loadFeaturedNode('article');

    return [
      '#theme' => 'umami_next_home_hero_block',
      '#hero' => $hero_node ? $this->editorialData->buildArticleCard($hero_node, TRUE) : NULL,
      '#hero_author' => $hero_node ? $this->editorialData->buildAuthor($hero_node->getOwner()) : NULL,
      '#cache' => $this->editorialCache(['node_list', 'user_list']),
    ];
  }

}
