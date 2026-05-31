# Release Notes for Standard Site

## 1.1.0
### Added
- Added publication icon, theme colours (`basicTheme`) and discovery preference (`preferences.showInDiscover`) to the `site.standard.publication` record, so standard.site-aware readers (Bluesky, Frontpage, etc.) can render content as rich link cards.
- Publication details (name, description, icon, theme colours, discovery) are now configured on the **Standard.site** CP page and stored in the database — editable on production (`allowAdminChanges = false`) and with the icon asset referenced per-environment. Existing values are migrated from plugin settings automatically (the icon is re-selected per environment).
- Published entry pages now auto-inject a `<link rel="site.standard.document" href="at://…">` discovery tag in the `<head>`. This is how readers map an article URL to its AT Protocol record to render a rich link card; without it they fall back to a generic preview. A `craft.standardSite.documentTag()` Twig helper is available for manual placement (e.g. headless setups).

### Fixed
- The theme `basicTheme` object now includes the required `$type` discriminators (`site.standard.theme.basic` and `site.standard.theme.color#rgb`). Without them the theme union was malformed and rich-card renderers ignored it.
- Publication icon no longer silently drops when re-saving: it's stored in the database with an environment-local asset ID instead of a project-config reference that wouldn't resolve across environments.

## 1.0.1 - 2026-05-29
### Fixed
- Fixed multi-site connections: each Craft site now stores and uses its own AT Protocol connection instead of sharing a single one. Connecting, disconnecting and publishing are now fully per-site.

## 1.0.0 - 2026-04-03
- Initial release!
