<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Displays structured recipe method steps.
 */
#[FieldFormatter(
  id: 'umami_method_step',
  label: new TranslatableMarkup('Ordered method steps'),
  field_types: ['umami_method_step'],
)]
final class MethodStepFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $steps = [];

    foreach ($items as $item) {
      $values = $item->getValue();
      $title = trim((string) ($values['step_title'] ?? ''));
      $instruction = trim((string) ($values['instruction'] ?? ''));
      if ($title === '' && $instruction === '') {
        continue;
      }
      $steps[] = [
        'title' => $title,
        'instruction' => $instruction,
      ];
    }

    if (!$steps) {
      return [];
    }

    return [
      0 => [
        '#theme' => 'item_list',
        '#list_type' => 'ol',
        '#attributes' => ['class' => ['method-steps']],
        '#items' => array_map(static fn (array $step): array => [
          '#type' => 'inline_template',
          '#template' => '{% if title %}<div class="step-title">{{ title }}</div>{% endif %}<div>{{ instruction|nl2br }}</div>',
          '#context' => $step,
        ], $steps),
      ],
    ];
  }

}
