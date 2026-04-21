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
  protected function editableSettingDefinitions(): array {
    return [
      'headline' => [
        'title' => $this->t('Headline'),
        'default' => 'This page has gone to the market.',
        'maxlength' => 120,
      ],
      'description' => [
        'title' => $this->t('Description'),
        'type' => 'textarea',
        'default' => 'It will be back Sunday with basil. In the meantime, here are a few recipes to stand in.',
        'rows' => 3,
      ],
      'home_label' => [
        'title' => $this->t('Home link label'),
        'default' => 'Home',
        'maxlength' => 80,
      ],
      'recipes_label' => [
        'title' => $this->t('Recipes link label'),
        'default' => 'Browse recipes',
        'maxlength' => 80,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#theme' => 'umami_next_not_found',
      '#recipe_cards' => $this->editorialData->loadLatestCards('recipe', 3),
      '#headline' => $this->configuration['headline'],
      '#description' => $this->configuration['description'],
      '#home_label' => $this->configuration['home_label'],
      '#recipes_label' => $this->configuration['recipes_label'],
      '#cache' => $this->editorialCache(['node_list']),
    ];
  }

}
