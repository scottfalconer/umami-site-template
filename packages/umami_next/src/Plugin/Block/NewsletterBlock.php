<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Newsletter signup section.
 */
#[Block(
  id: 'umami_next_newsletter',
  admin_label: new TranslatableMarkup('Umami Next newsletter'),
  category: new TranslatableMarkup('Umami Next'),
)]
final class NewsletterBlock extends EditorialDataBlockBase {

  /**
   * {@inheritdoc}
   */
  protected function editableSettingDefinitions(): array {
    return [
      'eyebrow' => [
        'title' => $this->t('Eyebrow'),
        'default' => 'The weekly dispatch',
        'maxlength' => 80,
      ],
      'section_title' => [
        'title' => $this->t('Section title'),
        'default' => 'A small, useful letter on cooking and the season.',
        'maxlength' => 140,
      ],
      'description' => [
        'title' => $this->t('Description'),
        'type' => 'textarea',
        'default' => 'Sent Sunday mornings. One recipe, one short essay, and a few things we have been eating.',
        'rows' => 3,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#theme' => 'umami_next_newsletter_block',
      '#newsletter' => $this->editorialData->buildWebform('newsletter_signup'),
      '#eyebrow' => $this->configuration['eyebrow'],
      '#section_title' => $this->configuration['section_title'],
      '#description' => $this->configuration['description'],
      '#cache' => $this->editorialCache(['config:webform_list']),
    ];
  }

}
