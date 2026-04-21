<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Stores one structured recipe method step.
 */
#[FieldType(
  id: 'umami_method_step',
  label: new TranslatableMarkup('Recipe method step'),
  description: new TranslatableMarkup('Stores a method step title and instruction as separate values.'),
  category: 'plain_text',
  default_widget: 'umami_method_step',
  default_formatter: 'umami_method_step',
)]
final class MethodStepItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['step_title'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Step title'));
    $properties['instruction'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Instruction'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'step_title' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'instruction' => [
          'type' => 'text',
          'size' => 'normal',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return trim((string) $this->get('step_title')->getValue()) === ''
      && trim((string) $this->get('instruction')->getValue()) === '';
  }

}
