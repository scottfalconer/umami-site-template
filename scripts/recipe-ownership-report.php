#!/usr/bin/env php
<?php

/**
 * Builds a module ownership report for the Umami site-template recipe.
 *
 * This intentionally uses a small recipe.yml reader instead of depending on a
 * Composer autoloader, so it can run from the source checkout as well as from a
 * copied tester directory.
 */

const DEFAULT_CANDIDATE_RECIPES = [
  'core/recipes/administrator_role',
  'core/recipes/core_recommended_maintenance',
  'core/recipes/core_recommended_performance',
  'drupal_cms_admin_ui',
  'drupal_cms_authentication',
  'drupal_cms_forms',
  'drupal_cms_media',
  'drupal_cms_search',
  'drupal_cms_seo_basic',
  'easy_email_express',
];

const FOUNDATION_EXTENSIONS = [
  'automated_cron',
  'big_pipe',
  'block',
  'breakpoint',
  'canvas',
  'ckeditor5',
  'claro',
  'config',
  'content_moderation',
  'datetime',
  'dynamic_page_cache',
  'editor',
  'field',
  'field_ui',
  'file',
  'filter',
  'image',
  'inline_form_errors',
  'layout_builder',
  'layout_discovery',
  'link',
  'media',
  'media_library',
  'menu_link_content',
  'mysql',
  'node',
  'options',
  'page_cache',
  'path',
  'path_alias',
  'stark',
  'system',
  'taxonomy',
  'text',
  'user',
  'views',
  'workflows',
];

const UMAMI_EXTENSIONS = [
  'umami_next',
  'umami_next_theme',
];

const ADVANCED_SEO_EXTENSIONS = [
  'metatag',
  'metatag_open_graph',
  'metatag_twitter_cards',
  'redirect_404',
  'schema_article',
  'schema_metatag',
  'simple_sitemap',
];

/**
 * Prints usage details.
 */
function usage() {
  fwrite(STDERR, "Usage: php scripts/recipe-ownership-report.php [options]\n\n");
  fwrite(STDERR, "Options:\n");
  fwrite(STDERR, "  --recipe=PATH                  Recipe file to inspect. Defaults to recipe.yml.\n");
  fwrite(STDERR, "  --upstream-recipes-dir=PATH    Directory containing contrib recipe folders.\n");
  fwrite(STDERR, "                                 Can be repeated.\n");
  fwrite(STDERR, "  --drupal-root=PATH             Drupal web root or project root for module\n");
  fwrite(STDERR, "                                 config/install lookup.\n");
  fwrite(STDERR, "  --candidate-recipes=A,B,C      Recipe names to evaluate. Defaults to Umami's\n");
  fwrite(STDERR, "                                 intended upstream ownership target list.\n");
  fwrite(STDERR, "  --help                         Show this help.\n\n");
}

$options = [
  'recipe' => 'recipe.yml',
  'upstream-recipes-dir' => [],
  'drupal-root' => NULL,
  'candidate-recipes' => NULL,
];

foreach (array_slice($argv, 1) as $argument) {
  if ($argument === '--help' || $argument === '-h') {
    usage();
    exit(0);
  }
  if (!startsWith($argument, '--') || strpos($argument, '=') === FALSE) {
    fwrite(STDERR, "Unknown argument: $argument\n");
    usage();
    exit(1);
  }
  list($name, $value) = explode('=', substr($argument, 2), 2);
  if ($name === 'upstream-recipes-dir') {
    $options[$name][] = $value;
  }
  elseif (array_key_exists($name, $options)) {
    $options[$name] = $value;
  }
  else {
    fwrite(STDERR, "Unknown option: --$name\n");
    usage();
    exit(1);
  }
}

$recipe_file = absolutePath($options['recipe']);
if (!is_file($recipe_file)) {
  fwrite(STDERR, "Recipe file not found: $recipe_file\n");
  exit(1);
}

$recipe_dir = dirname($recipe_file);
$upstream_dirs = array_values(array_unique(array_filter(array_map(function ($path) {
  return is_dir($path) ? absolutePath($path) : NULL;
}, $options['upstream-recipes-dir']))));

