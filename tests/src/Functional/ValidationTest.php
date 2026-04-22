<?php

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;
use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\canvas\JsonSchemaDefinitionsStreamwrapper;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that this site template can be applied without errors.
 *
 * All deprecation notices triggered by the recipe's dependencies will be
 * displayed. To suppress them, add the
 * \PHPUnit\Framework\Attributes\IgnoreDeprecations attribute to this class.
 */
#[RunTestsInSeparateProcesses]
class ValidationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Returns the absolute path of the recipe this test is for.
   *
   * @return string
   *   The absolute path of the recipe.
   */
  protected static function getRecipePath(): string {
    $path = dirname(__FILE__, 4);
    $installed_path = dirname($path) . '/recipes/umami';
    return is_file($installed_path . '/recipe.yml') ? $installed_path : $path;
  }

  /**
   * {@inheritdoc}
   */
  protected function installParameters(): array {
    $install = parent::installParameters();
    $install['parameters']['recipe'] = self::getRecipePath();
    return $install;
  }

  /**
   * Tests that installed Canvas trees only reference shipped components.
   */
  public function testInstalledCanvasComponentsAreIncluded(): void {
    // If this site template uses Canvas, it is a best practice for it to ship
    // `canvas.component.*.yml` files for every component that it actually uses
    // in content templates, page regions, patterns, landing pages, etc. This
    // method checks for that.
    $this->assertCanvasComponentsAreIncluded();
    $this->assertHomepageUsesEditorialRecipeCuration();
  }

  /**
   * Checks that the site template includes all Canvas components that it uses.
   */
  protected function assertCanvasComponentsAreIncluded(): void {
    // Examine all entities that implement
    // \Drupal\canvas\Entity\ComponentTreeEntityInterface.
    $entity_types = array_filter(
      \Drupal::entityTypeManager()->getDefinitions(),
      fn ($entity_type): bool => $entity_type->entityClassImplements(ComponentTreeEntityInterface::class),
    );

    $included_components = (new FileStorage(self::getRecipePath() . '/config'))
      ->listAll('canvas.component.');

    foreach ($entity_types as $entity_type) {
      $entities = \Drupal::entityTypeManager()
        ->getStorage($entity_type->id())
        ->loadMultiple();

      foreach ($entities as $entity) {
        $this->assertInstanceOf(ComponentTreeEntityInterface::class, $entity);
        /** @var \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem $item */
        foreach ($entity->getComponentTree() as $item) {
          $component = $item->getComponent()?->getConfigDependencyName();
          if ($component) {
            $this->assertContains($component, $included_components, 'The site template should include this component in its configuration.');
          }
        }
      }
    }
  }

  /**
   * Checks that homepage recipe curation comes from installed content flags.
   */
  protected function assertHomepageUsesEditorialRecipeCuration(): void {
    $front_page = \Drupal::config('system.site')->get('page.front');
    if ($front_page === '/home') {
      $front_alias = $front_page;
    }
    else {
      $front_alias = \Drupal::service('path_alias.repository')
        ->lookupBySystemPath($front_page, 'en')['alias'] ?? NULL;
    }
    $this->assertSame('/home', $front_alias);

    $featured_cards = \Drupal::service('umami_next.editorial_data')
      ->loadFeaturedCards('recipe', 6);
    $this->assertSame([
      'Slow-Roasted Tomato Pappardelle',
      'Sticky Sesame Aubergine',
      'Weeknight Dal with Burnt Butter',
      'Olive Oil Citrus Cake',
      'Ribollita, in an Honest Mood',
      'Plum and Almond Galette',
    ], array_column($featured_cards, 'title'));
  }

  /**
   * {@inheritdoc}
   */
  protected function rebuildAll(): void {
    // The rebuild won't succeed without the `json-schema-definitions` stream
    // wrapper. This would normally happen automatically whenever a module is
    // installed, but in this case, all of that has taken place in a separate
    // process, so we need to refresh *this* process manually.
    // @see canvas_module_preinstall()
    \Drupal::service('stream_wrapper_manager')->registerWrapper(
      'json-schema-definitions',
      JsonSchemaDefinitionsStreamwrapper::class,
      JsonSchemaDefinitionsStreamwrapper::getType(),
    );
    parent::rebuildAll();
  }

}
