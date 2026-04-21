<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Spotlight collection section for the homepage.
 */
#[Block(
  id: 'umami_next_spotlight_collection',
  admin_label: new TranslatableMarkup('Umami Next spotlight collection'),
  category: new TranslatableMarkup('Umami Next'),
)]
final class SpotlightCollectionBlock extends EditorialDataBlockBase {

  /**
   * {@inheritdoc}
   */
  protected function editableSettingDefinitions(): array {
    return [
      'eyebrow' => [
        'title' => $this->t('Eyebrow'),
        'default' => 'Collection',
        'maxlength' => 80,
      ],
      'cta_label' => [
        'title' => $this->t('Call-to-action label'),
        'default' => 'Open the collection',
        'maxlength' => 80,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $spotlight = $this->editorialData->loadFeaturedNode('collection');

    return [
      '#theme' => 'umami_next_spotlight_collection_block',
      '#spotlight' => $spotlight ? $this->editorialData->buildCollectionCard($spotlight) : NULL,
      '#eyebrow' => $this->configuration['eyebrow'],
      '#cta_label' => $this->configuration['cta_label'],
      '#cache' => $this->editorialCache(['node_list']),
    ];
  }

}