$drupal_root = $options['drupal-root'] ? normalizeDrupalRoot(absolutePath($options['drupal-root'])) : NULL;
$candidate_recipes = $options['candidate-recipes']
  ? array_values(array_filter(array_map('trim', explode(',', $options['candidate-recipes']))))
  : DEFAULT_CANDIDATE_RECIPES;

$umami_recipe = readRecipe($recipe_file);
$install = $umami_recipe['install'];
$currently_applied_recipes = $umami_recipe['recipes'];
$config_imports = $umami_recipe['config_imports'];
$umami_config_names = configNames($recipe_dir . '/config');

$owners_by_extension = [];
$unresolved_recipes = [];
foreach ($candidate_recipes as $candidate_recipe) {
  $installed_by_candidate = collectRecipeInstalls(
    $candidate_recipe,
    $candidate_recipe,
    $recipe_dir,
    $upstream_dirs,
    $drupal_root,
    $unresolved_recipes
  );
  foreach ($installed_by_candidate as $extension) {
    $owners_by_extension[$extension][] = $candidate_recipe;
  }
}

foreach ($owners_by_extension as $extension => $owners) {
  $owners_by_extension[$extension] = array_values(array_unique($owners));
  sort($owners_by_extension[$extension]);
}

ksort($owners_by_extension);

$rows = [];
foreach ($install as $extension) {
  $config_install = extensionConfigInstallNames($drupal_root, $recipe_dir, $extension);
  $overlap = array_values(array_intersect($config_install, $umami_config_names));
  sort($overlap);

  $owners = isset($owners_by_extension[$extension]) ? $owners_by_extension[$extension] : [];
  $rows[] = [
    'extension' => $extension,
    'owners' => $owners,
    'config_install' => $config_install,
    'overlap' => $overlap,
    'recommendation' => recommend($extension, $owners, $overlap, $config_imports),
  ];
}

print "# Recipe Ownership Report\n\n";
print "This report classifies the current flat `install:` list against the upstream recipe ownership target for Umami. Refresh it with:\n\n";
print "```sh\n";
print "php scripts/recipe-ownership-report.php \\\n";
print "  --upstream-recipes-dir=/path/to/drupal-cms/recipes \\\n";
print "  --drupal-root=/path/to/drupal/web \\\n";
print "  > docs/RECIPE_OWNERSHIP.md\n";
print "```\n\n";
print "If upstream recipes are unavailable, the script still reports the current flat install list but cannot identify upstream owners. `--drupal-root` is optional; without it, contrib and core module `config/install` overlap cannot be calculated.\n\n";
print "## Candidate Upstream Recipes\n\n";
foreach ($candidate_recipes as $candidate_recipe) {
  $status = in_array($candidate_recipe, $currently_applied_recipes, TRUE) ? 'currently applied' : 'target';
  print "- `$candidate_recipe` ($status)\n";
}

if ($unresolved_recipes) {
  $unresolved_recipes = array_values(array_unique($unresolved_recipes));
  sort($unresolved_recipes);
  print "\n## Unresolved Recipe Sources\n\n";
  print "The following recipes were referenced but not found in the supplied recipe directories. Re-run with a complete Drupal CMS checkout if these should be classified.\n\n";
  foreach ($unresolved_recipes as $recipe) {
    print "- `$recipe`\n";
  }
}

print "\n## Decision Rules\n\n";
print "- `move to upstream recipe` means the module has a candidate upstream owner and Umami does not currently re-ship matching module default config.\n";
print "- `audit config before moving` means an upstream-owned extension also has module default config that overlaps `config/`; check whether Umami changed that config before deleting it from `install:`.\n";
print "- `keep flat` means the extension is Umami-owned, a core structural dependency, or not covered by the candidate upstream recipes.\n";
print "- `keep flat with config.import` means Umami intentionally uses module-provided defaults that are not owned by the candidate recipes.\n";

print "\n## Matrix\n\n";
print "| Extension | Currently in `install:` | Candidate upstream owner(s) | Module ships `config/install` | Umami re-ships that config | Recommendation |\n";
print "| --- | --- | --- | --- | --- | --- |\n";

foreach ($rows as $row) {
  print '| `' . $row['extension'] . '` ';
  print '| yes ';
  print '| ' . markdownList($row['owners']) . ' ';
  print '| ' . countLabel($row['config_install']) . ' ';
  print '| ' . countLabel($row['overlap']) . ' ';
  print '| ' . $row['recommendation'] . " |\n";
}

