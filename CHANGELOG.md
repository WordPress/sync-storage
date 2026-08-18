# Changelog

## [0.1.4](https://github.com/WordPress/sync-storage/compare/v0.1.3...v0.1.4) (2026-08-18)


### Bug Fixes

* stop Playwright from starting a second wp-env instance in CI ([8c308b9](https://github.com/WordPress/sync-storage/commit/8c308b9b1fd5f3a1d3aeab88f29278fb4e9bc766))
* stop plugin-check's isolated wp-env from crashing on Requires Plugins ([4d30914](https://github.com/WordPress/sync-storage/commit/4d30914f337c3240e8a2c2557e215568ba22e327))
* sync all three version copies, not just the plugin header ([1880d4e](https://github.com/WordPress/sync-storage/commit/1880d4e10ef58821b1c3d89b4d3f715e5e22315a))


### Performance Improvements

* cache Gutenberg trunk checkout and Playwright browsers in CI ([5b316fd](https://github.com/WordPress/sync-storage/commit/5b316fd5363b0abd0f9b7b2e716b3371181db2c4))
* cache Gutenberg trunk checkout and Playwright browsers in CI ([3dcc316](https://github.com/WordPress/sync-storage/commit/3dcc316009916721df778e394fd5ee4544bb1ac8))

## [0.1.3](https://github.com/josephfusco/sync-storage/compare/v0.1.2...v0.1.3) (2026-08-18)


### Bug Fixes

* correct timestamp units, awareness access control, and uninstall mismatches ([5c26bdf](https://github.com/josephfusco/sync-storage/commit/5c26bdf26285a21aece4b353208c365da1810c4b))
* name the local Gutenberg trunk checkout to match its dependency slug ([6b13d19](https://github.com/josephfusco/sync-storage/commit/6b13d19470e1f5284ecea53d14d34e76fb2ec3c2))
* resolve Plugin Check findings for WordPress.org distribution ([21690da](https://github.com/josephfusco/sync-storage/commit/21690dade38785ff39dfce85073693410bd68dd7))

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
