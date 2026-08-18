/**
 * Global setup for Playwright tests.
 * Handles WordPress authentication.
 */
import { chromium, FullConfig } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

async function globalSetup(config: FullConfig) {
	const { baseURL, storageState } = config.projects[0].use;

	const browser = await chromium.launch();
	const context = await browser.newContext({ baseURL });
	const page = await context.newPage();

	// Navigate to login page
	await page.goto('/wp-login.php');

	// Fill in credentials (wp-env defaults)
	const username = process.env.WP_USERNAME || 'admin';
	const password = process.env.WP_PASSWORD || 'password';

	await page.fill('input[name="log"]', username);
	await page.fill('input[name="pwd"]', password);
	await page.click('input[type="submit"]');

	// Wait for navigation to complete
	await page.waitForURL('**/wp-admin/**');

	// Save authenticated state
	await context.storageState({ path: storageState as string });

	await browser.close();

	// createCollaborativeSessions() creates a fresh RequestUtils per test and
	// does its own login + REST discovery handshake (RequestUtils.setupRest()).
	// The UI login above doesn't exercise that path at all, so its cold-start
	// cost (opcache priming REST routes on a fresh install, on top of a
	// freshly-built Gutenberg trunk) would otherwise land inside whichever
	// test runs first, against that test's own timeout. Pay it once, here,
	// against setup's own budget, by running the exact same handshake.
	const admin = await RequestUtils.setup({
		user: { username, password },
		baseURL: baseURL as string,
	});
	await admin.rest({ path: '/' });
}

export default globalSetup;
