# Updating Snipe-IT (this fork)

How to pull in a new upstream Snipe-IT release and publish updated Docker images.

Upstream is **`grokability/snipe-it`** (the old `snipe/snipe-it` redirects here).
This fork carries custom commits, so upstream is never merged straight into
production — it flows through a staging branch first.

```
upstream release
  └─ you: git merge + npm run prod ▶ push develop      (staging)
                       └─ validate the develop image
                            └─ PR develop ▶ master     (production gate)
                                 └─ testing image (release candidate)
                                      └─ push tag X.Y.Z ▶ release
```

**Every step is manual.** There is no workflow watching upstream — the automated
sync was removed, along with its daily cron, its `contents:write` token and its
unattended build of third-party code. Nothing will notify you that a release
exists, security releases included.

- **`develop`** — integration/staging. Upstream lands here first.
- **`master`** — production gate. Only reviewed, validated code arrives via a `develop → master` PR. Merging publishes `testing`, not `latest`.
- **git tag `X.Y.Z`** — the release. Pushing it builds that commit and publishes `X.Y.Z` + `latest`.

**Image versions are the FORK's, not upstream's.** `X.Y.Z` is the number *you*
pick when pushing the tag. It has no relationship to the upstream Snipe-IT
version, and cannot: several fork releases legitimately sit on top of one upstream
release, so upstream's number can't name them apart. The upstream version stays
visible at runtime in the app footer and at `/api/v1/version`, both of which read
`config/version.php`.

---

## One-time setup, per clone

Both of these are required. The second one is not optional housekeeping — without
it, every upstream merge conflicts on the committed compiled assets.

```bash
git remote add upstream https://github.com/grokability/snipe-it.git
git config merge.ours.driver true      # see .gitattributes:15
```

`merge.ours.driver` registers the driver `.gitattributes` refers to, which makes a
merge **keep our committed copy** of `public/js`, `public/css` and
`public/mix-manifest.json` instead of conflicting on them.

---

## Syncing an upstream release

**This is a manual process.** There is no workflow watching upstream and no
notification — the automated sync was removed deliberately. Nothing will tell you
a new release exists, including a security release. Check when you think to.

### 1. Is there a new stable release?

```bash
gh release view --repo grokability/snipe-it
```

`gh release view` with no tag returns the **latest published, non-draft,
non-prerelease** release — which is the gate you want. Do not sync a prerelease.

### 2. Merge it into `develop` — never `master`

```bash
git fetch upstream --tags
git checkout develop && git pull
git merge v8.7.0                       # assets auto-resolve via merge=ours
```

**The merge must happen locally.** `merge=ours` is only honoured by a local
`git merge` — GitHub's PR-merge machinery ignores it entirely, so merging upstream
through the GitHub UI produces asset conflicts every time. This is why the old
workflow performed the merge in CI rather than letting GitHub do it.

If real (non-asset) conflicts remain, resolve them now.

### 3. Rebuild the compiled assets — always

```bash
npm ci && npm run prod
git add public/js public/css public/mix-manifest.json
git commit -m "sync upstream v8.7.0 + rebuild assets"
```

**Do not skip this even if the merge was clean.** The Docker images copy the
committed assets in and have no in-image build step, so stale assets ship silently
— no error, no failing build, just a UI built against the previous release's PHP.

Build on Linux/WSL if you can. Building on Windows rewrites these files with CRLF
line endings and produces a large, noisy diff.

### 4. Push

```bash
git push
```

