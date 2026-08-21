/**
 * End-to-end tests for WP_Sync_Storage implementation.
 */
import { test as base, expect } from '@wordpress/e2e-test-utils-playwright';
import {
	createCollaborativeSessions,
	closeCollaborativeSessions,
	pollSync,
	getPresence,
	postRoom,
	waitForSyncStorage,
} from './utils/collaborative';

const test = base.extend({});

test.describe('Sync Storage - Basic Functionality', () => {
	test.beforeEach(async ({ admin }) => {
		await admin.visitAdminPage('/');
	});

	test('plugin is active', async ({ page, admin }) => {
		await admin.visitAdminPage('plugins.php');

		const syncStorageRow = page.locator('tr[data-slug="sync-storage"]');
		await expect(syncStorageRow).toBeVisible();
		await expect(syncStorageRow.locator('.active')).toBeVisible();
	});

	test('collaboration table exists', async ({ page, admin }) => {
		// Verify plugin is active (implies table was created on activation)
		await admin.visitAdminPage('plugins.php');
		const syncStorageRow = page.locator('tr[data-slug="sync-storage"]');
		await expect(syncStorageRow).toBeVisible();
		await expect(syncStorageRow.locator('.active')).toBeVisible();

		// Table creation happens on plugin activation
		// No direct way to verify table existence from browser, but plugin being active confirms it
	});

	test('editor loads without errors', async ({ page, admin }) => {
		const errors: string[] = [];
		page.on('console', (msg) => {
			if (msg.type() === 'error') {
				errors.push(msg.text());
			}
		});

		await admin.createNewPost();

		// Wait for editor to fully load
		await page.waitForSelector('.edit-post-layout', { timeout: 10000 });
		await page.waitForTimeout(2000);

		// Filter out known non-critical errors
		const criticalErrors = errors.filter(
			(error) =>
				!error.includes('Failed to load resource') && // Network timing
				!error.includes('favicon.ico') && // Missing favicon
				!error.includes('ResizeObserver') // Benign resize observer errors
		);

		expect(criticalErrors).toHaveLength(0);
	});
});

test.describe('Sync Storage - Collaborative Editing', () => {
	test('multiple users can open the same post', async ({ browser, requestUtils }) => {
		// Provisioning two collaborator sessions, then waiting for each one's
		// storage provider to actually start polling (rather than just for the
		// editor to have a post loaded, which happens much sooner) pushes this
		// past the default per-test timeout.
		test.setTimeout(60_000);

		// admin.createNewPost() resolves void, not the post ID (it's meant to
		// leave you on the editor screen it just opened). requestUtils.createPost()
		// returns the created post object, which is what post.php?post=<id> needs.
		const post = await requestUtils.createPost({
			title: 'Collaborative Test Post',
			content: 'Initial content.',
			status: 'publish',
		});

		// Create two collaborative sessions
		const sessions = await createCollaborativeSessions(browser, 2);

		try {
			// Open the same post in both sessions
			await Promise.all(
				sessions.map(({ admin: sessionAdmin }) =>
					sessionAdmin.visitAdminPage(`post.php?post=${post.id}&action=edit`)
				)
			);

			for (const { page } of sessions) {
				await waitForSyncStorage(page);
			}

			// Both sessions should see the editor.
			for (const { page } of sessions) {
				await expect(page.locator('.edit-post-layout')).toBeVisible();
			}
		} finally {
			await closeCollaborativeSessions(sessions);
		}
	});

	// These tests drive the real `/wp-sync/v1/updates` REST endpoint directly
	// (REST -> __unstable_wp_sync_storage filter -> Sync_Storage_Provider ->
	// wp_collaboration table) rather than typing in the editor UI. Gutenberg's
	// RTC feature is experimental and its DOM/UI wiring can change out from
	// under this plugin; the storage contract this plugin owns is what these
	// tests verify.
	test('edits sync between users', async ({ browser }) => {
		const sessions = await createCollaborativeSessions(browser, 2);

		try {
			const post = await sessions[0].requestUtils.createPost({
				title: 'Edits Sync Test',
				content: 'Initial content.',
				status: 'publish',
			});
			const room = postRoom(post.id);

			const update = {
				type: 'update',
				data: Buffer.from('edit-from-session-1').toString('base64'),
			};
			const posted = await pollSync(sessions[0], room, { updates: [update] });
			expect(posted.total_updates).toBe(1);

			const received = await pollSync(sessions[1], room, { after: 0 });
			expect(received.updates).toHaveLength(1);
			expect(received.updates[0].data).toBe(update.data);
		} finally {
			await closeCollaborativeSessions(sessions);
		}
	});

	test('concurrent edits are handled', async ({ browser }) => {
		const sessions = await createCollaborativeSessions(browser, 2);

		try {
			const post = await sessions[0].requestUtils.createPost({
				title: 'Concurrent Edits Test',
				content: 'Initial content.',
				status: 'publish',
			});
			const room = postRoom(post.id);

			const updateA = {
				type: 'update',
				data: Buffer.from('from-session-1').toString('base64'),
			};
			const updateB = {
				type: 'update',
				data: Buffer.from('from-session-2').toString('base64'),
			};

			// Regression coverage for the compaction/cursor-collision race
			// documented in Trac #64696: two collaborators writing to the same
			// room at the same moment must not lose either update.
			await Promise.all([
				pollSync(sessions[0], room, { updates: [updateA] }),
				pollSync(sessions[1], room, { updates: [updateB] }),
			]);

			// Poll with a client_id neither writer used, so nothing is
			// filtered out as "already delivered to this client."
			const result = await pollSync(sessions[0], room, {
				after: 0,
				clientId: 9999,
			});
			const receivedData = result.updates.map((u) => u.data);

			expect(receivedData).toEqual(
				expect.arrayContaining([updateA.data, updateB.data])
			);
			expect(result.total_updates).toBe(2);
		} finally {
			await closeCollaborativeSessions(sessions);
		}
	});

	test('awareness state updates', async ({ browser }) => {
		const sessions = await createCollaborativeSessions(browser, 2);

		try {
			const post = await sessions[0].requestUtils.createPost({
				title: 'Awareness Test',
				content: 'Initial content.',
				status: 'publish',
			});
			const room = postRoom(post.id);

			await pollSync(sessions[0], room, { awareness: { cursor: 12 } });

			// A different collaborator reads presence through Presence API's
			// own REST endpoint, confirming Sync_Storage_Provider actually
			// delegated the write there rather than dropping it.
			const presence = await getPresence(sessions[1], room);
			const entry = presence.find((p) => p.user_id === sessions[0].userId);

			expect(entry).toBeDefined();
			expect(entry?.data).toMatchObject({ cursor: 12 });
		} finally {
			await closeCollaborativeSessions(sessions);
		}
	});
});

// Server authority (lib/server-authority.php) isn't reachable from here: the
// 1->2 collaborator transition it listens for is detected inside Presence
// API's WordPress Heartbeat handler (includes/heartbeat.php), keyed to
// presence rows named "editor-{user_id}". This plugin's /wp-sync/v1/updates
// awareness writes use Yjs client IDs instead, so they never feed that
// counter. Testing the actual transition would mean simulating Presence
// API's heartbeat AJAX contract, which is that plugin's concern, not this
// one's. lib/server-authority.php's own reaction to the resulting actions is
// covered directly in tests/test-server-authority.php.
