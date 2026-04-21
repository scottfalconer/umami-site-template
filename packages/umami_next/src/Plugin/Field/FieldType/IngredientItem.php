<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Stores one structured recipe ingredient.
 */
#[FieldType(
  id: 'umami_ingredient',
  label: new TranslatableMarkup('Recipe ingredient'),
  description: new TranslatableMarkup('Stores an ingredient group, quantity, and ingredient text as separate values.'),
  category: 'plain_text',
  default_widget: 'umami_ingredient',
  default_formatter: 'umami_ingredient',
)]
final class IngredientItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['group_label'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Group label'));
    $properties['quantity'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Quantity'));
    $properties['ingredient'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Ingredient'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'group_label' => [
          'type' => 'varchar',
          'length' => 128,
        ],
        'quantity' => [
          'type' => 'varchar',
          'length' => 128,
        ],
        'ingredient' => [
          'type' => 'varchar',
          'length' => 512,
        ],
      ],
      'indexes' => [
        'group_label' => ['group_label'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return trim((string) $this->get('ingredient')->getValue()) === ''
      && trim((string) $this->get('quantity')->getValue()) === ''
      && trim((string) $this->get('group_label')->getValue()) === '';
  }

}
