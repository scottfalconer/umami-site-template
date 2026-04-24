<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Recipe\Recipe;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Finder\Finder;

/**
 * Tests that the site template conforms to basic requirements.
 *
 * You can customize this test, but generally shouldn't unless you have a
 * specific reason to do so. The requirements for site templates are documented
 * in GET-STARTED.md.
 */
#[RunTestsInSeparateProcesses]
final class RequirementsTest extends KernelTestBase {

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
   * Tests that the site template conforms to basic requirements.
   */
  public function testSiteTemplateRequirements(): void {
    $path = self::getRecipePath();

    // The site template cannot include any code (i.e., modules or themes).
    $finder = Finder::create()->in($path)->exclude('packages')->files()->name('*.info.yml');
    $this->assertCount(0, $finder, "Recipes cannot include any code (modules or themes) of their own; they must list them as dependencies in `composer.json`.");

    // This repository keeps local path repositories under packages/ for
    // development, but those packages must not be included in the released
    // site-template archive.
    $git_attributes = file_get_contents($path . '/.gitattributes');
    $this->assertIsString($git_attributes);
    $this->assertStringContainsString('/packages export-ignore', $git_attributes, 'The local development packages must be excluded from the released recipe archive.');

    // Ensure the recipe's type is correct.
    $this->assertSame('Site', Recipe::createFromDirectory($path)->type, 'The recipe type must be "Site".');

    // Read `composer.json` and ensure it's syntactically valid.
    $file = $path . '/composer.json';
    $this->assertFileExists($file);
    $data = file_get_contents($file);
    $data = Json::decode($data);
    $this->assertIsArray($data);

    // To avoid confusion about what packages are part of Drupal CMS, site
    // templates should never be prefixed with "drupal_cms_" or "drupal-cms-".
    // The only exception is the starter kit.
    [, $name] = explode('/', $data['name'], 2);
    if ($name !== 'drupal_cms_site_template_base') {
      $this->assertStringStartsNotWith('drupal_cms_', $name, 'Site templates should not use the drupal_cms_ prefix in their name.');
      $this->assertStringStartsNotWith('drupal-cms-', $name, 'Site templates should not use the drupal-cms- prefix in their name.');
    }

    // In CI, respect a list of dependencies which are required as dev branches.
    // For example, this is useful for testing the site template against the
    // latest commit of a bespoke theme to which it is strongly coupled.
    $allow_dev = getenv('CI_ALLOW_DEV');
    if (getenv('CI') && $allow_dev) {
      $allow_dev = array_map('trim', explode(',', $allow_dev));
    }
    else {
      $allow_dev = [];
    }

    $install_profiles = InstalledVersions::getInstalledPackagesByType('drupal-profile');
    foreach ($data['require'] ?? [] as $name => $constraint) {
      // Site templates aren't allowed to depend on install profiles.
      $this->assertNotContains($name, $install_profiles, "The site template cannot depend on $name because it is an install profile.");
      // Site templates may not patch dependencies in any way, which includes
      // depending on the cweagans/composer-patches plugin.
      $this->assertNotSame('cweagans/composer-patches', $name, "The site template cannot depend on $name because site templates must not patch dependencies.");

      if (in_array($name, $allow_dev, TRUE)) {
        continue;
      }
      // Use a basic heuristic to detect pinned dependencies, which are never
      // allowed in a site template.
      $this->assertDoesNotMatchRegularExpression('/^v?[0-9]+\./i', $constraint, "The site template cannot pin a specific version of $name.");
    }
    $this->assertArrayNotHasKey('patches', $data['extra'] ?? [], 'Site templates cannot supply or specify patches for dependencies.');
    $this->assertArrayHasKey('drupal/facets', $data['require'] ?? [], 'The site template should explicitly require Facets because Umami owns its search facet configuration.');

    // The site template must identify itself as a recipe.
    $this->assertSame(Recipe::COMPOSER_PROJECT_TYPE, $data['type'], sprintf('The project type must be "%s".', Recipe::COMPOSER_PROJECT_TYPE));

    // Although not a hard technical requirement, it's an extremely good idea
    // for a site template to specify a license.
    $this->assertNotEmpty($data['license'], 'The site template should declare a license.');

    // Ensure that all config shipped by this site template doesn't have the
    // `_core` or (except in certain situations) `uuid` keys.
    $storage = new FileStorage($path . '/config');
    foreach ($storage->listAll() as $name) {
      $data = $storage->read($name);
      // In general, the config shipped by a site template should not have a
      // UUID key. The exception is certain entity types, Canvas folders being
      // the main example, that use their UUID as their main identifier. In such
      // cases, we would expect to see the UUID in the config's name.
      if (isset($data['uuid'])) {
        $this->assertStringContainsString($data['uuid'], $name, "The $name config should contain its UUID in its name.");
      }
      $this->assertArrayNotHasKey('_core', $data, "The $name config should not include a `_core` key.");
    }
  }

  /**
   * Tests guardrails for upstream Drupal CMS recipe composition.
   */
  public function testRecipeCompositionGuardrails(): void {
    $recipe = Yaml::decode(file_get_contents(self::getRecipePath() . '/recipe.yml'));
    $recipes = $recipe['recipes'] ?? [];
    $install = $recipe['install'] ?? [];
    $expected_recipes = [
      'core/recipes/administrator_role',
      'core/recipes/core_recommended_maintenance',
      'core/recipes/core_recommended_performance',
      'drupal_cms_admin_ui',
      'drupal_cms_authentication',
      'drupal_cms_forms',
      'drupal_cms_media',
      'drupal_cms_search',
      '../recipes/umami/recipes/search_index_fields',
      'drupal_cms_seo_basic',
      'easy_email_express',
    ];

    $this->assertSame($expected_recipes, $recipes, 'Umami should compose the lower-level Drupal CMS feature recipes it depends on.');
    $this->assertNotContains('drupal_cms_starter', $recipes, 'Site templates should compose lower-level Drupal CMS recipes directly, not another site template.');
    $this->assertNotContains('drupal_cms_helper', $recipes, 'Drupal CMS Helper is a module dependency, not a top-level feature recipe.');
    $this->assertSame(array_values(array_unique($recipes)), $recipes, 'Recipe composition should not list duplicate recipes.');
    $this->assertNotContains('search_api', $install, 'Search API should be installed by the Drupal CMS Search recipe, not duplicated in the flat install list.');
    $this->assertNotContains('search_api_db', $install, 'Search API DB should be installed by the Drupal CMS Search recipe, not duplicated in the flat install list.');
    $this->assertNotContains('search_api_exclude', $install, 'Search API Exclude should be installed by the Drupal CMS Search recipe, not duplicated in the flat install list.');
    $this->assertNotContains('canvas_stark', $install, 'Canvas Stark should be installed by the Drupal CMS Search recipe, not duplicated in the flat install list.');
    $this->assertNotContains('webform', $install, 'Webform should be installed by the Drupal CMS Forms recipe, not duplicated in the flat install list.');
    $this->assertNotContains('gin', $install, 'Gin should be installed by the Drupal CMS Admin UI recipe, not duplicated in the flat install list.');
    $this->assertContains('canvas', $install, 'Canvas should stay explicit because Umami owns Canvas pages, templates, and components.');
    $this->assertContains('facets', $install, 'Facets should stay explicit because Umami owns the public search facet configuration.');
    $this->assertSame(array_values(array_unique($install)), $install, 'The flat install list should not contain duplicates.');
  }

}
