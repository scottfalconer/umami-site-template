# Getting Started With Umami

Umami is a Drupal CMS 2.1+ site template. Install it into a fresh Drupal CMS
project as a recipe, then treat the resulting site as the owner of any
post-install configuration changes.

## Local Development Install

Use a separate Drupal CMS tester so recipe installs are always validated from a
clean site.

```sh
make dev-test-install
```

Manual equivalent:

```sh
mkdir umami-site-template-test
cd umami-site-template-test
ddev config --project-type=drupal11 --docroot=web --project-name=umami-site-template-test
ddev start
ddev composer create-project drupal/cms .
cp -R /path/to/umami-site-template source
ddev composer config repositories.umami path source
ddev composer config repositories.umami_next path source/packages/umami_next
ddev composer config repositories.umami_next_theme path source/packages/umami_next_theme
ddev composer require drupal/umami:^1@dev drupal/umami_next:^1@dev drupal/umami_next_theme:^1@dev --with-all-dependencies
ddev drush site:install recipes/umami --site-name=Umami --account-name=admin --account-pass=admin -y
```

After changing this repository, refresh the tester copy and reinstall:

```sh
make dev-test-install
```

Or, from inside the tester:

```sh
rsync -a --delete /path/to/umami-site-template/. source/
ddev drush site:install recipes/umami --site-name=Umami --account-name=admin --account-pass=admin -y
```

## What This Template Demonstrates

- Drupal CMS as the base distribution.
- Canvas pages and content templates for the editable visual experience.
- Mercury and Umami theme assets as the design-system foundation.
- Structured content for recipes, stories, collections, media, topics, cuisine, dietary categories, and recipe categories.
- Views-driven public recipe and story archives.
- Drupal CMS Search API/View for search.
- Metatag Open Graph/Twitter Card defaults and Simple XML Sitemap discovery.
- Schema Metatag installed for future per-bundle JSON-LD mappings.
- Webform-driven contact and newsletter forms.
- Menu-driven header, footer, and social links.

## Development Rules

- Do not make manual UI changes without exporting them back into recipe/config/content source.
- Keep custom PHP limited to behavior that cannot be expressed with Drupal config, Views, fields, menus, Webform, Search API, Canvas, or theme templates.
- Do not add dependencies outside the Drupal CMS stack without a release-level reason.
- Use DDEV and Drush for validation.
- Record exact validation commands before claiming a build is releasable.

## Release Notes

Before publishing beyond development:

1. Tag and release `drupal/umami_next`.
2. Tag and release `drupal/umami_next_theme`.
3. Change the root recipe requirements from local `^1@dev` packages to tagged constraints such as `^1.0@alpha`.
4. Run a fresh Drupal CMS install using only the released recipe package.
5. Update `recommended.yml` if Umami should curate Project Browser add-ons.
6. Verify that environment-specific settings, including trusted hosts, private files, CAPTCHA keys, and mail transport, are documented but not committed as defaults.
7. Confirm the final Drupal.org namespace and Composer package names before tagging `drupal/umami`.

The root recipe release archive intentionally excludes `packages/`. The released
recipe must depend on tagged module and theme packages, not on local path
repositories.

## Validation Notes

See `docs/VALIDATION.md` in the source repository for the latest clean-install
validation notes and upstream findings that should not be patched in the Umami
recipe.

## Current Follow-Ups

- Reduce the flattened `install:` list by composing more Drupal CMS recipes directly, after comparing the installed config delta against the current export.
- Remove exported upstream defaults from `config/` when they are owned by Drupal CMS, core, or contrib recipes rather than Umami.
- Add per-bundle Schema.org JSON-LD mappings with Schema Metatag, especially for recipe structured data.
- Keep homepage recipe curation driven by Drupal's editable node flags: `promote` includes recipes in homepage curation and `sticky` pins the first row unless a future Canvas-native curation model replaces it.
- Continue reducing theme preprocess code as more rendering moves into content templates and components.
- Reduce default-content reference backfill glue where exported config/content can own the final state directly.
