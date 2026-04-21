<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Widget for structured recipe method steps.
 */
#[FieldWidget(
  id: 'umami_method_step',
  label: new TranslatableMarkup('Recipe method step'),
  field_types: ['umami_method_step'],
)]
final class MethodStepWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $item = $items[$delta] ?? NULL;

    $element['#type'] = 'fieldset';
    $element['#title'] = $this->t('Step @number', ['@number' => $delta + 1]);

    $element['step_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Step title'),
      '#default_value' => $item?->step_title,
      '#maxlength' => 255,
      '#description' => $this->t('Short action label, such as Mix, Roast, or Finish.'),
    ];
    $element['instruction'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Instruction'),
      '#default_value' => $item?->instruction,
      '#rows' => 3,
    ];

    return $element;
  }

}
