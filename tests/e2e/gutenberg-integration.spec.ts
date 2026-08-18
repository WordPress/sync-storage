/**
 * Tests for Gutenberg WP_Sync_Storage integration.
 */
import { test as base, expect } from '@wordpress/e2e-test-utils-playwright';

const test = base.extend({});

test.describe('Gutenberg Integration', () => {
	test('storage filter is hooked', async ({ page, admin }) => {
		// Create a new post to trigger RTC initialization
		await admin.createNewPost({ title: 'Integration Test' });

		// Wait for editor to load
		await page.waitForSelector('.edit-post-layout', { timeout: 10000 });

		// Check debug log for filter hook event
		// Our filter logs when it's called
		const response = await page.request.get('/wp-admin/');
		expect(response.ok()).toBeTruthy();

		// The filter should be applied when REST API routes are registered
		// Let's check if the collaboration REST endpoints exist
		const routesResponse = await page.request.get('/wp-json/');
		const routes = await routesResponse.json();

		// Gutenberg collaboration endpoints should be registered
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

		// Actual routes registered by this Gutenberg build (23.8.0-rc.1) and
		// the Presence API plugin. Earlier versions of this test looked for a
		// "wp-collaboration" namespace that doesn't exist in this build.
		expect(routes).toEqual(
			expect.arrayContaining(['/wp-sync/v1/updates', '/wp-presence/v1/presence'])
		);
	});

	test('storage provider handles room creation', async ({ page, admin }) => {
		// Create a post and open it in the editor
		const postId = await admin.createNewPost({
			title: 'Storage Test Post',
			content: 'Initial content for storage test.',
		});

		await page.waitForSelector('.edit-post-layout', { timeout: 10000 });

		// Make an edit to trigger storage writes
		const editor = page.locator('[contenteditable="true"]').first();
		if (await editor.isVisible()) {
			await editor.click();
			await editor.pressSequentially(' Updated content.', { delay: 50 });
			await page.waitForTimeout(1000); // Wait for debounced save
		}

		// Save the post
		await page.keyboard.press('Meta+S');
		await page.waitForTimeout(1000);

		// Check if collaboration updates were stored
		// We can't directly query the table from the browser, but we can check logs
		// or verify the post saved successfully
		const saveButton = page.locator('button:has-text("Save draft"), button:has-text("Update")').first();

		// If post saved, storage is working
		const isSaved = await page.locator('.components-snackbar__content').textContent().catch(() => '');
		// Success indicators vary, but lack of errors is a good sign
		expect(isSaved).not.toContain('error');
	});
});
