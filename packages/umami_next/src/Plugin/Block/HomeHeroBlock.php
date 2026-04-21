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
  protected function editableSettingDefinitions(): array {
    return [
      'issue_label' => [
        'title' => $this->t('Issue label'),
        'default' => 'Issue No. 14 - April 2026',
        'maxlength' => 120,
      ],
      'headline_prefix' => [
        'title' => $this->t('Headline prefix'),
        'default' => 'A letter from the',
        'maxlength' => 80,
      ],
      'headline_emphasis' => [
        'title' => $this->t('Headline emphasized word'),
        'default' => 'spring',
        'maxlength' => 40,
      ],
      'headline_suffix' => [
        'title' => $this->t('Headline suffix'),
        'default' => 'market.',
        'maxlength' => 80,
      ],
      'cta_label' => [
        'title' => $this->t('Call-to-action label'),
        'default' => 'Read the letter',
        'maxlength' => 80,
      ],
      'image_caption' => [
        'title' => $this->t('Image caption'),
        'default' => 'Photographed by Jun Watanabe, Saturday market, 04.2026',
        'maxlength' => 160,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $hero_node = $this->editorialData->loadFeaturedNode('article');

    return [
      '#theme' => 'umami_next_home_hero_block',
      '#hero' => $hero_node ? $this->editorialData->buildArticleCard($hero_node, TRUE) : NULL,
      '#hero_author' => $hero_node ? $this->editorialData->buildAuthor($hero_node->getOwner()) : NULL,
      '#issue_label' => $this->configuration['issue_label'],
      '#headline_prefix' => $this->configuration['headline_prefix'],
      '#headline_emphasis' => $this->configuration['headline_emphasis'],
      '#headline_suffix' => $this->configuration['headline_suffix'],
      '#cta_label' => $this->configuration['cta_label'],
      '#image_caption' => $this->configuration['image_caption'],
      '#cache' => $this->editorialCache(['node_list', 'user_list']),
    ];
  }

}
