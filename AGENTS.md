# Agent guidance for Umami Drupal CMS sites

This file is copied into sites created from the Umami Drupal CMS site template.
Those downstream sites are Composer-managed Drupal sites and local development
uses `ddev`.

If you are working in the site-template source repository instead of an
installed site, use the source repository's `README.md` and `Makefile` for the
recipe install workflow.

## Local environment (DDEV)

Run commands from the project root:

- Start or restart the local environment with `ddev start`, `ddev restart`, and `ddev stop`.
- Install PHP dependencies with `ddev composer install`.
- Open the site with `ddev launch`.
- Run Drush commands with `ddev drush <command>` such as `status`, `user:login`,  `cache:rebuild`, and `update:db`.

DDEV project config lives in `.ddev/config.yaml`. Use `.ddev/config.local.yaml` for machine-specific overrides.

## Common Drupal workflows

- Add a module with `ddev composer require drupal/<project>`, then  `ddev drush pm:enable --yes <module_machine_name>`, then `ddev drush cache:rebuild`.
- Apply database updates after code changes with `ddev drush update:db --yes`.
- Import repository configuration into the site with `ddev drush config:import --yes`.
- Export site configuration back to the repo with `ddev drush config:export --yes`.

## Guardrails

- Do not commit secrets or machine-local overrides such as `.env`, `settings.local.php`, or `.ddev/config.local.yaml`.
- Do not commit `vendor/` or uploaded files under `web/sites/*/files`.
- Do not edit Drupal core or contributed projects in place.
- Put custom code in `web/modules/custom` and `web/themes/custom`.

## Template-specific notes

### Content model

Umami ships four primary node bundles: `recipe`, `article`, `collection`, and
`page` ("Utility page"). Recipes use custom structured field types for
ingredients and method steps; do not replace those with free-form text fields.
Topics, cuisines, dietary categories, recipe categories, and tags are taxonomy
vocabularies.

The recipe exports intentionally preserve the original Drupal core Umami demo
recipes and photography contributed by Drupal community members. Do not replace
those with generic stock, AI-generated, or synthetic food content; add new demo
recipes alongside the same source and licensing documentation pattern.

The homepage and 404 are Canvas pages. Repeatable public discovery surfaces use
Drupal-native structures: Views for recipes and stories, taxonomy term pages
with aliases for topics, Drupal CMS Search API/View for search, and Webform for
contact/newsletter forms.

### Editorial workflow and roles

The `basic_editorial` workflow applies to recipe, article, collection, and page
nodes with Draft, Published, and Unpublished states. The `content_editor` role
owns editorial CRUD, media handling, Canvas page editing, scheduling, and
workflow transitions. The `site_builder` role owns menus, redirects, and URL
alias administration. Anonymous and authenticated users should remain public
reader roles.

### Theme notes

The active public theme is `umami_next_theme`. It is an Umami-specific theme,
not a Mercury sub-theme, but Mercury remains available as the Canvas component
library. Keep visible editorial copy in fields, menus, Webforms, or Canvas page
content rather than hardcoding it in Twig or PHP.

Do not patch upstream Canvas editor UI issues in this theme unless an upstream
Canvas issue confirms a project-level workaround is required.

### Deployment notes

The recipe does not ship environment-specific settings. Configure trusted host
patterns, private file paths, CAPTCHA keys, and mail transport per environment.
After install, the downstream site owns normal Drupal configuration management.

For template maintainers:

The Umami source repository uses local `packages/` directories for development.
Public releases should depend on tagged `drupal/umami_next` and
`drupal/umami_next_theme` packages instead.

## References

- https://docs.ddev.com/en/stable/
- https://www.drupal.org/docs/administering-a-drupal-site/configuration-management/workflow-using-drush
