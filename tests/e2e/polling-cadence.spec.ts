/**
 * Measured cadence of the polling loop.
 *
 * The request-per-hour figures in the RTC cost discussion are the interval
 * constants turned into arithmetic. That is only sound if the loop actually
 * runs at those intervals in a browser, so these tests count real requests
 * from a real editor over a real window rather than reading the constants.
 *
 * Counts are asserted as ranges. The exact number in a window depends on where
 * the window starts relative to the loop, and on how long the editor takes to
 * boot; the claim under test is the rate, not a specific integer.
 */
import { test as base, expect } from '@wordpress/e2e-test-utils-playwright';
import type { Page } from '@playwright/test';
import {
	createCollaborativeSessions,
	closeCollaborativeSessions,
	waitForSyncStorage,
} from './utils/collaborative';

const test = base.extend({});

/**
 * Window over which requests are counted. Long enough that the 4s solo
 * interval produces a sample worth dividing.
 */
const SAMPLE_MS = 30000;

/** Observed requests scaled to an hourly rate. */
function perHour(requests: number): number {
	return Math.round((requests / SAMPLE_MS) * 3600000);
}

/**
 * Count POSTs to the sync endpoint made by a page over a fixed window.
 *
 * @param page     Page to observe.
 * @param windowMs How long to count for.
 */
async function countSyncRequests(page: Page, windowMs: number): Promise<number> {
	return (await sampleSyncRequests(page, windowMs)).length;
}

/**
 * Timestamps of every sync POST a page makes over a fixed window, relative to
 * the start of the window. The gaps between them are the observed interval.
 *
 * @param page     Page to observe.
 * @param windowMs How long to sample for.
 */
async function sampleSyncRequests(
	page: Page,
	windowMs: number
): Promise<number[]> {
	const startedAt = Date.now();
	const at: number[] = [];

	const onRequest = (request: { method(): string; url(): string }) => {
		const url = decodeURIComponent(request.url());
		if (
			request.method() === 'POST' &&
			(url.includes('/wp-json/wp-sync/v1/updates') ||
				url.includes('rest_route=/wp-sync/v1/updates'))
		) {
			at.push(Date.now() - startedAt);
		}
	};

	page.on('request', onRequest);
	await new Promise((resolve) => setTimeout(resolve, windowMs));
	page.off('request', onRequest);

	return at;
}

