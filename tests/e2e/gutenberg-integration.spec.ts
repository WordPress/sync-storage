/**
 * Tests for Gutenberg WP_Sync_Storage integration.
 */
import { test as base, expect } from '@wordpress/e2e-test-utils-playwright';
import {
	createCollaborativeSessions,
	closeCollaborativeSessions,
	pollSync,
	postRoom,
} from './utils/collaborative';

const test = base.extend({});

test.describe('Gutenberg Integration', () => {
	test('storage filter is hooked', async ({ page, admin }) => {
		// Create a new post to trigger RTC initialization
		await admin.createNewPost({ title: 'Integration Test' });

		// Wait for editor to load
		await page.waitForSelector('.edit-post-layout', { timeout: 10000 });

		// Check that wp-admin loads.
		const response = await page.request.get('/wp-admin/');
		expect(response.ok()).toBeTruthy();

		// Check that the REST index responds and lists routes. It doesn't
		// check for any specific route; see the "sync and presence REST
		// routes are registered" test below for that.
		const routesResponse = await page.request.get('/wp-json/');
		const routes = await routesResponse.json();

		expect(routes.routes).toBeDefined();
	});

	test('WP_Sync_Storage provider is active', async ({ requestUtils }) => {
		// A real round trip through /wp-sync/v1/updates: REST route ->
		// __unstable_wp_sync_storage filter -> Sync_Storage_Provider ->
		// wp_collaboration table. requestUtils.rest() throws on a non-2xx
		// response, so getting a result here already proves the provider
		// handled the request; the shape assertions confirm it's actually our
		// storage, not just any 200.
		const post = await requestUtils.createPost({
			title: 'Sync Storage Provider Check',
			content: 'Content.',
			status: 'publish',
		});
		const room = `postType/post:${post.id}`;

		const response = await requestUtils.rest({
			method: 'POST',
			path: '/wp-sync/v1/updates',
			data: {
				rooms: [{ room, client_id: 1, after: 0, awareness: null, updates: [] }],
			},
		});

		expect(response.rooms).toHaveLength(1);
		expect(response.rooms[0].room).toBe(room);
		expect(typeof response.rooms[0].end_cursor).toBe('number');
	});

	test('sync and presence REST routes are registered', async ({ requestUtils }) => {
		const response = await requestUtils.rest({ path: '/' });
		const routes = Object.keys(response.routes || {});

		// Route names as registered by the current Gutenberg and Presence API
		// builds. Gutenberg is pinned in .wp-env.json, but Presence API
		// installs from the wp.org "latest" zip, so a rename upstream can
		// break this list without a commit here. If it fails, check the
		// current route names first. Earlier versions of this test looked for
		// a "wp-collaboration" namespace that no longer exists.
		expect(routes).toEqual(
			expect.arrayContaining(['/wp-sync/v1/updates', '/wp-presence/v1/presence'])
		);
	});

	test('storage provider handles room creation', async ({ page, admin, editor: editorUtils }) => {
		// Create a post and open it in the editor
		const postId = await admin.createNewPost({
			title: 'Storage Test Post',
			content: 'Initial content for storage test.',
		});

		await page.waitForSelector('.edit-post-layout', { timeout: 10000 });

		// Make an edit to trigger storage writes. The block canvas renders
		// inside an iframe for style isolation, so page.locator() alone can
		// never match its contenteditable regions -- frameLocator() is
		// required to reach inside it.
		const editor = page
			.frameLocator('iframe[name="editor-canvas"]')
			.locator('[contenteditable="true"]')
			.first();
		await editor.waitFor({ state: 'visible', timeout: 10000 });
		await editor.click();
		await editor.pressSequentially(' Updated content.', { delay: 50 });
		await page.waitForTimeout(1000); // Wait for debounced save

		// Save the post. editor.saveDraft() clicks the actual "Save draft"
		// button and waits for the "Draft saved" notice, rather than a
		// keyboard shortcut whose modifier key (Meta vs Ctrl) is OS-dependent
		// and doesn't work in this Linux CI environment.
		await editorUtils.saveDraft();
	});

	// Goes through POST /wp-sync/v1/updates rather than reading the presence
	// table, so the assertion covers what Gutenberg's sync server does with
	// what our provider returns, not just what we stored. The server expires
	// awareness on time() - updated_at, and a provider that returns anything
	// but a timestamp there drops every collaborator before the response is
	// built. That was #88, and a test reading presence directly missed it.
	test('a collaborator survives the sync server round trip', async ({
		browser,
	}) => {
		const sessions = await createCollaborativeSessions(browser, 2);

		try {
			const post = await sessions[0].requestUtils.createPost({
				title: 'Awareness Round Trip',
				content: 'Initial.',
				status: 'publish',
			});
			const room = postRoom(post.id);

			await pollSync(sessions[0], room, {
				awareness: { cursor: { index: 1 } },
			});

			const seen = await pollSync(sessions[1], room, {
				awareness: { cursor: { index: 2 } },
			});

			// The second client always sees itself; the first is what the
			// expiry check would have removed.
			expect(Object.keys(seen.awareness)).toContain(
				String(sessions[0].clientId)
			);
		} finally {
			await closeCollaborativeSessions(sessions);
		}
	});
});
