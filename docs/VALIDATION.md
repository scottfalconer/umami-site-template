# Validation Notes

This file records clean-install findings that affect how the Umami site
template should be evaluated or released.

## April 24, 2026 Community Recipe Content Preservation

Covered on a clean DDEV install at `../umami-community-content-test` after
replacing the synthetic recipe export with the original Drupal core Umami demo
recipes and photography.

Validation commands run:

```sh
make dev-test-install TESTER_DIR=../umami-community-content-test TESTER_NAME=umami-community-content-test
ddev drush ev '$ids=\Drupal::entityQuery("node")->accessCheck(FALSE)->condition("type","recipe")->sort("created","DESC")->execute(); print "recipe_count=".count($ids)."\n";'
ddev drush ev '$cards=\Drupal::service("umami_next.editorial_data")->loadFeaturedCards("recipe",6); print implode("\n", array_column($cards,"title"))."\n";'
ddev drush search-api:index
ddev drush search-api:status
ddev drush watchdog:show --count=20 --severity=Error --format=table
```

Runtime checks confirmed:

- Clean install completed and cache rebuild succeeded.
- Installed recipe count is 10, matching the original Umami community recipe
  corpus.
- Featured recipe curation returns Deep mediterranean quiche, Vegan chocolate
  and nut brownies, Super easy vegetarian pasta bake, Watercress soup, Victoria
  sponge cake, and Gluten free pizza.
- The previous synthetic recipe titles checked during validation, including
  Slow-Roasted Tomato Pappardelle, Sticky Sesame Aubergine, and Jollof Rice
  with Smoked Paprika, are no longer installed as recipe nodes.
- `/`, `/recipes`, `/recipe/deep-mediterranean-quiche`, and `/search` return
  HTTP 200 inside the DDEV web container.
- `/search?keywords=tomato` returns Deep mediterranean quiche after indexing.
- No Drupal watchdog errors were present after install and indexing.

## April 24, 2026 Core Umami Attribution Alignment

Checked against the Drupal core Umami profile installed with Drupal 11.3.8. Core
Umami imports recipe nodes with `Umami` as the author and keeps photographer
credits in `default_content/LICENCE.txt`; it does not render a visible
provenance sentence on recipe pages.

Validation commands run:

```sh
ruby --disable-gems -e 'require "yaml"; Dir["{config,content,packages}/**/*.{yml,yaml}"].sort.each { |f| YAML.load_file(f) }; puts "yaml_ok"'
docker run --rm -v "$PWD":/app -w /app php:8.3-cli php -l packages/umami_next_theme/umami_next_theme.theme
docker run --rm -v "$PWD":/app -w /app php:8.3-cli php -l tests/src/Functional/ValidationTest.php
make dev-test-install TESTER_DIR=../umami-attribution-test TESTER_NAME=umami-attribution-test
ddev drush ev '$storage=\Drupal::entityTypeManager()->getStorage("node"); $ids=\Drupal::entityQuery("node")->accessCheck(FALSE)->condition("type","recipe")->execute(); $nodes=$storage->loadMultiple($ids); print "recipe_count=" . count($nodes) . "\n"; foreach ($nodes as $node) { print $node->label() . " | " . $node->getOwner()->getAccountName() . "\n"; } $defs=\Drupal::service("entity_field.manager")->getFieldDefinitions("node","recipe"); print "field_recipe_credit=" . (isset($defs["field_recipe_credit"]) ? "present" : "absent") . "\n"; print "field_media_credit=" . (isset($defs["field_media_credit"]) ? "present" : "absent") . "\n";'
ddev drush search-api:index
ddev drush search-api:status
ddev drush watchdog:show --count=20 --severity=Error --format=table
```

Runtime checks confirmed:

- Clean install completed and cache rebuild succeeded.
- Installed recipe count is 10.
- All installed recipe nodes are authored by `Umami`.
- `field_recipe_credit` and `field_media_credit` are absent from the recipe
  field definitions.
- `/recipe/deep-mediterranean-quiche` renders `By Umami` and does not render the
  custom provenance sentence or a `Photo:` caption.