test.describe('Polling cadence', () => {
	test('a solo editor polls at the default interval', async ({ browser }) => {
		// Editor boot, then the sample window.
		test.setTimeout(120000);

		const sessions = await createCollaborativeSessions(browser, 1);

		try {
			const post = await sessions[0].requestUtils.createPost({
				title: 'Polling Cadence',
				content: 'Measured, not inferred.',
				status: 'publish',
			});

			await sessions[0].admin.visitAdminPage(
				`post.php?post=${post.id}&action=edit`
			);
			await waitForSyncStorage(sessions[0].page);

			const requests = await countSyncRequests(sessions[0].page, SAMPLE_MS);

			// eslint-disable-next-line no-console
			console.log(
				`solo: ${requests} requests in ${SAMPLE_MS}ms (~${perHour(requests)}/hour)`
			);

			// The 4s interval gives 7 in a 30s window. The bound that matters is
			// the upper one: at the 1s collaborator rate this would be near 30.
			expect(requests).toBeGreaterThanOrEqual(5);
			expect(requests).toBeLessThan(12);
		} finally {
			await closeCollaborativeSessions(sessions);
		}
	});

	test('a second tab of the same user opens the collaborator rate', async ({
		browser,
	}) => {
		// Editor boot for two tabs, then a 30s sample.
		test.setTimeout(120000);

		const sessions = await createCollaborativeSessions(browser, 1);

		try {
			const post = await sessions[0].requestUtils.createPost({
				title: 'Polling Cadence - Two Tabs',
				content: 'Measured, not inferred.',
				status: 'publish',
			});
			const editUrl = `post.php?post=${post.id}&action=edit`;

			await sessions[0].admin.visitAdminPage(editUrl);
			await waitForSyncStorage(sessions[0].page);

			// A second tab in the same context: same user, same cookies, same
			// browser. Only the Yjs client ID differs.
			const second = await sessions[0].context.newPage();
			await second.goto(
				`${sessions[0].page.url().split('/wp-admin')[0]}/wp-admin/${editUrl}`
			);
			await waitForSyncStorage(second);

			// Bring the first tab back to the foreground; opening the second
			// backgrounded it, and the interval is gated on visibilityState.
			await sessions[0].page.bringToFront();

			// Both tabs are sampled over the same window. Counting only the
			// foreground tab would understate the cost of the person, which is
			// the sum of every connection they hold open.
			const [front, back] = await Promise.all([
				sampleSyncRequests(sessions[0].page, SAMPLE_MS),
				sampleSyncRequests(second, SAMPLE_MS),
			]);

			// Which interval each tab is on is a function of its visibility, so
			// the counts only mean something alongside it.
			const frontVisibility = await sessions[0].page.evaluate(
				() => document.visibilityState
			);
			const backVisibility = await second.evaluate(
				() => document.visibilityState
			);
			const gaps = front.slice(1).map((t, i) => t - front[i]);
			const total = front.length + back.length;

			// eslint-disable-next-line no-console
			console.log(
				`two tabs over ${SAMPLE_MS}ms:\n` +
					`  front (${frontVisibility}): ${front.length} (~${perHour(front.length)}/hour)\n` +
					`  back  (${backVisibility}): ${back.length} (~${perHour(back.length)}/hour)\n` +
					`  total for one person: ${total} (~${perHour(total)}/hour)\n` +
					`  front gaps: ${gaps.join(', ')}`
			);

			await second.close();

			// One person, one post, one browser. A second tab reading as a
			// collaborator puts the foreground tab on the 1s rate, which over
			// this window is several times the solo count asserted above.
			expect(front.length).toBeGreaterThanOrEqual(20);
		} finally {
			await closeCollaborativeSessions(sessions);
		}
	});

	test('a hidden second tab still holds the foreground tab at the collaborator rate', async ({
		browser,
	}) => {
		test.setTimeout(120000);

		const sessions = await createCollaborativeSessions(browser, 1);

		try {
			const post = await sessions[0].requestUtils.createPost({
				title: 'Polling Cadence - Hidden Tab',
				content: 'Measured, not inferred.',
				status: 'publish',
			});
			const editUrl = `post.php?post=${post.id}&action=edit`;

			await sessions[0].admin.visitAdminPage(editUrl);
			await waitForSyncStorage(sessions[0].page);

			const second = await sessions[0].context.newPage();
			await second.goto(
				`${sessions[0].page.url().split('/wp-admin')[0]}/wp-admin/${editUrl}`
			);
			await waitForSyncStorage(second);

			// Headless Chromium keeps every page visible regardless of which is
			// in front, so the background case has to be induced. The loop
			// branches on document.visibilityState and reschedules on
			// visibilitychange, so overriding both reproduces a backgrounded tab
			// exactly as the code under test perceives one.
			await second.evaluate(() => {
				Object.defineProperty(document, 'visibilityState', {
					configurable: true,
					get: () => 'hidden',
				});
				Object.defineProperty(document, 'hidden', {
					configurable: true,
					get: () => true,
				});
				document.dispatchEvent(new Event('visibilitychange'));
			});

			const [front, back] = await Promise.all([
				sampleSyncRequests(sessions[0].page, SAMPLE_MS),
				sampleSyncRequests(second, SAMPLE_MS),
			]);

			const total = front.length + back.length;

			// eslint-disable-next-line no-console
			console.log(
				`one visible tab plus one hidden tab over ${SAMPLE_MS}ms:\n` +
					`  front (${await sessions[0].page.evaluate(
						() => document.visibilityState
					)}): ${front.length} (~${perHour(front.length)}/hour)\n` +
					`  back  (${await second.evaluate(
						() => document.visibilityState
					)}): ${back.length} (~${perHour(back.length)}/hour)\n` +
					`  total for one person: ${total} (~${perHour(total)}/hour)`
			);

			await second.close();

			// The hidden tab polls at 25s, so it barely registers in this window.
			// It still occupies an awareness slot, which is what keeps the
			// visible tab on the 1s rate rather than its 4s solo rate.
			expect(back.length).toBeLessThanOrEqual(3);
			expect(front.length).toBeGreaterThanOrEqual(20);
		} finally {
			await closeCollaborativeSessions(sessions);
		}
	});
});
