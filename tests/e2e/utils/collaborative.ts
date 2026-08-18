/**
 * Collaborative editing test utilities.
 *
 * Utilities for testing multi-user collaborative scenarios in WordPress.
 * This could evolve into @wordpress/e2e-test-utils-collaborative package.
 */
import type { Browser, BrowserContext, Page } from '@playwright/test';
import { Admin, RequestUtils } from '@wordpress/e2e-test-utils-playwright';

export interface CollaborativeSession {
	context: BrowserContext;
	page: Page;
	admin: Admin;
	requestUtils: RequestUtils;
	userId: number;
	userName: string;
}

/**
 * Create multiple collaborative sessions (different users editing simultaneously).
 *
 * @param browser Playwright browser instance
 * @param count Number of concurrent users to simulate
 * @returns Array of collaborative sessions
 */
export async function createCollaborativeSessions(
	browser: Browser,
	count: number = 2
): Promise<CollaborativeSession[]> {
	const sessions: CollaborativeSession[] = [];

	for (let i = 0; i < count; i++) {
		const context = await browser.newContext({
			storageState: undefined, // Fresh session for each user
		});
		const page = await context.newPage();
		const requestUtils = await RequestUtils.setup({
			baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
			storageState: undefined,
		});
		const admin = new Admin({ page, request: requestUtils });

		// Each session gets a unique user
		const userName = `editor${i + 1}`;
		const userId = i + 1;

		sessions.push({
			context,
			page,
			admin,
			requestUtils,
			userId,
			userName,
		});
	}

	return sessions;
}

/**
 * Open the same post in multiple editor sessions.
 *
 * @param sessions Collaborative sessions
 * @param postId Post ID to open
 */
export async function openPostInSessions(
	sessions: CollaborativeSession[],
	postId: number
): Promise<void> {
	await Promise.all(
		sessions.map(async ({ admin }) => {
			await admin.visitAdminPage(`post.php?post=${postId}&action=edit`);
		})
	);
}

/**
 * Wait for sync storage to be initialized.
 *
 * @param page Playwright page
 */
export async function waitForSyncStorage(page: Page): Promise<void> {
	await page.waitForFunction(() => {
		return window.wp?.data?.select('core/editor')?.getCurrentPost() !== null;
	});
}

/**
 * Monitor sync storage API calls.
 *
 * @param page Playwright page
 * @returns Array of captured storage calls
 */
export async function captureStorageCalls(
	page: Page
): Promise<Array<{ method: string; args: unknown[] }>> {
	const calls: Array<{ method: string; args: unknown[] }> = [];

	await page.exposeFunction('__syncStorageMonitor', (method: string, args: unknown[]) => {
		calls.push({ method, args });
	});

	// Inject monitoring into the page
	await page.addInitScript(() => {
		const originalStorage = window.__unstableStorageProvider;
		if (originalStorage) {
			const proxy = new Proxy(originalStorage, {
				get(target, prop) {
					const value = target[prop];
					if (typeof value === 'function') {
						return (...args: unknown[]) => {
							window.__syncStorageMonitor?.(String(prop), args);
							return value.apply(target, args);
						};
					}
					return value;
				},
			});
			window.__unstableStorageProvider = proxy;
		}
	});

	return calls;
}

/**
 * Type text in the editor and wait for sync.
 *
 * @param page Playwright page
 * @param text Text to type
 */
export async function typeInEditor(page: Page, text: string): Promise<void> {
	const editor = page.locator('[contenteditable="true"]').first();
	await editor.click();
	await editor.pressSequentially(text, { delay: 50 });

	// Wait for debounced sync
	await page.waitForTimeout(1000);
}

/**
 * Get the current editor content.
 *
 * @param page Playwright page
 * @returns Editor content as plain text
 */
export async function getEditorContent(page: Page): Promise<string> {
	return await page.evaluate(() => {
		const editor = window.wp?.data?.select('core/editor');
		return editor?.getEditedPostContent() || '';
	});
}

/**
 * Wait for content to sync between sessions.
 *
 * @param sessions Collaborative sessions
 * @param expectedContent Expected content in all sessions
 * @param timeout Timeout in milliseconds
 */
export async function waitForContentSync(
	sessions: CollaborativeSession[],
	expectedContent: string,
	timeout: number = 5000
): Promise<void> {
	const startTime = Date.now();

	while (Date.now() - startTime < timeout) {
		const contents = await Promise.all(
			sessions.map(({ page }) => getEditorContent(page))
		);

		if (contents.every((content) => content.includes(expectedContent))) {
			return;
		}

		await new Promise((resolve) => setTimeout(resolve, 100));
	}

	throw new Error(`Content did not sync within ${timeout}ms`);
}

/**
 * Clean up collaborative sessions.
 *
 * @param sessions Collaborative sessions to close
 */
export async function closeCollaborativeSessions(
	sessions: CollaborativeSession[]
): Promise<void> {
	await Promise.all(
		sessions.map(async ({ context }) => {
			await context.close();
		})
	);
}
