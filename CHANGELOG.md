# Changelog

## [0.1.5](https://github.com/WordPress/sync-storage/compare/v0.1.4...v0.1.5) (2026-08-19)


### Bug Fixes

* activate Gutenberg's real-time-collaboration experiment on plugin load ([230373c](https://github.com/WordPress/sync-storage/commit/230373c02d64ba0ff915b070d03a6e0987b6a963))
* give CI a .env so REST discovery hits the right wp-env port ([d62a2b8](https://github.com/WordPress/sync-storage/commit/d62a2b88dd0273da323ec341e338759c9894f656))
* give collaborative Playwright tests enough time on CI ([cec0720](https://github.com/WordPress/sync-storage/commit/cec072000bba874f0d7f52efa4f9e0faaf0ac2ec))
* give collaborative Playwright tests enough time on CI ([2e1986a](https://github.com/WordPress/sync-storage/commit/2e1986a8f1d50c412410a7da26aeccde9e503233))
* give release-please branches a real success, not skipped, on required checks ([8d09690](https://github.com/WordPress/sync-storage/commit/8d09690f955499ea5540eb306972cf9fcee1672d))
* give release-please branches a real success, not skipped, on required checks ([2663fc8](https://github.com/WordPress/sync-storage/commit/2663fc813d9400646de781f26fbfb6cf9e196521))
* point Playwright readiness check at /wp-json/ to warm REST routes ([728d2c9](https://github.com/WordPress/sync-storage/commit/728d2c9ea5707c0e82ebd3d72cfa6514863feab2))
* provision e2e collaborator users via WP-CLI instead of REST ([5a0148c](https://github.com/WordPress/sync-storage/commit/5a0148c65077298013b9eb2fe18edeeeb673fec0))
* reach the editor canvas iframe in the room-creation e2e test ([07459c0](https://github.com/WordPress/sync-storage/commit/07459c0b029e9c6773e8204257a1a5fcaf14c258))
* remove global-setup REST warm-up that hung CI for 30 minutes ([787f557](https://github.com/WordPress/sync-storage/commit/787f55786046575bc9f10690f68f9724e9b7344c))
* remove global-setup REST warm-up that hung CI for 30 minutes ([3264a4d](https://github.com/WordPress/sync-storage/commit/3264a4df058a09024e1a5db6cdaf0d40dd0c838f))
* resolve zizmor code-scanning findings ([8593000](https://github.com/WordPress/sync-storage/commit/8593000bafa665f2eeea8d5e80ed07825830ea2b))
* resolve zizmor code-scanning findings ([c156871](https://github.com/WordPress/sync-storage/commit/c156871650032ec90b43c22c4c9057bc3e2a3a1e))
* skip apt-get in Playwright browser install on cache hit ([b33e901](https://github.com/WordPress/sync-storage/commit/b33e901695572e146fd76d8c784876b8935b3ee5))
* warn when Gutenberg build silently skips sync-storage's filter ([a86520c](https://github.com/WordPress/sync-storage/commit/a86520c9122ca59e622f7fbe6585d667ae0b38e8))
* warn when Gutenberg build silently skips sync-storage's filter ([4d7df00](https://github.com/WordPress/sync-storage/commit/4d7df00e238ac946d3ae2d28071bad788c599102))

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
