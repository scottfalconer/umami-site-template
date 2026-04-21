<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Latest stories section for the homepage.
 */
#[Block(
  id: 'umami_next_latest_stories',
  admin_label: new TranslatableMarkup('Umami Next latest stories'),
  category: new TranslatableMarkup('Umami Next'),
)]
final class LatestStoriesBlock extends EditorialDataBlockBase {

  /**
   * {@inheritdoc}
   */
  protected function editableSettingDefinitions(): array {
    return [
      'stories_eyebrow' => [
        'title' => $this->t('Stories eyebrow'),
        'default' => 'Stories',
        'maxlength' => 80,
      ],
      'stories_title' => [
        'title' => $this->t('Stories title'),
        'default' => 'Essays from the kitchen',
        'maxlength' => 120,
      ],
      'stories_cta_label' => [
        'title' => $this->t('Stories call-to-action label'),
        'default' => 'All stories',
        'maxlength' => 80,
      ],
      'picks_eyebrow' => [
        'title' => $this->t('Editor picks eyebrow'),
        'default' => "Editor's picks",
        'maxlength' => 80,
      ],
      'picks_title' => [
        'title' => $this->t('Editor picks title'),
        'default' => 'Three we keep coming back to',
        'maxlength' => 120,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#theme' => 'umami_next_latest_stories_block',
      '#latest_stories' => $this->editorialData->loadLatestCards('article', 4),
      '#editors_picks' => $this->editorialData->loadRecipeCardsByUuids([
        'e832f942-2282-4dd3-b804-1d85255a0f99',
        'be17db54-41fe-481a-8e2b-88673f537aa3',
        'f0a5cac7-c43b-47cf-b278-0f22d7514808',
      ]),
      '#stories_eyebrow' => $this->configuration['stories_eyebrow'],
      '#stories_title' => $this->configuration['stories_title'],
      '#stories_cta_label' => $this->configuration['stories_cta_label'],
      '#picks_eyebrow' => $this->configuration['picks_eyebrow'],
      '#picks_title' => $this->configuration['picks_title'],
      '#cache' => $this->editorialCache(['node_list', 'user_list']),
    ];
  }

}