- `/`, `/recipes`, `/recipe/deep-mediterranean-quiche`, and `/search` return
  HTTP 200 inside the DDEV web container.
- Search index is 100%, with 26 of 26 items indexed.
- No Drupal watchdog errors were present after install and indexing.

## April 24, 2026 Recipe-Composition Audit

Covered on a clean DDEV install at `../umami-ownership-clean-20260424` after
composing the lower-level Drupal CMS recipes and reducing the flattened
`install:` and `config/` exports.

Validation commands run:

```sh
make dev-sync-source TESTER_DIR=../umami-ownership-clean-20260424
ddev drush site:install "recipes/umami" --site-name=Umami --account-name=admin --account-pass=admin -y
ddev drush cr
ddev drush search-api:index
ddev drush search-api:status
ddev drush watchdog:show --count=20 --severity=Error --format=table
```

Runtime checks confirmed:

- `/`, `/recipes`, `/stories`, `/topic/weeknight`, `/search`, `/contact`,
  `/collections/spring-market`, and `/sitemap.xml` return HTTP 200 inside the
  DDEV web container.
- `/search?keywords=tomato` returns indexed recipe/content results and no
  longer returns the Canvas `/404` utility page.
- `/contact` renders the Umami contact form with subject choices plus the
  newsletter signup, while retaining Honeypot protection from
  `drupal_cms_forms`.
- Homepage, search, and contact browser snapshots had the expected headings,
  links, and form controls, with no browser page errors or console errors.
- The installed Canvas pages are `/home` and `/404`, and the functional test
  guards that all referenced Canvas components are shipped as config.

Known remaining status-report warnings:

- `settings.php` is writable in DDEV. Production must protect configuration
  files.
- Package Manager is experimental and also emits its early-testing warning via
  the Drupal CMS Admin UI stack.
- Webform private files are not configured in local DDEV settings.
- Webform external-library warnings remain for optional admin/UI libraries that
  are not required by the public contact/newsletter forms.

## Latest Clean-Site Intent Coverage

Covered on a standalone Drupal CMS install created from the recipe on April 22,
2026. The browser-based intent pass exercised the homepage, recipe archive,
story archive, topic landing page, search page, contact page, 404 handling, and
the editor lifecycle below.

The editor lifecycle coverage was:

- Log in as `admin`.
- Open content administration.
- Open the Canvas editor for the homepage.
- Create an article.
- View the generated article page.
- Delete the test article.
- Confirm no Drupal watchdog errors and no leftover test node.

The backend checks recorded for the cleanup assertion were:

```sh
ddev drush watchdog:show --count=20 --severity=Error --format=table
ddev drush ev '$ids=\Drupal::entityQuery("node")->accessCheck(FALSE)->condition("title", "Intent Test Article", "CONTAINS")->execute(); print "ids=".implode(",", $ids)."\n";'
```

## Upstream Canvas Editor Console Warning

The Canvas editor currently emits browser console warnings when opening the
homepage editor at `/canvas/editor/canvas_page/1`:

```text
Error: <svg> attribute width: Expected length, "auto".
```

The affected SVGs are Canvas editor top-bar controls, including Exit, Undo, and
Redo. This should be tracked upstream in Canvas rather than patched in
`umami_next_theme`, because the invalid markup belongs to the editor UI chrome,
not the public Umami site.

The source scan used to confirm this was not Umami-owned markup was:

```sh
rg -n "width=\"auto\"|width='auto'|width:\s*auto|height=\"auto\"|height='auto'" . --glob '!vendor/**' --glob '!web/core/**' --glob '!web/modules/contrib/**' --glob '!web/themes/contrib/**' --glob '!web/sites/default/files/**'
```

Do not add local CSS or JavaScript workarounds for this in the recipe unless a
future Canvas issue confirms a project-level workaround is required.

## Local DDEV Snapshot Caveat

During repeated intent-test runs, `ddev snapshot restore` intermittently failed
on the local machine because DDEV global command mounts were missing inside the
web container. This is local DDEV environment noise, not a recipe defect.

The recovery command was:

```sh
ddev debug fix-commands || true
ddev start
ddev drush cr
```
