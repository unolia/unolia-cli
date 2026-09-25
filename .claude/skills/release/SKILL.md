---
name: release
description: Cut a new unolia-cli release. Drafts the notes in chat for review, pushes the tag, lets the Build PHAR workflow open a draft release with the phar, puts the notes on the draft, and leaves publishing to the owner. Use when asked to tag, release, ship or publish a version.
---

# Releasing unolia-cli

Releases are **immutable** on this repository. Once a release is published, GitHub refuses new assets,
and a deleted release's tag can never be reused. The phar must be on the release before it is published.
v2.0.0 shipped without a phar because it was published first.

So the flow is: **tag → the workflow drafts the release with the phar → notes go on the draft → the owner
publishes.** Never create or publish a release yourself.

## 1. Check that main is ready

```bash
git fetch --tags
git status -sb                       # on main, clean, not ahead or behind origin
gh api repos/unolia/unolia-cli/commits/$(git rev-parse HEAD)/check-runs \
  -q '.check_runs[] | "\(.name) \(.conclusion)"'   # every check succeeded
```

If `main` is ahead of `origin`, ask the owner before pushing it. If checks are failing or still running,
wait or report back. Do not tag a red commit.

## 2. Pick the version

```bash
gh release list --limit 5
git log --format='%h %s' <last-tag>..HEAD
```

Semver: breaking changes mean major, a `feat` means minor, only fixes and chores mean patch. Propose a
version and ask when it is not obvious.

## 3. Draft the notes in chat first

Write them to the scratchpad as `vX.Y.Z.md` and show them **in full** in the conversation. Do not go
further until the owner approves them.

- Structure: a short intro, `## Highlights` (bold lead, then one or two sentences), `## Upgrading from vN`
  when something breaks or moves, `## Install` for a major, and a final
  `**Full Changelog**: https://github.com/unolia/unolia-cli/compare/<last-tag>...vX.Y.Z`.
- Group commits by what a user gets, not by commit. Leave out dependabot bumps, tests and internal
  refactors unless they change behavior.
- Check every claim against `php bin/unolia <command> --help`. Do not describe a command from its
  commit message alone.
- CLI copy rules apply: no semicolons, no em dashes, no emojis, short sentences.
- Never reference the private app repository. Say "the matching change in the app".
- No Claude attribution or session link.

## 4. Push the tag

Confirm with the owner, then:

```bash
git tag vX.Y.Z            # on the commit whose checks you verified
git push origin vX.Y.Z
```

Pushing a `v*` tag starts `.github/workflows/build-phar.yml`. It compiles the phar, smoke tests it
(`--version` must contain the tag), writes `unolia.phar.sha256`, and creates a **draft** release with both
files attached and generated notes as a placeholder.

## 5. Wait for the draft

```bash
gh run list --workflow build-phar.yml --limit 1
gh run watch <run-id> --exit-status
gh release view vX.Y.Z --json isDraft,assets -q '[.isDraft, [.assets[].name]]'
```

Expect `true` and both `unolia.phar` and `unolia.phar.sha256`. If the run fails, read
`gh run view <run-id> --log-failed`, fix on main, and rerun. A rerun can create a second draft for the
same tag, so delete the extra one (it is a draft, nothing is lost).

## 6. Put the notes on the draft

```bash
gh release edit vX.Y.Z --title vX.Y.Z --notes-file <scratchpad>/vX.Y.Z.md
```

Give the owner the draft's URL and stop there. **The owner publishes.** Only if they explicitly ask you
to do it:

```bash
gh release edit vX.Y.Z --draft=false --latest
```

## Never

- `gh release create` without `--draft`. It publishes an immutable release with no phar.
- Deleting a published release to "redo" it. The tag is burned. Ship the next patch instead.
- `gh release create --target <short-sha>`. The API rejects short SHAs, so use the full one.
