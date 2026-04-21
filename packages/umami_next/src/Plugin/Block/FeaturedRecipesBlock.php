<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Featured recipe grid for the homepage.
 */
#[Block(
  id: 'umami_next_featured_recipes',
  admin_label: new TranslatableMarkup('Umami Next featured recipes'),
  category: new TranslatableMarkup('Umami Next'),
)]
final class FeaturedRecipesBlock extends EditorialDataBlockBase {

  /**
   * {@inheritdoc}
   */
  protected function editableSettingDefinitions(): array {
    return [
      'eyebrow' => [
        'title' => $this->t('Eyebrow'),
        'default' => 'From the test kitchen',
        'maxlength' => 80,
      ],
      'section_title' => [
        'title' => $this->t('Section title'),
        'default' => "Recipes we're cooking this week",
        'maxlength' => 120,
      ],
      'cta_label' => [
        'title' => $this->t('Call-to-action label'),
        'default' => 'All recipes',
        'maxlength' => 80,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#theme' => 'umami_next_featured_recipes_block',
      '#featured_recipes' => $this->editorialData->loadRecipeCardsByUuids([
        '36a35a55-76f6-4e39-baf8-31798f2db0aa',
        'ac36ac60-a124-4153-a2b9-671173cac6fb',
        '571994c9-f7dd-432c-8762-bb5ab5c94506',
      ]),
      '#eyebrow' => $this->configuration['eyebrow'],
      '#section_title' => $this->configuration['section_title'],
      '#cta_label' => $this->configuration['cta_label'],
      '#cache' => $this->editorialCache(['node_list']),
    ];
  }

}
