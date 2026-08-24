'use strict';

const MARKER = '<!-- sync-storage:release-props -->';
const RELEASE_BRANCH = 'release-please--';
// Plugin releases are tagged vX.Y.Z. The releases list also holds Playground
// preview releases (preview-pr-N) and release-please drafts, neither of which
// may set the cutoff.
const RELEASE_TAG = /^v\d+\.\d+\.\d+$/;
const WPORG_LOOKUP = 'https://profiles.wordpress.org/wp-json/wporg-github/v1/lookup/';
// props-bot writes this sentence under an "## Unlinked Accounts" heading for
// every contributor with no WordPress.org account linked to their GitHub one.
// See commentProps() in WordPress/props-bot-action, src/github.js. Logins
// cannot contain a period, so the sentence ends at the first one.
const UNLINKED = /^The following contributors have not linked their GitHub and WordPress\.org accounts: ([^.]+)\./m;
// The merge commit trailers props-bot writes under `format: git`, which is the
// only place a linked contributor's WordPress.org slug appears in that format.
// props-bot synthesises the address from the slug, so the local part is the
// slug: see contributorLists.coAuthored in props-bot-action,
// src/contribution-collector.js.
const CO_AUTHORED = /^Co-authored-by: .+ <([^@\s>]+)@git\.wordpress\.org>$/gm;

function findCutoff(releases) {
  return releases
    .filter(r => !r.draft && r.published_at && RELEASE_TAG.test(r.tag_name || ''))
    .map(r => r.published_at)
    .sort()
    .pop();
}

function parsePropsNames(body) {
  const match = body.match(/Props ([^.]+)\./);
  if (!match) return [];
  return match[1].split(', ').map(n => n.trim()).filter(Boolean);
}

// Both shapes of props comment. props-bot.yml asks for `format: git`, whose
// comments carry Co-authored-by trailers and no "Props " line at all; comments
// left on pull requests merged while this repository asked for the SVN format
// are still read back here, and both formats omit their props block entirely
// when nobody on the pull request is linked yet. Matching only one shape would
// skip exactly the pull requests the unlinked recovery below exists for.
function parseCoAuthoredSlugs(body) {
  return [...body.matchAll(CO_AUTHORED)].map(m => m[1]);
}

function isPropsBotComment(comment) {
  return (
    comment.user.login === 'github-actions[bot]' &&
    (comment.body.includes('Props ') ||
      comment.body.includes('Co-authored-by: ') ||
      UNLINKED.test(comment.body))
  );
}

function parseUnlinkedLogins(body) {
  const match = body.match(UNLINKED);
  if (!match) return [];
  return match[1].split(',').map(n => n.trim().replace(/^@/, '')).filter(Boolean);
}

// Asks WordPress.org which of these GitHub logins now have a linked profile.
// Same endpoint props-bot uses, so a login resolves here exactly when it would
// have resolved there.
async function resolveWPOrgLogins(logins, { userAgent, fetchImpl = fetch }) {
  if (logins.length === 0) return [];

  const response = await fetchImpl(WPORG_LOOKUP, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'User-Agent': userAgent },
    body: JSON.stringify({ github_user: logins }),
  });

  if (!response.ok) {
    throw new Error(`WordPress.org lookup responded ${response.status}.`);
  }

  const data = await response.json();

  // An unlinked login comes back as `false` rather than an object.
  return logins.map(login => data?.[login]?.slug).filter(Boolean);
}

function sortProps(names, sortLast) {
  const unique = [...new Set(names)];
  if (!sortLast) return unique;
  return [...unique.filter(n => n !== sortLast), ...(unique.includes(sortLast) ? [sortLast] : [])];
}

// Mirrors the props-bot comment layout so the props line is a copyable code
// block rather than prose.
function buildComment(names) {
  return [
    MARKER,
    '',
    'Core Committers: Use this line as a base for the props when committing in SVN:',
    '',
    '```',
    `Props ${names.join(', ')}.`,
    '```',
  ].join('\n');
}

