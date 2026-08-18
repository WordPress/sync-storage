import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.WP_BASE_URL || 'http://localhost:8888';

export default defineConfig({
	testDir: './tests/e2e',
	fullyParallel: false, // Collaborative tests need sequential execution
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1, // Single worker for collaborative scenarios
	reporter: process.env.CI ? 'github' : 'list',
	use: {
		baseURL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
	webServer: {
		command: 'npm run env:start',
		url: baseURL,
		reuseExistingServer: !process.env.CI,
		timeout: 120_000,
	},
});
