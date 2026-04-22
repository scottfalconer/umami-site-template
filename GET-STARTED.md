# Getting Started With Umami

Umami is a Drupal CMS site template. Install it into a fresh Drupal CMS project
as a recipe, then treat the resulting site as the owner of any post-install
configuration changes.

## Local Development Install

Use a separate Drupal CMS tester so recipe installs are always validated from a
clean site.

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
ddev drush site:install recipes/umami --account-name=admin --account-pass=admin -y
```

After changing this repository, refresh the tester copy and reinstall:

```sh
cp -R /path/to/umami-site-template/. source/
ddev drush site:install recipes/umami --account-name=admin --account-pass=admin -y
```

## What This Template Demonstrates

- Drupal CMS as the base distribution.
- Canvas pages and content templates for the editable visual experience.
- Mercury and Umami theme assets as the design-system foundation.
- Structured content for recipes, stories, collections, media, topics, cuisine, dietary categories, and recipe categories.
- Views-driven public recipe and story archives.
- Drupal CMS Search API/View for search.
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

## Current Follow-Ups

- Keep homepage recipe curation driven by Drupal's editable node flags: `promote` includes recipes in homepage curation and `sticky` pins the first row unless a future Canvas-native curation model replaces it.
- Continue reducing theme preprocess code as more rendering moves into content templates and components.
- Reduce install/update repair glue where exported config/content can own the final state.
