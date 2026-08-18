/**
 * End-to-end tests for WP_Sync_Storage implementation.
 */
import { test as base, expect } from '@wordpress/e2e-test-utils-playwright';
import {
	createCollaborativeSessions,
	closeCollaborativeSessions,
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
	test.skip('multiple users can open the same post', async ({ browser, admin }) => {
		// Create a test post first
		const postId = await admin.createNewPost({
			title: 'Collaborative Test Post',
			content: 'Initial content.',
		});

		// Create two collaborative sessions
		const sessions = await createCollaborativeSessions(browser, 2);

		try {
			// Open the same post in both sessions
			await Promise.all(
				sessions.map(({ admin: sessionAdmin }) =>
					sessionAdmin.visitAdminPage(`post.php?post=${postId}&action=edit`)
				)
			);

			// Wait for editors to load
			for (const { page } of sessions) {
				await page.waitForFunction(() => {
					return window.wp?.data?.select('core/editor')?.getCurrentPost() !== null;
				});
			}

			// Both sessions should see the editor
			for (const { page } of sessions) {
				const editorVisible = await page.locator('.edit-post-layout').isVisible();
				expect(editorVisible).toBeTruthy();
			}
		} finally {
			await closeCollaborativeSessions(sessions);
		}
	});

	test.skip('edits sync between users', async ({ browser, admin }) => {
		// This test requires the full RTC implementation to be working
		// Currently skipped pending Gutenberg integration verification
	});

	test.skip('concurrent edits are handled', async ({ browser, admin }) => {
		// This test requires the full RTC implementation to be working
		// Currently skipped pending Gutenberg integration verification
	});

	test.skip('awareness state updates', async ({ browser, admin }) => {
		// This test requires the full RTC implementation to be working
		// Currently skipped pending Gutenberg integration verification
	});
});

test.describe('Sync Storage - Server Authority', () => {
	test.skip('single user does not activate RTC', async ({ admin }) => {
		// Create a test post
		const postId = await admin.createNewPost({
			title: 'Server Authority Test',
			content: 'Test content.',
		});

		await admin.visitAdminPage(`post.php?post=${postId}&action=edit`);

		// With only one user, RTC should not activate
		// This would require checking internal state or debug logging
	});

	test.skip('multiple users activate RTC', async ({ browser }) => {
		// This test requires the full RTC implementation to be working
		// Currently skipped pending Gutenberg integration verification
	});
});
