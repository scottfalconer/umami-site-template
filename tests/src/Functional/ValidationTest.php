<?php

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Logger\RfcLogLevel;
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
   * Tests that the public discovery routes load after a recipe install.
   */
  public function testPublicRoutesLoad(): void {
    $this->assertSame(
      'A food magazine with recipes, stories, and collections.',
      \Drupal::config('system.site')->get('slogan'),
    );
    $this->assertNotSame('America/Costa_Rica', \Drupal::config('system.date')->get('timezone.default'));
    $this->assertEditorialWorkflowCoversPrimaryBundles();

    $routes = [
      '/' => NULL,
      '/recipes' => 'Recipes',
      '/stories' => 'Stories',
      '/search' => 'Search',
      '/contact' => 'Contact',
      '/sitemap.xml' => '<urlset',
    ];

    foreach ($routes as $path => $expected_text) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(200);
      if ($expected_text) {
        $this->assertSession()->responseContains($expected_text);
      }
    }

    $term_ids = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'topic')
      ->condition('field_slug', 'weeknight')
      ->accessCheck(FALSE)
      ->execute();
    $this->assertNotEmpty($term_ids);
    $term_id = (int) reset($term_ids);

    $alias = \Drupal::service('path_alias.repository')
      ->lookupBySystemPath('/taxonomy/term/' . $term_id, 'en')['alias'] ?? NULL;
    $this->assertSame('/topic/weeknight', $alias);

    $this->drupalGet($alias);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Weeknight');

    $this->assertUnpublishedContentReturns404WithoutEcaError();
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
   * Checks that editorial workflow config governs all primary node bundles.
   */
  protected function assertEditorialWorkflowCoversPrimaryBundles(): void {
    $workflow_config = \Drupal::config('workflows.workflow.basic_editorial')
      ->get('type_settings.entity_types.node');
    $this->assertSame(['article', 'collection', 'page', 'recipe'], $workflow_config);

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node_ids = \Drupal::entityQuery('node')
      ->condition('type', ['article', 'collection', 'recipe'], 'IN')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();
    $this->assertNotEmpty($node_ids);

    foreach ($node_storage->loadMultiple($node_ids) as $node) {
      $this->assertSame('published', $node->get('moderation_state')->value);
    }
  }

  /**
   * Checks that unpublished public requests stay quiet in operational logs.
   */
  protected function assertUnpublishedContentReturns404WithoutEcaError(): void {
    $this->drupalGet('/privacy-policy');
    $this->assertSession()->statusCodeEquals(404);

    $eca_errors = (int) \Drupal::database()
      ->select('watchdog', 'w')
      ->condition('type', 'eca')
      ->condition('severity', RfcLogLevel::ERROR)
      ->condition('message', '%unpublished_404%', 'LIKE')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertSame(0, $eca_errors);
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
