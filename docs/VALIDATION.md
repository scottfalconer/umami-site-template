# Validation Notes

This file records clean-install findings that affect how the Umami site
template should be evaluated or released.

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
