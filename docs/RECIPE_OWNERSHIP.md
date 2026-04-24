# Recipe Ownership Report

This report classifies the current flat `install:` list against the upstream recipe ownership target for Umami. Refresh it with:

```sh
php scripts/recipe-ownership-report.php \
  --upstream-recipes-dir=/path/to/drupal-cms/recipes \
  --drupal-root=/path/to/drupal/web \
  > docs/RECIPE_OWNERSHIP.md
```

If upstream recipes are unavailable, the script still reports the current flat install list but cannot identify upstream owners. `--drupal-root` is optional; without it, contrib and core module `config/install` overlap cannot be calculated.

## Candidate Upstream Recipes

- `core/recipes/administrator_role` (currently applied)
- `core/recipes/core_recommended_maintenance` (currently applied)
- `core/recipes/core_recommended_performance` (currently applied)
- `drupal_cms_admin_ui` (currently applied)
- `drupal_cms_authentication` (currently applied)
- `drupal_cms_forms` (currently applied)
- `drupal_cms_media` (currently applied)
- `drupal_cms_search` (currently applied)
- `../recipes/umami/recipes/search_index_fields` (currently applied)
- `drupal_cms_seo_basic` (currently applied)
- `easy_email_express` (currently applied)

## Decision Rules

- `move to upstream recipe` means the module has a candidate upstream owner and Umami does not currently re-ship matching module default config.
- `audit config before moving` means an upstream-owned extension also has module default config that overlaps `config/`; check whether Umami changed that config before deleting it from `install:`.
- `keep flat` means the extension is Umami-owned, a core structural dependency, or not covered by the candidate upstream recipes.
- `keep flat with config.import` means Umami intentionally uses module-provided defaults that are not owned by the candidate recipes.

## Matrix

