<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Topic browse section for the homepage.
 */
#[Block(
  id: 'umami_next_topic_browse',
  admin_label: new TranslatableMarkup('Umami Next topic browse'),
  category: new TranslatableMarkup('Umami Next'),
)]
final class TopicBrowseBlock extends EditorialDataBlockBase {

  /**
   * {@inheritdoc}
   */
  protected function editableSettingDefinitions(): array {
    return [
      'eyebrow' => [
        'title' => $this->t('Eyebrow'),
        'default' => 'Browse',
        'maxlength' => 80,
      ],
      'section_title' => [
        'title' => $this->t('Section title'),
        'default' => 'By way of the kitchen',
        'maxlength' => 120,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#theme' => 'umami_next_topic_browse_block',
      '#topics' => $this->editorialData->buildTopicTiles(6),
      '#eyebrow' => $this->configuration['eyebrow'],
      '#section_title' => $this->configuration['section_title'],
      '#cache' => $this->editorialCache(['node_list', 'taxonomy_term_list']),
    ];
  }

}
