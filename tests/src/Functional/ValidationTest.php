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
  protected $defaultTheme = 'umami_next_theme';

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
    $this->assertEditorialRolesAreScoped();
    $this->assertRecipeAttributionMatchesCoreUmami();

    $routes = [
      '/' => NULL,
      '/recipes' => 'Recipes',
      '/stories' => 'Stories',
      '/search' => 'Search',
      '/contact' => 'Contact',
      '/collections/spring-market' => 'The Spring Market',
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

    $this->assertContactFormsRenderConfiguredElements();
    $this->assertUnpublishedContentReturns404WithoutEcaError();
    $this->assertSitemapIncludesPublicLandingPages();
    $this->assertSearchPageExposesSearchForm();
  }

  /**
   * Checks that recipe attribution stays close to Drupal core Umami.
   */
  protected function assertRecipeAttributionMatchesCoreUmami(): void {
    $recipe_ids = \Drupal::entityQuery('node')
      ->condition('type', 'recipe')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();
    $this->assertCount(10, $recipe_ids);

    $field_definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', 'recipe');
    $this->assertArrayNotHasKey('field_recipe_credit', $field_definitions);
    $this->assertArrayNotHasKey('field_media_credit', $field_definitions);

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    foreach ($node_storage->loadMultiple($recipe_ids) as $node) {
      $this->assertSame('Umami', $node->getOwner()->getAccountName());
    }

    $this->drupalGet('/recipe/deep-mediterranean-quiche');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('By Umami');
    $this->assertSession()->pageTextNotContains('Original Umami demo recipe');
    $this->assertSession()->responseNotContains('Photo:');
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
      'Deep mediterranean quiche',
      'Vegan chocolate and nut brownies',
      'Super easy vegetarian pasta bake',
      'Watercress soup',
      'Victoria sponge cake',
      'Gluten free pizza',
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
   * Checks that editorial and site-building permissions stay separated.
   */
  protected function assertEditorialRolesAreScoped(): void {
    $content_editor = \Drupal\user\Entity\Role::load('content_editor');
    $this->assertNotNull($content_editor);
    $this->assertFalse($content_editor->isAdmin());

    foreach ([
      'create article content',
      'create collection content',
      'create page content',
      'create recipe content',
      'edit any article content',
      'edit any collection content',
      'edit any page content',
      'edit any recipe content',
      'create canvas_page',
      'edit canvas_page',
      'use basic_editorial transition publish',
    ] as $permission) {
      $this->assertTrue($content_editor->hasPermission($permission), "Content editors should have `$permission`.");
    }

    foreach ([
      'administer menu',
      'administer redirects',
      'administer url aliases',
    ] as $permission) {
      $this->assertFalse($content_editor->hasPermission($permission), "Content editors should not have site-builder permission `$permission`.");
    }

    $site_builder = \Drupal\user\Entity\Role::load('site_builder');
    $this->assertNotNull($site_builder);
    $this->assertFalse($site_builder->isAdmin());

    foreach ([
      'administer menu',
      'administer redirects',
      'administer url aliases',
    ] as $permission) {
      $this->assertTrue($site_builder->hasPermission($permission), "Site builders should have `$permission`.");
    }
  }

  /**
   * Checks that the public contact page uses Umami's form shape.
   */
  protected function assertContactFormsRenderConfiguredElements(): void {
    $this->drupalGet('/contact');
    $this->assertSession()->statusCodeEquals(200);

    foreach (['name', 'email', 'subject', 'message'] as $field_name) {
      $this->assertSession()->fieldExists($field_name);
    }
    $this->assertSession()->buttonExists('Send message');
    $this->assertSession()->buttonExists('Subscribe');
    $this->assertSession()->pageTextNotContains('CAPTCHA');
    $this->assertSession()->responseNotContains('${site_uuid}');
    $this->assertSession()->responseNotContains('frc-captcha');
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
   * Checks that review-facing landing pages are included in the sitemap.
   */
  protected function assertSitemapIncludesPublicLandingPages(): void {
    $this->drupalGet('/sitemap.xml');
    $this->assertSession()->statusCodeEquals(200);

    foreach (['/', '/recipes', '/stories', '/contact'] as $path) {
      $this->assertSession()->responseMatches('#<loc>https?://[^<]+' . preg_quote($path, '#') . '</loc>#');
    }
  }

  /**
   * Checks that the public search page is usable after recipe install.
   */
  protected function assertSearchPageExposesSearchForm(): void {
    $this->drupalGet('/search');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'h1');

    $search_view = \Drupal::config('views.view.search');
    $this->assertSame('keywords', $search_view->get('display.default.display_options.filters.search_api_fulltext.expose.identifier'));
    $this->assertTrue($search_view->get('display.page.display_options.exposed_block'));

    $search_block = \Drupal::config('block.block.umami_next_theme_search_exposed_form');
    $this->assertSame('views_exposed_filter_block:search-page', $search_block->get('plugin'));
    $this->assertSame('/search', $search_block->get('visibility.request_path.pages'));

    $index = \Drupal::config('search_api.index.content');
    $facet_fields = [
      'field_cuisine' => 'Cuisine',
      'field_dietary' => 'Dietary',
      'field_recipe_category' => 'Recipe category',
      'field_topics' => 'Topics',
      'field_cook_minutes' => 'Cook minutes',
    ];
    foreach ($facet_fields as $field => $label) {
      $this->assertSame($label, $index->get("field_settings.$field.label"));
    }

    $facets = [
      'cuisine' => 'Cuisine',
      'dietary' => 'Dietary',
      'recipe_category' => 'Recipe category',
      'topics' => 'Topics',
      'cook_time' => 'Cook time',
    ];
    foreach ($facets as $id => $label) {
      $facet = \Drupal::config("facets.facet.$id");
      $this->assertSame($label, $facet->get('name'));
      $this->assertSame('search_api:views_page__search__page', $facet->get('facet_source_id'));

      $block = \Drupal::config("block.block.umami_next_theme_facet_$id");
      $this->assertSame("facet_block:$id", $block->get('plugin'));
      $this->assertSame('/search', $block->get('visibility.request_path.pages'));
    }

    /** @var \Drupal\search_api\IndexInterface $content_index */
    $content_index = \Drupal::entityTypeManager()
      ->getStorage('search_api_index')
      ->load('content');
    $this->assertNotNull($content_index);
    $item_ids = [];
    foreach ($content_index->getDatasourceIds() as $datasource_id) {
      foreach ($content_index->getDatasource($datasource_id)->getItemIds() ?? [] as $raw_id) {
        $item_ids[] = \Drupal\search_api\Utility\Utility::createCombinedId($datasource_id, $raw_id);
      }
    }
    $items = $content_index->loadItemsMultiple($item_ids);
    $this->assertNotEmpty($items);
    $this->assertNotEmpty($content_index->indexSpecificItems($items));

    $this->drupalGet('/search', ['query' => ['keywords' => 'tomato']]);
    $this->assertSession()->statusCodeEquals(200);
    foreach ($facets as $label) {
      $this->assertSession()->pageTextContains($label);
    }
    $this->assertSession()->elementExists('css', '#block-umami-next-theme-facet-cuisine [data-drupal-facet-filter-value^="cuisine:"]');
    $this->assertSession()->elementExists('css', '#block-umami-next-theme-facet-cook-time [data-drupal-facet-filter-value^="cook_time:"]');
    $this->assertSession()->pageTextContains('Deep mediterranean quiche');
    $this->assertSession()->pageTextNotContains('Not Found');
    $this->assertSession()->responseNotContains('href="/404"');

    $italian_term_ids = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'cuisine')
      ->condition('name', 'Italian')
      ->accessCheck(FALSE)
      ->execute();
    $this->assertNotEmpty($italian_term_ids);

    $this->drupalGet('/search', [
      'query' => [
        'keywords' => 'tomato',
        'f' => ['cuisine:' . reset($italian_term_ids)],
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '#block-umami-next-theme-facet-cuisine.facet-active');
    $this->assertSession()->pageTextContains('Deep mediterranean quiche');
    $this->assertSession()->elementNotExists('css', 'a[href="/recipe/thai-green-curry"]');
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
