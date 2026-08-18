/**
 * Collaborative editing test utilities.
 *
 * Utilities for testing multi-user collaborative scenarios in WordPress.
 */
import type { Browser, BrowserContext, Page } from '@playwright/test';
import { Admin, Editor, PageUtils, RequestUtils } from '@wordpress/e2e-test-utils-playwright';

export interface CollaborativeSession {
	context: BrowserContext;
	page: Page;
	admin: Admin;
	requestUtils: RequestUtils;
	userId: number;
	userName: string;
	clientId: number;
}

export interface SyncPollResult {
	end_cursor: number;
	room: string;
	should_compact: boolean;
	total_updates: number;
	updates: Array<{ type: string; data: string }>;
	awareness: Record<string, unknown>;
}

export interface PresenceEntry {
	client_id: string;
	user_id: number;
	display_name: string;
	data: Record<string, unknown>;
	date_gmt: string;
}

const ADMIN_USERNAME = process.env.WP_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.WP_PASSWORD || 'password';
const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';
const COLLABORATOR_PASSWORD = 'sync-storage-e2e-collaborator!1';

/**
 * Create (or reuse, from a previous run) a distinct editor user for a collaborative session.
 *
 * @param admin    Admin-authenticated RequestUtils, used to provision the collaborator.
 * @param username Username for the collaborator.
 */
async function ensureCollaborator(
	admin: RequestUtils,
	username: string
): Promise<{ id: number }> {
	try {
		return await admin.createUser({
			username,
			email: `${username}@example.test`,
			password: COLLABORATOR_PASSWORD,
			roles: ['editor'],
		});
	} catch (error) {
		if ((error as { code?: string })?.code !== 'existing_user_login') {
			throw error;
		}
	}

	const matches: Array<{ id: number; slug: string }> = await admin.rest({
		path: `/wp/v2/users?search=${encodeURIComponent(username)}&context=edit`,
	});
	const existing = matches.find((user) => user.slug === username);
	if (!existing) {
		throw new Error(
			`Collaborator "${username}" already exists but could not be found via search.`
		);
	}

	// Reset the password so this run's login credentials are known, regardless
	// of what a previous run left behind.
	await admin.rest({
		method: 'POST',
		path: `/wp/v2/users/${existing.id}`,
		data: { password: COLLABORATOR_PASSWORD },
	});

	return { id: existing.id };
}

/**
 * Create multiple collaborative sessions (different users editing simultaneously).
 *
 * Each session is a separate browser context authenticated as its own
 * WordPress editor user, so cookies, capabilities, and presence all behave
 * like real concurrent collaborators instead of one user in multiple tabs.
 *
 * @param browser Playwright browser instance
 * @param count Number of concurrent users to simulate
 * @returns Array of collaborative sessions
 */
export async function createCollaborativeSessions(
	browser: Browser,
	count: number = 2
): Promise<CollaborativeSession[]> {
	const admin = await RequestUtils.setup({
		user: { username: ADMIN_USERNAME, password: ADMIN_PASSWORD },
		baseURL: BASE_URL,
	});

	const sessions: CollaborativeSession[] = [];

	for (let i = 0; i < count; i++) {
		const userName = `sync-storage-editor-${i + 1}`;
		const { id: userId } = await ensureCollaborator(admin, userName);

		const context = await browser.newContext();
		const requestUtils = new RequestUtils(context.request, {
			user: { username: userName, password: COLLABORATOR_PASSWORD },
			baseURL: BASE_URL,
		});
		// Logging in via context.request shares this context's cookie jar, so
		// the browser `page` below is authenticated as this collaborator too.
		await requestUtils.login();

		const page = await context.newPage();
		const sessionAdmin = new Admin({
			page,
			pageUtils: new PageUtils({ page }),
			editor: new Editor({ page }),
		});

		sessions.push({
			context,
			page,
			admin: sessionAdmin,
			requestUtils,
			userId,
			userName,
			clientId: 1000 + i,
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
 * Room identifier for a post, matching Sync_Storage_Provider's expected format.
 *
 * @param postId Post ID.
 */
export function postRoom(postId: number): string {
	return `postType/post:${postId}`;
}

/**
 * Poll the real Gutenberg sync REST endpoint (`/wp-sync/v1/updates`) as a given
 * session. This drives updates and awareness through the actual REST -> filter
 * -> Sync_Storage_Provider path, not a mock.
 *
 * @param session Collaborative session making the request.
 * @param room    Room identifier.
 * @param options Polling options (cursor, awareness payload, updates to send).
 */
export async function pollSync(
	session: CollaborativeSession,
	room: string,
	options: {
		after?: number;
		awareness?: Record<string, unknown> | null;
		updates?: Array<{ type: string; data: string }>;
		clientId?: number;
	} = {}
): Promise<SyncPollResult> {
	const response = await session.requestUtils.rest({
		method: 'POST',
		path: '/wp-sync/v1/updates',
		data: {
			rooms: [
				{
					room,
					client_id: options.clientId ?? session.clientId,
					after: options.after ?? 0,
					awareness: options.awareness ?? null,
					updates: options.updates ?? [],
				},
			],
		},
	});

	return response.rooms[0];
}

/**
 * Read live presence entries for a room via the Presence API's own REST
 * endpoint, as a given session. Used to verify awareness written by one
 * collaborator is visible to another.
 *
 * @param session Collaborative session making the request.
 * @param room    Room identifier.
 */
export async function getPresence(
	session: CollaborativeSession,
	room: string
): Promise<PresenceEntry[]> {
	return session.requestUtils.rest({
		path: `/wp-presence/v1/presence?room=${encodeURIComponent(room)}`,
	});
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
