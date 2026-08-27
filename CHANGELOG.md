# Changelog

## [0.1.10](https://github.com/WordPress/sync-storage/compare/v0.1.9...v0.1.10) (2026-08-26)


### Bug Fixes

* clear the cleanup cron when the plugin is deactivated ([#77](https://github.com/WordPress/sync-storage/issues/77)) ([a934109](https://github.com/WordPress/sync-storage/commit/a93410965c4f705fffef853449a68ee86a49e823))
* guard GUTENBERG_VERSION in the build id ([#82](https://github.com/WordPress/sync-storage/issues/82)) ([baf159e](https://github.com/WordPress/sync-storage/commit/baf159e416f6b15e114f92029964457a00589a7b))
* register the deactivation hook above the dependency guards ([#80](https://github.com/WordPress/sync-storage/issues/80)) ([7962a4c](https://github.com/WordPress/sync-storage/commit/7962a4c58424f8aff9ce8647350df609f26edf86))
* scope the Presence API guard to the features that need it ([#84](https://github.com/WordPress/sync-storage/issues/84)) ([c5d703a](https://github.com/WordPress/sync-storage/commit/c5d703a21238409a9606b08749be5440f2f0f5f8)), closes [#81](https://github.com/WordPress/sync-storage/issues/81)
* strip PR links from the generated readme.txt changelog ([#83](https://github.com/WordPress/sync-storage/issues/83)) ([8c3d2fc](https://github.com/WordPress/sync-storage/commit/8c3d2fc6fb5b8eb4e6411d402f7d56dc56c98f6b))

## [0.1.9](https://github.com/WordPress/sync-storage/compare/v0.1.8...v0.1.9) (2026-08-25)


### Bug Fixes

* key the filter-support cache on the Gutenberg build, not its version ([6c72edc](https://github.com/WordPress/sync-storage/commit/6c72edc484fbe1b569d1d5090792094da6123a68))

## [0.1.8](https://github.com/WordPress/sync-storage/compare/v0.1.7...v0.1.8) (2026-08-24)


### Bug Fixes

* batch-delete stale updates in a loop so cleanup can catch up ([b27296f](https://github.com/WordPress/sync-storage/commit/b27296fa601279c3a6bdc976af4855a79e7125d4))
* bound every CI job with a timeout and stop masking install failures ([3401ef3](https://github.com/WordPress/sync-storage/commit/3401ef325212a7b95683a460016fe8a3dceb06fa))
* fix stale plugin filename in phpunit.xml.dist ([ea3fbec](https://github.com/WordPress/sync-storage/commit/ea3fbec648dafd2ea900255e75dd96b0820e0364))
* make PHPStan actually analyse the plugin ([c270efc](https://github.com/WordPress/sync-storage/commit/c270efcfcbf3db53a2128b1ad8f8d66466e9e4a9)), closes [#57](https://github.com/WordPress/sync-storage/issues/57)
* make the Playground blueprints activate the plugin ([1582453](https://github.com/WordPress/sync-storage/commit/158245348096fd28c32a4fbc7476edf9d2f12743))
* make waitForSyncStorage() actually wait for the storage provider ([3492805](https://github.com/WordPress/sync-storage/commit/3492805a2c956a87b2bb2e0a0c60150f431d98cc))
* provision existing sites on network activation ([d0612be](https://github.com/WordPress/sync-storage/commit/d0612beb96ce2f9a373296acca50136427729f9a))
* stop writing the post meta flag nothing reads ([830a4a0](https://github.com/WordPress/sync-storage/commit/830a4a04d15b5a57606ca83d338a5980da35f0f8)), closes [#56](https://github.com/WordPress/sync-storage/issues/56)
* strip closes clauses when generating readme.txt's changelog ([78be0b4](https://github.com/WordPress/sync-storage/commit/78be0b491afc38b03db6a8328fe57d8faaea9d1e))

## [0.1.7](https://github.com/WordPress/sync-storage/compare/v0.1.6...v0.1.7) (2026-08-20)


### Bug Fixes

* bound Playwright browser install with a timeout and retry ([dff75ea](https://github.com/WordPress/sync-storage/commit/dff75ea5f5ac9c9ae15e2a04ad672572fc4a3a1d))

## [0.1.6](https://github.com/WordPress/sync-storage/compare/v0.1.5...v0.1.6) (2026-08-19)


### Bug Fixes

* make playwright.yml workflow_call-only like its CI siblings ([128c911](https://github.com/WordPress/sync-storage/commit/128c911cb59ed5e92f0abbbfaaae29f124860663))

## [0.1.5](https://github.com/WordPress/sync-storage/compare/v0.1.4...v0.1.5) (2026-08-19)


### Bug Fixes

* activate Gutenberg's real-time-collaboration experiment on plugin load ([230373c](https://github.com/WordPress/sync-storage/commit/230373c02d64ba0ff915b070d03a6e0987b6a963))
* give CI a .env so REST discovery hits the right wp-env port ([d62a2b8](https://github.com/WordPress/sync-storage/commit/d62a2b88dd0273da323ec341e338759c9894f656))
* give collaborative Playwright tests enough time on CI ([cec0720](https://github.com/WordPress/sync-storage/commit/cec072000bba874f0d7f52efa4f9e0faaf0ac2ec))
* give release-please branches a real success, not skipped, on required checks ([8d09690](https://github.com/WordPress/sync-storage/commit/8d09690f955499ea5540eb306972cf9fcee1672d))
* point Playwright readiness check at /wp-json/ to warm REST routes ([728d2c9](https://github.com/WordPress/sync-storage/commit/728d2c9ea5707c0e82ebd3d72cfa6514863feab2))
* provision e2e collaborator users via WP-CLI instead of REST ([5a0148c](https://github.com/WordPress/sync-storage/commit/5a0148c65077298013b9eb2fe18edeeeb673fec0))
* reach the editor canvas iframe in the room-creation e2e test ([07459c0](https://github.com/WordPress/sync-storage/commit/07459c0b029e9c6773e8204257a1a5fcaf14c258))
* remove global-setup REST warm-up that hung CI for 30 minutes ([787f557](https://github.com/WordPress/sync-storage/commit/787f55786046575bc9f10690f68f9724e9b7344c))
* resolve zizmor code-scanning findings ([8593000](https://github.com/WordPress/sync-storage/commit/8593000bafa665f2eeea8d5e80ed07825830ea2b))
* skip apt-get in Playwright browser install on cache hit ([b33e901](https://github.com/WordPress/sync-storage/commit/b33e901695572e146fd76d8c784876b8935b3ee5))
* warn when Gutenberg build silently skips sync-storage's filter ([a86520c](https://github.com/WordPress/sync-storage/commit/a86520c9122ca59e622f7fbe6585d667ae0b38e8))

## [0.1.4](https://github.com/WordPress/sync-storage/compare/v0.1.3...v0.1.4) (2026-08-18)


### Bug Fixes

* stop Playwright from starting a second wp-env instance in CI ([8c308b9](https://github.com/WordPress/sync-storage/commit/8c308b9b1fd5f3a1d3aeab88f29278fb4e9bc766))
* stop plugin-check's isolated wp-env from crashing on Requires Plugins ([4d30914](https://github.com/WordPress/sync-storage/commit/4d30914f337c3240e8a2c2557e215568ba22e327))
* sync all three version copies, not just the plugin header ([1880d4e](https://github.com/WordPress/sync-storage/commit/1880d4e10ef58821b1c3d89b4d3f715e5e22315a))


### Performance Improvements

* cache Gutenberg trunk checkout and Playwright browsers in CI ([5b316fd](https://github.com/WordPress/sync-storage/commit/5b316fd5363b0abd0f9b7b2e716b3371181db2c4))

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
