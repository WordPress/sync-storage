# Changelog

## [0.1.2](https://github.com/josephfusco/sync-storage/compare/v0.1.1...v0.1.2) (2026-08-18)


### Features

* add Playwright e2e tests with collaborative testing utilities ([a627de6](https://github.com/josephfusco/sync-storage/commit/a627de67c50b63fcfc4ab1dc536921e899d45ac9))
* use WordPress.org plugin dependency system ([3140a96](https://github.com/josephfusco/sync-storage/commit/3140a960c259aaf3cfe06ccfb0b59a30a2b71f02))


### Bug Fixes

* complete rename cleanup ([b7253a1](https://github.com/josephfusco/sync-storage/commit/b7253a186e7e0cd8c76dc7f1e02dcf4b20572e7e))
* configure Playwright authentication for e2e tests ([fef9109](https://github.com/josephfusco/sync-storage/commit/fef9109fae96e93ca27e4d718c1aeccfb32815ea))

## [0.1.1](https://github.com/josephfusco/sync-storage/compare/v0.1.0...v0.1.1) (2026-08-18)


### Bug Fixes

* remove UNIQUE KEY constraint, fix multisite activation, use RTC_Logger consistently ([557e6ba](https://github.com/josephfusco/sync-storage/commit/557e6baf92bed09d2235af639be44760e437be9d))

## [0.1.0] - 2026-08-15

### Added

- Initial release
- `wp_collaboration` table for CRDT update storage
- `Gutenberg_Sync_Storage` interface implementation
- Presence API integration for awareness (cursors, user metadata)
- Server authority model (RTC activates when 2+ editors detected)
- `rtc_room_active` and `rtc_room_inactive` action hooks
- Multisite support (per-site tables)
- Daily cron cleanup for stale updates (7-day TTL)
- Migration from `wp_sync_storage` post meta
- WordPress Playground blueprints for testing

[0.1.0]: https://github.com/WordPress/sync-storage/releases/tag/v0.1.0
