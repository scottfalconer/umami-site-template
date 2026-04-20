<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 404 recovery section.
 */
#[Block(
  id: 'umami_next_not_found',
  admin_label: new TranslatableMarkup('Umami Next not found'),
  category: new TranslatableMarkup('Umami Next'),
)]
final class NotFoundBlock extends EditorialDataBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#theme' => 'umami_next_not_found',
      '#recipe_cards' => $this->editorialData->loadLatestCards('recipe', 3),
      '#cache' => $this->editorialCache(['node_list']),
    ];
  }

}