/**
 * Gets an absolute path.
 */
function absolutePath($path) {
  if (startsWith($path, '/')) {
    return $path;
  }
  return getcwd() . '/' . $path;
}

/**
 * Normalizes a Drupal project root or web root to the web root.
 */
function normalizeDrupalRoot($path) {
  if (is_dir($path . '/core/modules')) {
    return $path;
  }
  if (is_dir($path . '/web/core/modules')) {
    return $path . '/web';
  }
  return $path;
}

/**
 * Reads the recipe sections needed by this report.
 *
 * @return array{install: list<string>, recipes: list<string>, config_imports: list<string>}
 */
function readRecipe($file) {
  $install = [];
  $recipes = [];
  $config_imports = [];
  $section = NULL;
  $in_config = FALSE;
  $in_config_import = FALSE;

  foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if (preg_match('/^([A-Za-z0-9_.-]+):\s*(.*)$/', $line, $matches)) {
      $section = $matches[1];
      $in_config = $section === 'config';
      $in_config_import = FALSE;
      continue;
    }

    if (($section === 'install' || $section === 'recipes') && preg_match('/^\s+-\s+(.+?)\s*$/', $line, $matches)) {
      $value = trim($matches[1], "\"' ");
      if ($value !== '') {
        if ($section === 'install') {
          $install[] = $value;
        }
        else {
          $recipes[] = $value;
        }
      }
      continue;
    }

    if ($in_config && preg_match('/^  import:\s*$/', $line)) {
      $in_config_import = TRUE;
      continue;
    }

    if ($in_config_import) {
      if (preg_match('/^    ([A-Za-z0-9_]+):\s*/', $line, $matches)) {
        $config_imports[] = $matches[1];
        continue;
      }
      if (preg_match('/^  [A-Za-z0-9_.-]+:\s*/', $line) || preg_match('/^[A-Za-z0-9_.-]+:\s*/', $line)) {
        $in_config_import = FALSE;
      }
    }
  }

  return [
    'install' => array_values(array_unique($install)),
    'recipes' => array_values(array_unique($recipes)),
    'config_imports' => array_values(array_unique($config_imports)),
  ];
}

/**
 * Recursively collects modules/themes installed by a recipe.
 *
 * @param array<string, bool> $seen
 *
 * @return list<string>
 */
function collectRecipeInstalls(
  $recipe_name,
  $top_level_recipe,
  $recipe_dir,
  array $upstream_dirs,
  $drupal_root,
  array &$unresolved_recipes,
  array &$seen = array()
) {
  $seen_key = $top_level_recipe . '|' . $recipe_name;
  if (isset($seen[$seen_key])) {
    return [];
  }
  $seen[$seen_key] = TRUE;

  $path = resolveRecipePath($recipe_name, $recipe_dir, $upstream_dirs, $drupal_root);
  if (!$path) {
    $unresolved_recipes[] = $recipe_name;
    return [];
  }

  $recipe = readRecipe($path);
  $installed = $recipe['install'];
  foreach ($recipe['recipes'] as $child_recipe) {
    $child_installs = collectRecipeInstalls($child_recipe, $top_level_recipe, $recipe_dir, $upstream_dirs, $drupal_root, $unresolved_recipes, $seen);
    foreach ($child_installs as $child_install) {
      $installed[] = $child_install;
    }
  }

  $installed = array_values(array_unique($installed));
  sort($installed);
  return $installed;
}

/**
 * Resolves a recipe name to a recipe.yml path.
 */
function resolveRecipePath($recipe_name, $recipe_dir, array $upstream_dirs, $drupal_root) {
  $candidates = [];

  if ($recipe_name === 'umami') {
    $candidates[] = $recipe_dir . '/recipe.yml';
  }

  if (startsWith($recipe_name, 'core/recipes/')) {
    if ($drupal_root) {
      $candidates[] = $drupal_root . '/' . $recipe_name . '/recipe.yml';
    }
  }
  else {
    foreach ($upstream_dirs as $upstream_dir) {
      $candidates[] = $upstream_dir . '/' . $recipe_name . '/recipe.yml';
    }
    $candidates[] = $recipe_dir . '/recipes/' . $recipe_name . '/recipe.yml';
  }

  foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
      return $candidate;
    }
  }
  return NULL;
}