**Never `git push --tags`.** With no automation writing refs, that command is now
the only way upstream's ~300 mirrored tags reach origin. Most are `v`-prefixed and
harmless, but it is a habit worth not having — see
[Golden rules](#golden-rules).

Pushing `develop` builds the **`develop` / `develop-alpine`** image — your staging
artifact.

### 5. Validate the staging image
```bash
docker pull ghcr.io/citadeliscybersecurity/snipe-it:develop
docker buildx imagetools inspect ghcr.io/citadeliscybersecurity/snipe-it:develop   # confirm amd64 + arm64
```
Smoke-test until satisfied.

### 6. Promote `develop → master`
Open a PR from `develop` into `master` (title e.g. `Release: upstream vX.Y.Z`),
review, and merge. Merging publishes **`testing`** (+ `-alpine`) — the release
candidate. It does **not** move `latest`.

### 7. Release — push a version tag

Pick the next fork release number and push it from `master`:

```bash
git checkout master && git pull
git tag 2.0.0 && git push origin 2.0.0
```

That builds the tagged commit and publishes **`X.Y.Z`**, **`latest`** and
**`alpine`** (+ their `-alpine` variants).

The tag must be **unprefixed**. `v2.0.0` matches no trigger and builds nothing —
deliberately, so that upstream's `v*` tags can never fire a release.

Note this **rebuilds** the tagged commit rather than retagging the `testing`
digest you validated in step 5. Same source, fresh build, so a base image that
moved in the meantime lands in the release. See [RELEASING.md](RELEASING.md) for
the full release path, the `guard` ancestry check, and rollback.

---

## What triggers which image

| Action | Image tag(s) |
|---|---|
| `develop` pushed (after a manual sync) | `develop` / `develop-alpine` (staging) |
| `develop → master` PR merged | `testing` (+ `-alpine`) — release candidate |
| **git tag `X.Y.Z` pushed** | `X.Y.Z`, `latest`, `alpine` (+ `-alpine`) — production |

Base image: `ghcr.io/citadeliscybersecurity/snipe-it`

`X.Y.Z` is the **fork's** release number, chosen by whoever pushes the tag — not
upstream's version. It is also the only **immutable** tag: `latest`, `alpine`,
`testing` and the branch pointers all move, and `X.Y.Z` never does. That is
precisely what makes the version tags a release history rather than a label.

`testing` is the last build of `master`; it diverges from `latest` on the next
merge. Those are the only tags published — no minor-series pointer (`2.0`) and no
per-commit `<branch>-<sha>` tag.

### Pinning and rollback

**Version tags never move, so every release ever published is still pullable.**
Roll back by naming the version:

```bash
APP_VERSION=1.9.3 docker compose up -d   # exactly that release, indefinitely
```

`ghcr-cleanup.yml` excludes version tags from its weekly prune, so there is no
expiry window on this.

`latest`, `testing` and `develop` **do** move, so pin a digest if you need to hold
on a build that was never released:

```bash
docker buildx imagetools inspect ghcr.io/citadeliscybersecurity/snipe-it:testing
```

```yaml
# in a compose override
image: ghcr.io/citadeliscybersecurity/snipe-it@sha256:<digest>
```

Untagged digests survive only while they are newer than `ghcr-cleanup.yml`'s
`older-than` window (**4 weeks**). Past that an unreleased build is gone — which is
a reason to cut a real release rather than pin a candidate long-term.

To rebuild a specific commit, re-run the docker workflow on that ref (Actions →
*Docker images (Ubuntu)* / *Docker images (Alpine)* → **Run workflow**). That
republishes only that ref's own pointer and never touches `latest`.

---

## Golden rules

- **Upstream always lands on `develop` first** — never merge a raw upstream drop straight to `master`.
- **Merge upstream locally, never through the GitHub UI.** `merge=ours` — the thing
  that stops committed assets conflicting — is only honoured by a local `git merge`.
- **Always `npm run prod` after an upstream merge, even a clean one.** The images
  copy committed assets in and never build them, so stale assets ship silently.
- **Never `git push --tags`.** Nothing automated writes refs any more, so that is
  the only way upstream's ~300 mirrored tags reach origin. Two of them — `3.2.0`
  and `5.1.7` — are version-shaped *and* ancestors of `master`, so they would pass
  the `guard` check; the docker workflows exclude them by name for exactly that
  reason. Push branches and release tags explicitly, one at a time.
- **Every push to `master` publishes `testing`, not `latest`.** Merging to `master`
  cuts the release candidate; production moves only when someone pushes an `X.Y.Z`
  git tag, and only if that commit is already an ancestor of `master`.
- **Release tags are unprefixed, and a number is never reused.** `2.0.0`, not
  `v2.0.0` — a `v` matches no trigger. And once published, a number names one build
  forever; re-pushing it over a different build destroys the rollback guarantee
  that the whole scheme rests on.
- **Never name a branch `latest`, `testing`, `alpine`, or anything version-shaped**
  (`8.6.4`, `v8.6.4`). A branch build is tagged with its own name, so those would
  collide with the production pointers. The docker workflows refuse such branches
  outright.
- **Never hand-edit `app_version` in `config/version.php`.** It is upstream's file
  and arrives with the upstream merge. Nothing in CI reads it any more, but the app
  footer and `/api/v1/version` do — editing it makes the running app misreport
  which Snipe-IT it is.
- **Compiled assets stay committed.** The Docker image copies them in and there is
  no in-image build step, so `npm run prod` after every upstream merge is on you —
  see step 3.
