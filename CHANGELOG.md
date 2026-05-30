# Release Notes for Standard Site

## 1.1.0
### Added
- Added publication icon, theme colours (`basicTheme`) and discovery preference (`preferences.showInDiscover`) to the `site.standard.publication` record, so standard.site-aware readers (Bluesky, Frontpage, etc.) can render content as rich link cards. Configure them per-site in plugin settings.

## 1.0.1 - 2026-05-29
### Fixed
- Fixed multi-site connections: each Craft site now stores and uses its own AT Protocol connection instead of sharing a single one. Connecting, disconnecting and publishing are now fully per-site.

## 1.0.0 - 2026-04-03
- Initial release!
