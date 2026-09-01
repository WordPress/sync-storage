/**
 * Measured cost of the polling path.
 *
 * The per-poll query count is the number the RTC performance discussion turns
 * on, and a number read off the call graph is worth less than one taken from
 * the endpoint running. These tests drive `/wp-sync/v1/updates` for real and
 * report what the database actually saw, via the sync-query-counter mu-plugin.
 *
 * The invariant under test is that the count is flat in the number of clients
 * sharing a room. An adapter that writes every client's row on every poll makes
 * it linear instead, which at the one-second collaborator interval is the
 * difference between thousands and tens of thousands of writes an hour.
 */
import { test as base, expect } from '@wordpress/e2e-test-utils-playwright';
import {
	createCollaborativeSessions,
	closeCollaborativeSessions,
	pollSync,
	postRoom,
	resetQueryLog,
	readQueryLog,
} from './utils/collaborative';

const test = base.extend({});

/**
 * Awareness entries are stamped with `time()`, so two polls inside the same
 * second are indistinguishable by age. Waiting past a second boundary makes
 * "this client is the freshest" a property of the data rather than of timing.
 */
async function waitForNewSecond(): Promise<void> {
	await new Promise((resolve) => setTimeout(resolve, 1100));
}

test.describe('Polling cost', () => {
	test('query count per poll is flat in the number of clients', async ({
		browser,
	}) => {
		const sessions = await createCollaborativeSessions(browser, 3);

		try {
			const post = await sessions[0].requestUtils.createPost({
				title: 'Polling Cost',
				content: 'Measured, not inferred.',
				status: 'publish',
			});
			const room = postRoom(post.id);

			// One client in the room.
			resetQueryLog();
			await pollSync(sessions[0], room, { awareness: { cursor: 1 } });
			const solo = readQueryLog();

			expect(solo).toHaveLength(1);

			// Two more clients join, then age past the second boundary so the
			// measured poll is unambiguously the freshest entry.
			await pollSync(sessions[1], room, { awareness: { cursor: 2 } });
			await pollSync(sessions[2], room, { awareness: { cursor: 3 } });
			await waitForNewSecond();

			resetQueryLog();
			await pollSync(sessions[0], room, { awareness: { cursor: 4 } });
			const crowded = readQueryLog();

			expect(crowded).toHaveLength(1);

			// eslint-disable-next-line no-console
			console.log(
				`queries per poll: ${solo[0].queries} at 1 client, ${crowded[0].queries} at 3`
			);

			expect(crowded[0].queries).toBe(solo[0].queries);
		} finally {
			await closeCollaborativeSessions(sessions);
		}
	});

	test('a poll that is not the freshest writes nothing extra', async ({
		browser,
	}) => {
		const sessions = await createCollaborativeSessions(browser, 2);

		try {
			const post = await sessions[0].requestUtils.createPost({
				title: 'Polling Cost - Relay',
				content: 'Measured, not inferred.',
				status: 'publish',
			});
			const room = postRoom(post.id);

			await pollSync(sessions[0], room, { awareness: { cursor: 1 } });
			await waitForNewSecond();

			// Session 1 is now the freshest; session 0's entry is older and is
			// relayed back to storage untouched rather than rewritten.
			resetQueryLog();
			await pollSync(sessions[1], room, { awareness: { cursor: 2 } });
			const relayed = readQueryLog();

			expect(relayed).toHaveLength(1);

			resetQueryLog();
			await pollSync(sessions[1], room, { awareness: { cursor: 3 } });
			const alone = readQueryLog();

			expect(relayed[0].queries).toBe(alone[0].queries);
		} finally {
			await closeCollaborativeSessions(sessions);
		}
	});
});
