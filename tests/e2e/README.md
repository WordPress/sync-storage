# End-to-End Tests for Sync Storage

Playwright-based e2e tests for the WP_Sync_Storage implementation, with specialized utilities for testing collaborative editing scenarios.

## Running Tests

```bash
# Run all e2e tests
npm run test:e2e

# Run tests with headed browser (visible)
npm run test:e2e:headed

# Debug mode (step through tests)
npm run test:e2e:debug

# Run specific test file
npx playwright test sync-storage.spec.ts

# Run tests matching a pattern
npx playwright test --grep "collaborative"
```

## Test Structure

### `sync-storage.spec.ts`
Main test suite covering:
- **Basic Functionality** - Plugin activation, table creation, editor loading
- **Collaborative Editing** - Multi-user scenarios, content sync, concurrent edits
- **Server Authority** - RTC activation when 2+ editors present

### `utils/collaborative.ts`
Reusable utilities for testing collaborative scenarios. **This could evolve into `@wordpress/e2e-test-utils-collaborative` package.**

#### Key Utilities

**`createCollaborativeSessions(browser, count)`**
Creates multiple browser contexts simulating concurrent users.

```typescript
const sessions = await createCollaborativeSessions(browser, 2);
// Returns array of { context, page, admin, userId, userName }
```

**`openPostInSessions(sessions, postId)`**
Opens the same post in all collaborative sessions.

```typescript
await openPostInSessions(sessions, 123);
```

**`typeInEditor(page, text)`**
Types text in the editor and waits for debounced sync.

```typescript
await typeInEditor(user1.page, 'Hello from user 1');
```

**`getEditorContent(page)`**
Retrieves current editor content.

```typescript
const content = await getEditorContent(page);
expect(content).toContain('Hello');
```

**`waitForContentSync(sessions, expectedContent, timeout)`**
Waits for content to sync across all sessions.

```typescript
await waitForContentSync(sessions, 'synced text', 5000);
```

**`captureStorageCalls(page)`**
Monitors WP_Sync_Storage method calls for debugging.

```typescript
const calls = await captureStorageCalls(page);
// Returns array of { method, args }
```

## Collaborative Testing Pattern

Standard pattern for multi-user tests:

```typescript
test('users collaborate', async ({ browser }) => {
  const sessions = await createCollaborativeSessions(browser, 2);
  
  try {
    // Setup
    await openPostInSessions(sessions, postId);
    
    const [user1, user2] = sessions;
    
    // User 1 edits
    await typeInEditor(user1.page, 'Edit from user 1');
    
    // Verify sync to User 2
    await waitForContentSync(sessions, 'Edit from user 1');
    
    const user2Content = await getEditorContent(user2.page);
    expect(user2Content).toContain('Edit from user 1');
  } finally {
    await closeCollaborativeSessions(sessions);
  }
});
```

## Why Specialized Utilities?

Standard `@wordpress/e2e-test-utils-playwright` provides single-user testing. Collaborative editing requires:

1. **Multiple browser contexts** - Each user needs isolated session
2. **Sync coordination** - Waiting for changes to propagate
3. **Concurrent interactions** - Simulating simultaneous edits
4. **Storage monitoring** - Verifying sync infrastructure calls

These utilities bridge that gap and could become a dedicated package for testing WordPress collaborative features.

## CI Integration

Tests run automatically on:
- Pull requests (paths: `**.php`, `**.ts`, `tests/e2e/**`)
- Pushes to `main`
- Manual workflow dispatch

See `.github/workflows/playwright.yml` for CI configuration.

## Future Package: `@wordpress/e2e-test-utils-collaborative`

The `utils/collaborative.ts` module is designed for extraction into a standalone package:

**Benefits:**
- Reusable across WordPress collaborative features (RTC, co-authoring, etc.)
- Standardized testing patterns for multi-user scenarios
- Integration with WordPress e2e ecosystem

**Potential API:**

```typescript
import { createCollaborativeSession } from '@wordpress/e2e-test-utils-collaborative';

const collab = await createCollaborativeSession({
  users: 2,
  postId: 123,
});

await collab.user(0).type('First user edit');
await collab.waitForSync();

expect(await collab.user(1).getContent()).toContain('First user edit');
```

**When to extract:**
- Multiple WordPress projects need collaborative testing
- API stabilizes through usage in sync-storage tests
- Community interest in standardized collaborative e2e patterns