async function run({ github, context, core, env = process.env, fetchImpl = fetch }) {
  const { owner, repo } = context.repo;
  const sortLast = env.PROPS_SORT_LAST || '';

  // 1. Resolve the release PR. release-please only reports it on runs where it
  //    touched the PR, so fall back to whichever release PR is open. Without
  //    this, contributors merged after the last touch are never aggregated.
  let prNumber = Number(env.PR_NUMBER);
  if (!prNumber) {
    const { data: openPRs } = await github.rest.pulls.list({
      owner, repo, state: 'open', base: 'main', per_page: 100,
    });
    prNumber = openPRs.find(pr => pr.head.ref.startsWith(RELEASE_BRANCH))?.number ?? 0;
  }
  if (!prNumber) { core.info('No open release PR; skipping comment.'); return; }

  // 2. Get cutoff from the latest published plugin release.
  const { data: releases } = await github.rest.repos.listReleases({ owner, repo, per_page: 100 });
  const cutoff = findCutoff(releases);

  // 3. List merged PRs since cutoff, skipping the release PR and bot-managed branches.
  const allClosed = await github.paginate(
    github.rest.pulls.list,
    { owner, repo, state: 'closed', base: 'main', per_page: 100 }
  );
  const mergedPRs = allClosed.filter(pr =>
    pr.merged_at &&
    pr.number !== prNumber &&
    !pr.head.ref.startsWith(RELEASE_BRANCH) &&
    !pr.head.ref.startsWith('docs/add-') &&
    (!cutoff || pr.merged_at >= cutoff)
  );

  // 4. Collect each merged PR's latest props-bot comment.
  const propsBodies = (
    await Promise.all(
      mergedPRs.map(pr =>
        github.rest.issues.listComments({ owner, repo, issue_number: pr.number, per_page: 100 })
      )
    )
  )
    .map(({ data: comments }) => comments.findLast(isPropsBotComment))
    .filter(Boolean)
    .map(c => c.body);

  const propped = propsBodies.flatMap(body => [
    ...parsePropsNames(body),
    ...parseCoAuthoredSlugs(body),
  ]);
  const unlinked = [...new Set(propsBodies.flatMap(parseUnlinkedLogins))];

  if (propped.length === 0 && unlinked.length === 0) {
    core.info('No props found across merged PRs; skipping comment.');
    return;
  }

  // 5. Re-resolve anyone props-bot recorded as unlinked. That comment is a
  //    snapshot taken while the pull request was open, and every trigger in
  //    props-bot.yml requires an open pull request, so nothing refreshes it
  //    after merge. Without this, linking a WordPress.org account any time
  //    after your own pull request merges drops you from the release props
  //    permanently. Checking here moves the deadline to the release.
  let recovered = [];
  try {
    recovered = await resolveWPOrgLogins(unlinked, {
      userAgent: `${owner}/${repo}`,
      fetchImpl,
    });
  } catch (error) {
    // Losing a late linker is better than losing the whole props comment.
    core.warning(`Could not re-check unlinked accounts: ${error.message}`);
  }

  if (recovered.length > 0) {
    core.info(`Contributors linked since their pull request merged: ${recovered.join(', ')}.`);
  }

  const allNames = [...propped, ...recovered];

  if (allNames.length === 0) {
    core.info('Every contributor since the last release is still unlinked; skipping comment.');
    return;
  }

  // 6. Deduplicate and sort.
  const sorted = sortProps(allNames, sortLast);

  // 7. Find or create sticky comment on the release PR.
  const { data: releaseComments } = await github.rest.issues.listComments({
    owner, repo, issue_number: prNumber,
  });
  const existing = releaseComments.find(c => c.body.includes(MARKER));
  const body = buildComment(sorted);

  if (existing) {
    await github.rest.issues.updateComment({ owner, repo, comment_id: existing.id, body });
  } else {
    await github.rest.issues.createComment({ owner, repo, issue_number: prNumber, body });
  }
}

module.exports = run;
module.exports.findCutoff = findCutoff;
module.exports.parsePropsNames = parsePropsNames;
module.exports.parseCoAuthoredSlugs = parseCoAuthoredSlugs;
module.exports.parseUnlinkedLogins = parseUnlinkedLogins;
module.exports.isPropsBotComment = isPropsBotComment;
module.exports.resolveWPOrgLogins = resolveWPOrgLogins;
module.exports.sortProps = sortProps;
module.exports.buildComment = buildComment;
module.exports.MARKER = MARKER;
