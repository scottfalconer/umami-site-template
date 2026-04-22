# Umami Drupal CMS Site Template

This repository is the working package for the Umami Drupal CMS site template.
It is intentionally structured so the recipe can be installed and tested from a
fresh Drupal CMS checkout before any Drupal.org project split happens.

## Package Layout

```text
.
├── composer.json
├── recipe.yml
├── config/
├── content/
└── packages/
    ├── umami_next/
    └── umami_next_theme/
```

The root package is the site-template recipe (`drupal/umami`). The package
directories contain local Composer packages for the custom module and theme that
the exported recipe currently depends on.

## Development Install Test

Use the local DDEV helper to create or refresh a separate Drupal CMS tester,
mirror this source package into `source/`, require the local path repositories,
and reinstall the recipe:

```sh
make dev-test-install
```

By default this uses `../umami-site-template-test`. Override `TESTER_DIR` and
`TESTER_NAME` if you need a different local tester:

```sh
make dev-test-install TESTER_DIR=../umami-release-smoke TESTER_NAME=umami-release-smoke
```

Manual equivalent:

```sh
mkdir umami-site-template-test
cd umami-site-template-test
ddev config --project-type=drupal11 --docroot=web --project-name=umami-site-template-test
ddev start
ddev composer create-project drupal/cms .
cp -R /path/to/umami-site-template-codex source
ddev composer config repositories.umami path source
ddev composer config repositories.umami_next path source/packages/umami_next
ddev composer config repositories.umami_next_theme path source/packages/umami_next_theme
ddev composer require drupal/umami:^1@dev drupal/umami_next:^1@dev drupal/umami_next_theme:^1@dev --with-all-dependencies
ddev drush site:install recipes/umami --account-name=admin --account-pass=admin -y
```

After local source changes, refresh the tester copy before rerunning Composer or
install checks:

```sh
make dev-sync-source
```

The acceptance gate is a fresh DDEV install that reaches the Umami site without
manual UI changes.

## Release Packaging

The root package is a Drupal CMS site-template recipe, but the module and theme
under `packages/` must be released as their own Composer packages before the
recipe can be published for normal downstream use.

Release order:

1. Tag and release `drupal/umami_next`.
2. Tag and release `drupal/umami_next_theme`.
3. Update this recipe's `composer.json` to require tagged releases instead of
   local development constraints, for example:

   ```json
   "drupal/umami_next": "^1.0@alpha",
   "drupal/umami_next_theme": "^1.0@alpha"
   ```

4. Run a clean install from packages.drupal.org with only:

   ```sh
   ddev composer require drupal/umami:^1.0@alpha --with-all-dependencies
   ddev drush site:install recipes/umami --account-name=admin --account-pass=admin -y
   ```

The `^1@dev` constraints and path repositories are local development mechanics
only. Do not ship a public release that depends on them.

## Configuration Ownership

This package is install-time source of truth for a new Drupal CMS site. Site
structure, sample content, Canvas templates, menus, and block placement are
owned by the recipe export in `config/` and `content/`.

After installation, the downstream site owns normal Drupal configuration
management. A production site should set its own config sync directory outside
the web root and commit post-install site changes in that downstream project.
Do not treat `sites/default/files/sync` from a local DDEV install as release
source.

Environment-specific settings are intentionally not shipped in the recipe:

- `trusted_host_patterns` must be configured per environment. A permissive
  `.*` value is acceptable only in local DDEV-generated settings.
- `file_private_path` should point to a private directory outside the docroot
  for production and shared test environments, especially because Webform can
  collect uploads.
- CAPTCHA keys and outbound mail settings must be supplied by the installing
  site, not committed to this package.

## Current State

This package started from a `drush site:export` baseline from the working
Drupal CMS site. The remaining cleanup passes should:

- Reduce the flattened recipe export toward Drupal CMS recipe dependencies where possible.
- Remove config inherited from the `drush site:export` baseline when an upstream Drupal CMS/core/contrib recipe owns it.
- Keep recipes, stories, topic archives, search, and contact on Drupal-native foundations: Views, taxonomy term pages, Search API/View from `drupal_cms_search`, Webform, and editable menus.
- Keep the custom module limited to code that cannot be expressed as recipe/config/theme.
- Move editorial structure into fields, Views, menus, media, and Canvas templates instead of Twig/PHP hardcoding.
- Keep homepage recipe curation driven by Drupal's editable node flags: `promote` includes recipes in homepage curation and `sticky` pins the first row unless a future Canvas-native curation model replaces it.
- Preserve installability evidence for every cleanup pass.
