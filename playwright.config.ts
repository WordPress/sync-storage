import { defineConfig, devices } from '@playwright/test';
import * as dotenv from 'dotenv';

// Load environment variables from .env file
dotenv.config();

const baseURL = process.env.WP_BASE_URL || 'http://localhost:8888';

export default defineConfig({
	testDir: './tests/e2e',
	fullyParallel: false, // Collaborative tests need sequential execution
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1, // Single worker for collaborative scenarios
	reporter: process.env.CI ? 'github' : 'list',
	globalSetup: './tests/e2e/global-setup.ts',
	use: {
		baseURL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		storageState: 'tests/e2e/.auth/storageState.json',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
	webServer: {
		command: 'npm run env:start',
		// rest_api_init only fires for requests actually routed through
		// /wp-json/, unlike a plain homepage request. Pointing the readiness
		// check here (rather than at baseURL) makes Playwright's own polling
		// pay for REST route registration once, up front, instead of the
		// first test that calls RequestUtils.rest() paying it inside its own
		// 30s test timeout.
		url: `${baseURL}/wp-json/`,
		// In CI, playwright.yml already starts wp-env in its own step, so
		// reuseExistingServer stops this block from starting a second instance
		// and colliding on the port. Locally, nothing else starts wp-env, so
		// this block does it.
		reuseExistingServer: true,
		timeout: 120_000,
	},
});
