# Changelog

## [0.1.1](https://github.com/josephfusco/realtime-collaboration/compare/v0.1.0...v0.1.1) (2026-08-18)


### Bug Fixes

* remove UNIQUE KEY constraint, fix multisite activation, use RTC_Logger consistently ([557e6ba](https://github.com/josephfusco/realtime-collaboration/commit/557e6baf92bed09d2235af639be44760e437be9d))

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

[0.1.0]: https://github.com/WordPress/realtime-collaboration/releases/tag/v0.1.0