| Extension | Currently in `install:` | Candidate upstream owner(s) | Module ships `config/install` | Umami re-ships that config | Recommendation |
| --- | --- | --- | --- | --- | --- |
| `automated_cron` | yes | `core/recipes/core_recommended_maintenance`<br>`drupal_cms_search` | yes (1: `automated_cron.settings`) | no | keep flat: Umami structural dependency |
| `big_pipe` | yes | `core/recipes/core_recommended_performance` | no | no | keep flat: Umami structural dependency |
| `block` | yes | `drupal_cms_search` | no | no | keep flat: Umami structural dependency |
| `breakpoint` | yes | - | no | no | keep flat: Umami structural dependency |
| `ckeditor5` | yes | `easy_email_express` | no | no | keep flat: Umami structural dependency |
| `config` | yes | - | no | no | keep flat: Umami structural dependency |
| `content_moderation` | yes | - | no | no | keep flat: Umami structural dependency |
| `datetime` | yes | - | no | no | keep flat: Umami structural dependency |
| `dynamic_page_cache` | yes | `core/recipes/core_recommended_performance` | no | no | keep flat: Umami structural dependency |
| `editor` | yes | `drupal_cms_forms`<br>`easy_email_express` | no | no | keep flat: Umami structural dependency |
| `field` | yes | - | yes (1: `field.settings`) | no | keep flat: Umami structural dependency |
| `field_ui` | yes | `drupal_cms_media` | yes (1: `field_ui.settings`) | no | keep flat: Umami structural dependency |
| `file` | yes | `drupal_cms_admin_ui`<br>`drupal_cms_media`<br>`easy_email_express` | yes (1: `file.settings`) | yes (1: `file.settings`) | keep flat: Umami structural dependency |
| `filter` | yes | `drupal_cms_forms`<br>`easy_email_express` | yes (2: `filter.format.plain_text`, `filter.settings`) | yes (1: `filter.format.plain_text`) | keep flat: Umami structural dependency |
| `image` | yes | `drupal_cms_authentication` | yes (5: `image.settings`, `image.style.large`, `image.style.medium`, ...) | no | keep flat: Umami structural dependency |
| `inline_form_errors` | yes | - | no | no | keep flat: Umami structural dependency |
| `layout_builder` | yes | - | no | no | keep flat: Umami structural dependency |
| `layout_discovery` | yes | - | no | no | keep flat: Umami structural dependency |
| `link` | yes | - | no | no | keep flat: Umami structural dependency |
| `media` | yes | - | yes (2: `core.entity_view_mode.media.full`, `media.settings`) | yes (1: `media.settings`) | keep flat: Umami structural dependency |
| `media_library` | yes | `drupal_cms_media` | yes (5: `core.entity_form_mode.media.media_library`, `core.entity_view_mode.media.media_library`, `image.style.media_library`, ...) | yes (1: `views.view.media_library`) | keep flat: Umami structural dependency |
| `menu_link_content` | yes | `drupal_cms_admin_ui`<br>`drupal_cms_media` | no | no | keep flat: Umami structural dependency |
| `mysql` | yes | - | no | no | keep flat: Umami structural dependency |
| `node` | yes | `drupal_cms_media`<br>`drupal_cms_search`<br>`drupal_cms_seo_basic` | yes (14: `core.entity_view_mode.node.full`, `core.entity_view_mode.node.rss`, `core.entity_view_mode.node.search_index`, ...) | yes (4: `system.action.node_delete_action`, `system.action.node_publish_action`, `system.action.node_save_action`, ...) | keep flat: Umami structural dependency |
| `options` | yes | `drupal_cms_media` | no | no | keep flat: Umami structural dependency |
| `package_manager` | yes | - | yes (1: `package_manager.settings`) | yes (1: `package_manager.settings`) | keep flat: no candidate upstream owner |
| `page_cache` | yes | `core/recipes/core_recommended_performance` | no | no | keep flat: Umami structural dependency |
| `path` | yes | `drupal_cms_media` | no | no | keep flat: Umami structural dependency |
| `path_alias` | yes | - | no | no | keep flat: Umami structural dependency |
| `system` | yes | - | yes (31: `core.date_format.fallback`, `core.date_format.html_date`, `core.date_format.html_datetime`, ...) | no | keep flat: Umami structural dependency |
| `taxonomy` | yes | `drupal_cms_media` | yes (4: `core.entity_view_mode.taxonomy_term.full`, `system.action.taxonomy_term_publish_action`, `system.action.taxonomy_term_unpublish_action`, ...) | yes (2: `system.action.taxonomy_term_publish_action`, `system.action.taxonomy_term_unpublish_action`) | keep flat: Umami structural dependency |
| `text` | yes | - | yes (1: `text.settings`) | no | keep flat: Umami structural dependency |
| `user` | yes | `drupal_cms_authentication` | yes (11: `core.entity_form_mode.user.register`, `core.entity_view_mode.user.compact`, `core.entity_view_mode.user.full`, ...) | no | keep flat: Umami structural dependency |
| `views` | yes | `core/recipes/core_recommended_maintenance`<br>`drupal_cms_forms`<br>`drupal_cms_media`<br>`drupal_cms_search`<br>`drupal_cms_seo_basic` | yes (1: `views.settings`) | no | keep flat: Umami structural dependency |
| `workflows` | yes | `drupal_cms_media` | no | no | keep flat: Umami structural dependency |
| `canvas` | yes | `drupal_cms_media`<br>`drupal_cms_search` | yes (8: `canvas.asset_library.global`, `canvas.brand_kit.global`, `editor.editor.canvas_html_block`, ...) | yes (5: `editor.editor.canvas_html_block`, `editor.editor.canvas_html_inline`, `filter.format.canvas_html_block`, ...) | keep flat: Umami structural dependency |
| `crop` | yes | - | yes (1: `crop.settings`) | no | keep flat: no candidate upstream owner |
| `cva` | yes | - | no | no | keep flat: no candidate upstream owner |
| `eca` | yes | - | yes (1: `eca.settings`) | yes (1: `eca.settings`) | keep flat: no candidate upstream owner |
| `editoria11y` | yes | - | yes (1: `editoria11y.settings`) | yes (1: `editoria11y.settings`) | keep flat: no candidate upstream owner |
| `facets` | yes | - | no | no | keep flat: Umami-owned public search facet configuration |
| `jquery_ui` | yes | - | no | no | keep flat: no candidate upstream owner |
| `jquery_ui_resizable` | yes | - | no | no | keep flat: no candidate upstream owner |
| `metatag` | yes | - | yes (8: `metatag.metatag_defaults.403`, `metatag.metatag_defaults.404`, `metatag.metatag_defaults.front`, ...) | no | keep flat with config.import |
| `metatag_open_graph` | yes | - | no | no | keep flat: Umami-specific SEO choice |
| `metatag_twitter_cards` | yes | - | no | no | keep flat: Umami-specific SEO choice |
| `redirect_404` | yes | `drupal_cms_seo_basic` | yes (2: `redirect_404.settings`, `views.view.redirect_404`) | yes (2: `redirect_404.settings`, `views.view.redirect_404`) | keep flat: Umami-specific SEO choice |
| `schema_article` | yes | - | no | no | keep flat with config.import |
| `schema_metatag` | yes | - | no | no | keep flat with config.import |
| `simple_sitemap` | yes | - | yes (6: `simple_sitemap.custom_links.default`, `simple_sitemap.settings`, `simple_sitemap.sitemap.default`, ...) | yes (1: `simple_sitemap.custom_links.default`) | keep flat with config.import |
| `umami_next` | yes | - | yes (1: `umami_next.default_content_references`) | no | keep flat: Umami custom package |
| `claro` | yes | - | no | no | keep flat: Umami structural dependency |
| `stark` | yes | - | no | no | keep flat: Umami structural dependency |
| `mercury` | yes | - | yes (1: `mercury.settings`) | no | keep flat: no candidate upstream owner |
| `umami_next_theme` | yes | - | no | no | keep flat: Umami custom package |

## Search Index Override

Umami applies `../recipes/umami/recipes/search_index_fields` immediately after
`drupal_cms_search` because Facets validates each configured field while facet
config is imported. The internal recipe raw-updates the cuisine, dietary,
recipe category, topic, and cook-time field settings before the parent recipe
imports `facets.facet.*` config, without replacing the full upstream Drupal CMS
Search index entity.
