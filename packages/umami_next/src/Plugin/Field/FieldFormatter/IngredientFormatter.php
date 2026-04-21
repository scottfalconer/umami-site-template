<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Displays grouped recipe ingredients.
 */
#[FieldFormatter(
  id: 'umami_ingredient',
  label: new TranslatableMarkup('Grouped ingredient list'),
  field_types: ['umami_ingredient'],
)]
final class IngredientFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $groups = [];

    foreach ($items as $item) {
      $values = $item->getValue();
      $ingredient = trim((string) ($values['ingredient'] ?? ''));
      if ($ingredient === '') {
        continue;
      }
      $group_label = trim((string) ($values['group_label'] ?? ''));
      $groups[$group_label][] = [
        'quantity' => trim((string) ($values['quantity'] ?? '')),
        'ingredient' => $ingredient,
      ];
    }

    if (!$groups) {
      return [];
    }

    $elements = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ingredients-list']],
    ];

    foreach ($groups as $group_label => $ingredients) {
      $group = [
        '#type' => 'container',
        '#attributes' => ['class' => ['ingredients__group']],
      ];
      if ($group_label !== '') {
        $group['label'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $group_label,
          '#attributes' => ['class' => ['ingredients__label']],
        ];
      }
      $group['items'] = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => array_map(static fn (array $ingredient): array => [
          '#type' => 'inline_template',
          '#template' => '{% if quantity %}<span class="ingredients__qty">{{ quantity }}</span>{% endif %}<span>{{ ingredient }}</span>',
          '#context' => $ingredient,
        ], $ingredients),
      ];
      $elements[] = $group;
    }

    return [0 => $elements];
  }

}
