/**
 * Global setup for Playwright tests.
 * Handles WordPress authentication.
 */
import { chromium, FullConfig } from '@playwright/test';

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
}

export default globalSetup;
