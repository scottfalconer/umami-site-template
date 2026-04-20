<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Site-wide editorial promo strip.
 */
#[Block(
  id: 'umami_next_promo_strip',
  admin_label: new TranslatableMarkup('Umami Next promo strip'),
  category: new TranslatableMarkup('Umami Next'),
)]
final class PromoStripBlock extends EditorialDataBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#theme' => 'umami_next_promo_strip_block',
      '#promo' => $this->editorialData->buildPromoLink(),
      '#cache' => $this->editorialCache(['node_list']),
    ];
  }

}
