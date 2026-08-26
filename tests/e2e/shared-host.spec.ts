/**
 * Shared host compatibility tests.
 *
 * Proves sync-storage works on environments where WebSocket upgrades are
 * rejected — the case on virtually all shared hosting. HTTP polling is the
 * default transport; these tests make that guarantee explicit and machine-
 * checkable.
 *
 * The browser-facing tests block window.WebSocket before any page navigation
 * so any accidental dependency on it surfaces as a test failure rather than
 * a silent production regression.
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

const test = base.extend( {} );

// Throw on WebSocket construction so any code path that requires it fails
// loudly rather than falling back silently in ways the test wouldn't catch.
const WS_BLOCKER = () => {
	Object.defineProperty( window, 'WebSocket', {
		get() {
			const err = new Error(
				'[shared-host-sim] WebSocket is not available on this server'
			);
			err.name = 'NotSupportedError';
			throw err;
		},
		configurable: false,
	} );
};

test.describe( 'Shared Host — Browser', () => {
	test.beforeEach( async ( { context } ) => {
		// addInitScript runs before scripts on every navigation in this
		// context, so it covers createNewPost, visitAdminPage, etc.
		await context.addInitScript( WS_BLOCKER );
	} );

	test( 'plugin is active', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'plugins.php' );
		const row = page.locator( 'tr[data-slug="sync-storage"]' );
		await expect( row ).toBeVisible();
		await expect( row.locator( '.active' ) ).toBeVisible();
	} );

	test( 'editor loads without errors when WebSocket is unavailable', async ( {
		admin,
		page,
	} ) => {
		const errors: string[] = [];
		page.on( 'console', ( msg ) => {
			if ( msg.type() === 'error' ) errors.push( msg.text() );
		} );

		await admin.createNewPost();
		await page.waitForSelector( '.edit-post-layout', { timeout: 10_000 } );
		await page.waitForTimeout( 2_000 );

		const critical = errors.filter(
			( e ) =>
				! e.includes( 'favicon.ico' ) &&
				! e.includes( 'ResizeObserver' ) &&
				! e.includes( 'Failed to load resource' )
		);
		expect( critical ).toHaveLength( 0 );
	} );

	test( 'sync polling starts automatically without WebSocket', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Shared Host Polling Test',
			content: 'Initial.',
			status: 'publish',
		} );

		await admin.visitAdminPage( `post.php?post=${ post.id }&action=edit` );

		// Resolves only when a POST to /wp-sync/v1/updates is observed —
		// proving HTTP polling started with no WebSocket involved.
		await waitForSyncStorage( page );
	} );
} );

test.describe( 'Shared Host — REST Layer', () => {
	// These tests drive the REST endpoint directly. WebSocket is never in
	// scope for REST requests; the suite documents that the storage contract
	// is entirely HTTP-based.

	test( 'edits sync between users via polling', async ( { browser } ) => {
		const sessions = await createCollaborativeSessions( browser, 2 );

		try {
			const post = await sessions[ 0 ].requestUtils.createPost( {
				title: 'Shared Host Edit Sync',
				content: 'Initial.',
				status: 'publish',
			} );
			const room = postRoom( post.id );

			const update = {
				type: 'update',
				data: Buffer.from( 'shared-host-edit' ).toString( 'base64' ),
			};

			const posted = await pollSync( sessions[ 0 ], room, {
				updates: [ update ],
			} );
			expect( posted.total_updates ).toBe( 1 );

			const received = await pollSync( sessions[ 1 ], room, { after: 0 } );
			expect( received.updates ).toHaveLength( 1 );
			expect( received.updates[ 0 ].data ).toBe( update.data );
		} finally {
			await closeCollaborativeSessions( sessions );
		}
	} );

	test( 'awareness propagates via REST without WebSocket', async ( {
		browser,
	} ) => {
		const sessions = await createCollaborativeSessions( browser, 2 );

		try {
			const post = await sessions[ 0 ].requestUtils.createPost( {
				title: 'Shared Host Presence Test',
				content: 'Initial.',
				status: 'publish',
			} );
			const room = postRoom( post.id );

			await pollSync( sessions[ 0 ], room, {
				awareness: { cursor: 5 },
			} );

			const presence = await getPresence( sessions[ 1 ], room );
			const entry = presence.find(
				( p ) => p.user_id === sessions[ 0 ].userId
			);

			expect( entry ).toBeDefined();
			expect( entry?.data ).toMatchObject( { cursor: 5 } );
		} finally {
			await closeCollaborativeSessions( sessions );
		}
	} );

	test( 'sync endpoint responds within shared host time limits', async ( {
		browser,
	} ) => {
		const sessions = await createCollaborativeSessions( browser, 1 );

		try {
			const post = await sessions[ 0 ].requestUtils.createPost( {
				title: 'Shared Host Timing Test',
				content: 'Initial.',
				status: 'publish',
			} );
			const room = postRoom( post.id );

			const start = Date.now();
			await pollSync( sessions[ 0 ], room, {} );
			const elapsed = Date.now() - start;

			// Shared hosts typically enforce max_execution_time of 30s.
			// The sync endpoint should resolve in well under that even on
			// constrained infrastructure.
			expect( elapsed ).toBeLessThan( 5_000 );
		} finally {
			await closeCollaborativeSessions( sessions );
		}
	} );

	test( 'concurrent updates from two users are both preserved', async ( {
		browser,
	} ) => {
		// Regression coverage for the core shared-host scenario: two editors
		// on a slow polling cycle (higher latency than WebSocket) submitting
		// updates in the same window. Neither should be dropped.
		const sessions = await createCollaborativeSessions( browser, 2 );

		try {
			const post = await sessions[ 0 ].requestUtils.createPost( {
				title: 'Shared Host Concurrent Edits',
				content: 'Initial.',
				status: 'publish',
			} );
			const room = postRoom( post.id );

			const updateA = {
				type: 'update',
				data: Buffer.from( 'from-editor-a' ).toString( 'base64' ),
			};
			const updateB = {
				type: 'update',
				data: Buffer.from( 'from-editor-b' ).toString( 'base64' ),
			};

			await Promise.all( [
				pollSync( sessions[ 0 ], room, { updates: [ updateA ] } ),
				pollSync( sessions[ 1 ], room, { updates: [ updateB ] } ),
			] );

			const result = await pollSync( sessions[ 0 ], room, {
				after: 0,
				clientId: 9999,
			} );

			const receivedData = result.updates.map( ( u ) => u.data );
			expect( receivedData ).toEqual(
				expect.arrayContaining( [ updateA.data, updateB.data ] )
			);
			expect( result.total_updates ).toBe( 2 );
		} finally {
			await closeCollaborativeSessions( sessions );
		}
	} );
} );
