<?php

declare(strict_types=1);

namespace Drupal\umami_next\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\umami_next\EditorialDataBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for blocks backed by editorial data queries.
 */
abstract class EditorialDataBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Creates a new block instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EditorialDataBuilder $editorialData,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('umami_next.editorial_data'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + $this->editableDefaults();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $definitions = $this->editableSettingDefinitions();
    if (!$definitions) {
      return $form;
    }

    $form['editorial_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Editorial settings'),
      '#open' => TRUE,
      '#description' => $this->t('These values control the section copy shown by this Canvas layer. The cards and listings remain powered by structured Drupal content.'),
    ];

    foreach ($definitions as $key => $definition) {
      $form['editorial_settings'][$key] = [
        '#type' => $definition['type'] ?? 'textfield',
        '#title' => $definition['title'],
        '#default_value' => $this->configuration[$key] ?? ($definition['default'] ?? ''),
        '#description' => $definition['description'] ?? NULL,
        '#required' => $definition['required'] ?? FALSE,
      ];
      if (isset($definition['maxlength'])) {
        $form['editorial_settings'][$key]['#maxlength'] = $definition['maxlength'];
      }
      if (isset($definition['rows'])) {
        $form['editorial_settings'][$key]['#rows'] = $definition['rows'];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    foreach (array_keys($this->editableSettingDefinitions()) as $key) {
      $this->configuration[$key] = (string) $form_state->getValue([
        'editorial_settings',
        $key,
      ], '');
    }
  }

  /**
   * Returns editable setting definitions for this block's Canvas form.
   *
   * @return array<string, array<string, mixed>>
   *   Form element metadata keyed by configuration key.
   */
  protected function editableSettingDefinitions(): array {
    return [];
  }

  /**
   * Returns editable setting defaults keyed by configuration key.
   *
   * @return array<string, string>
   *   Default configuration values.
   */
  protected function editableDefaults(): array {
    return array_map(
      static fn (array $definition): string => (string) ($definition['default'] ?? ''),
      $this->editableSettingDefinitions(),
    );
  }

  /**
   * Builds cache metadata for output derived from content entity access checks.
   *
   * @param string[] $tags
   *   Cache tags to include.
   *
   * @return array<string, string[]>
   *   Render cache metadata.
   */
  protected function editorialCache(array $tags): array {
    return [
      'tags' => $tags,
      'contexts' => [
        'user.node_grants:view',
        'user.permissions',
      ],
    ];
  }

}
