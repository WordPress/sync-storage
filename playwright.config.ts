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
		url: baseURL,
		reuseExistingServer: !process.env.CI,
		timeout: 120_000,
	},
});