/**
 * Lists config entity/simple config names in a directory.
 *
 * @return list<string>
 */
function configNames($config_dir) {
  if (!is_dir($config_dir)) {
    return [];
  }

  $names = [];
  foreach (glob($config_dir . '/*.yml') ?: [] as $file) {
    $names[] = basename($file, '.yml');
  }
  sort($names);
  return $names;
}

/**
 * Lists config/install names shipped by an extension.
 *
 * @return list<string>
 */
function extensionConfigInstallNames($drupal_root, $recipe_dir, $extension) {
  $paths = [
    $recipe_dir . '/packages/' . $extension,
  ];

  if ($drupal_root) {
    $paths[] = $drupal_root . '/core/modules/' . $extension;
    $paths[] = $drupal_root . '/modules/contrib/' . $extension;
    $paths[] = $drupal_root . '/modules/custom/' . $extension;
    $paths[] = $drupal_root . '/themes/contrib/' . $extension;
    $paths[] = $drupal_root . '/themes/custom/' . $extension;
    $paths = array_merge($paths, nestedExtensionPaths($drupal_root . '/modules/contrib', $extension));
    $paths = array_merge($paths, nestedExtensionPaths($drupal_root . '/modules/custom', $extension));
    $paths = array_merge($paths, nestedExtensionPaths($drupal_root . '/themes/contrib', $extension));
    $paths = array_merge($paths, nestedExtensionPaths($drupal_root . '/themes/custom', $extension));
  }

  $names = [];
  foreach ($paths as $path) {
    foreach (glob($path . '/config/install/*.yml') ?: [] as $file) {
      $names[] = basename($file, '.yml');
    }
  }
  $names = array_values(array_unique($names));
  sort($names);
  return $names;
}

/**
 * Finds nested extension directories such as Search API DB.
 *
 * @return list<string>
 */
function nestedExtensionPaths($base_dir, $extension) {
  if (!is_dir($base_dir)) {
    return [];
  }

  $paths = [];
  $patterns = [
    $base_dir . '/*/modules/' . $extension,
    $base_dir . '/*/modules/*/' . $extension,
    $base_dir . '/*/themes/' . $extension,
    $base_dir . '/*/themes/*/' . $extension,
  ];

  foreach ($patterns as $pattern) {
    foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $path) {
      if (is_file($path . '/' . $extension . '.info.yml')) {
        $paths[] = $path;
      }
    }
  }

  return array_values(array_unique($paths));
}

/**
 * Produces a recommendation for an extension row.
 */
function recommend($extension, array $owners, array $overlap, array $config_imports) {
  if (in_array($extension, UMAMI_EXTENSIONS, TRUE)) {
    return 'keep flat: Umami custom package';
  }
  if (in_array($extension, FOUNDATION_EXTENSIONS, TRUE)) {
    return 'keep flat: Umami structural dependency';
  }
  if (in_array($extension, ADVANCED_SEO_EXTENSIONS, TRUE)) {
    return in_array($extension, $config_imports, TRUE)
      ? 'keep flat with config.import'
      : 'keep flat: Umami-specific SEO choice';
  }
  if (!$owners) {
    return 'keep flat: no candidate upstream owner';
  }
  if ($overlap) {
    return 'audit config before moving to upstream recipe';
  }
  return 'move to upstream recipe';
}

/**
 * Formats a Markdown list for a table cell.
 */
function markdownList(array $values) {
  if (!$values) {
    return '-';
  }
  return implode('<br>', array_map(function ($value) {
    return '`' . $value . '`';
  }, $values));
}

/**
 * Formats a config count.
 */
function countLabel(array $values) {
  $count = count($values);
  if ($count === 0) {
    return 'no';
  }
  $examples = array_slice($values, 0, 3);
  return 'yes (' . $count . ': ' . implode(', ', array_map(function ($value) {
    return '`' . $value . '`';
  }, $examples)) . ($count > 3 ? ', ...' : '') . ')';
}

/**
 * Checks whether a string starts with a prefix.
 */
function startsWith($haystack, $needle) {
  return substr($haystack, 0, strlen($needle)) === $needle;
}
