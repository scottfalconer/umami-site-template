<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Widget for structured recipe ingredient items.
 */
#[FieldWidget(
  id: 'umami_ingredient',
  label: new TranslatableMarkup('Recipe ingredient'),
  field_types: ['umami_ingredient'],
)]
final class IngredientWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $item = $items[$delta] ?? NULL;

    $element['#type'] = 'fieldset';
    $element['#title'] = $this->t('Ingredient @number', ['@number' => $delta + 1]);

    $element['group_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group'),
      '#default_value' => $item?->group_label,
      '#maxlength' => 128,
      '#description' => $this->t('Optional section label, such as Dough or Filling. Leave blank to keep the previous grouping.'),
    ];
    $element['quantity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Quantity'),
      '#default_value' => $item?->quantity,
      '#maxlength' => 128,
      '#description' => $this->t('Amount or note, such as 2 tbsp, 400 g, or to serve.'),
    ];
    $element['ingredient'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ingredient'),
      '#default_value' => $item?->ingredient,
      '#maxlength' => 512,
    ];

    return $element;
  }

}
